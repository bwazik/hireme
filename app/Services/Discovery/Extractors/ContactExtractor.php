<?php

namespace App\Services\Discovery\Extractors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ContactExtractor
 *
 * Three-layer extraction strategy (each layer is free, layered by reliability):
 *
 *  Layer 1 — HTML attribute links (mailto:, tel:, wa.me)       ← most reliable
 *  Layer 2 — Visible plain text (stripped HTML)                ← moderate
 *  Layer 3 — Common email pattern guesser + MX DNS check       ← fallback
 *
 * Returns the UNION of all layers, deduplicated and normalised.
 */
class ContactExtractor
{
    private array $emailBlacklist = [
        'example.com', 'test.com', 'domain.com', 'yourdomain.com',
        'sentry.io', 'sentry-cdn.com',
        'w3.org', 'schema.org',
        'wordpress.org', 'jquery.com', 'jquery.org',
        'cloudflare.com', 'googleapis.com', 'gstatic.com',
        'bootstrapcdn.com', 'unpkg.com', 'cdnjs.com',
        'github.com', 'githubusercontent.com',
        'iana.org', 'icann.org',
        'wixpress.com', 'squarespace.com',
        'mailchimp.com', 'sendgrid.com',
        'gravatar.com', 'facebook.com', 'twitter.com',
        'npmjs.com', 'node.js', 'php.net',
    ];

    /**
     * Common professional email prefixes — ordered by probability of being real.
     */
    private array $commonPrefixes = [
        'info', 'contact', 'hello', 'hi',
        'hr', 'careers', 'jobs', 'hiring', 'recruitment',
        'admin', 'support', 'office', 'team',
        'sales', 'business', 'work',
    ];

    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
    ];

    // =========================================================================
    // PUBLIC ENTRY POINT
    // =========================================================================

    /**
     * @param  string       $websiteUrl          e.g. 'https://gizasystems.com'
     * @param  string|null  $serperPhone         Phone already extracted by Serper Maps — passed in so we
     *                                           don't lose it if the website scrape fails entirely.
     * @param  bool         $usePatternGuesser   Run Layer 3 (MX-based email guesser)?
     *                                           Disable for speed during bulk runs; enable for targeted enrichment.
     *
     * @return array{
     *   emails:       string[],
     *   phones:       string[],
     *   found_via:    string[],   <- human-readable log of which layer succeeded
     *   is_js_heavy:  bool,       <- true if the site appears to be JS-rendered (React/Vue/Next etc.)
     * }
     */
    public function extract(
        string  $websiteUrl,
        ?string $serperPhone = null,
        bool    $usePatternGuesser = true
    ): array {
        $websiteUrl = rtrim($websiteUrl, '/');
        $domain     = $this->extractDomain($websiteUrl);

        $emails   = [];
        $phones   = [];
        $foundVia = [];
        $isJsHeavy = false;

        // ── Seed phones with what Serper already gave us ──────────────────────
        // The Serper Maps API returns a phoneNumber field directly.
        // This is company-entered, high-quality data.
        if ($serperPhone) {
            $normalised = preg_replace('/[^\d+]/', '', $serperPhone);
            if (strlen($normalised) >= 8 && strlen($normalised) <= 16) {
                $phones[]   = $normalised;
                $foundVia[] = 'serper_maps_direct';
            }
        }

        // ── Pages to check ────────────────────────────────────────────────────
        $pagesToCheck = [
            $websiteUrl,
            $websiteUrl . '/contact',
            $websiteUrl . '/contact-us',
            $websiteUrl . '/about',
            $websiteUrl . '/about-us',
            $websiteUrl . '/careers',
            $websiteUrl . '/jobs',
        ];

        $userAgent    = $this->userAgents[array_rand($this->userAgents)];
        $pagesScraped = 0;

        foreach ($pagesToCheck as $pageUrl) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders(['User-Agent' => $userAgent])
                    ->get($pageUrl);

                if (!$response->successful()) {
                    continue;
                }

                $html = $response->body();

                // ── Detect JS-heavy site (check on homepage only) ─────────────
                if ($pageUrl === $websiteUrl && $this->detectJsRendered($html)) {
                    $isJsHeavy = true;
                    Log::info("ContactExtractor: JS-heavy site detected — {$websiteUrl}");
                    // Don't abort — mailto links are sometimes still in the HTML shell
                }

                // ── Layer 1: HTML attribute links ─────────────────────────────
                $layer1Emails = $this->extractMailtoLinks($html);
                $layer1Phones = $this->extractTelLinks($html);
                $layer1Phones = array_merge($layer1Phones, $this->extractWhatsAppLinks($html));

                if ($layer1Emails) {
                    $emails   = array_merge($emails, $layer1Emails);
                    $foundVia[] = "mailto_link:{$pageUrl}";
                }
                if ($layer1Phones) {
                    $phones   = array_merge($phones, $layer1Phones);
                    $foundVia[] = "tel_link:{$pageUrl}";
                }

                // ── Layer 2: Visible plain text ───────────────────────────────
                // Skip Layer 2 on JS-heavy sites — the visible text will be empty/useless
                if (!$isJsHeavy) {
                    $text = $this->extractVisibleText($html);

                    $layer2Emails = $this->extractEmailsFromText($text);
                    $layer2Phones = $this->extractPhonesFromText($text);

                    if ($layer2Emails) {
                        $emails   = array_merge($emails, $layer2Emails);
                        $foundVia[] = "text_parse:{$pageUrl}";
                    }
                    if ($layer2Phones) {
                        $phones   = array_merge($phones, $layer2Phones);
                    }
                }

                $pagesScraped++;

            } catch (\Exception $e) {
                Log::debug("ContactExtractor: failed to fetch {$pageUrl} — " . $e->getMessage());
                continue;
            }
        }

        // ── Layer 3: Common email pattern guesser (MX-verified) ──────────────
        // Only run if:
        //   - We found no emails in Layers 1+2
        //   - We have a valid domain
        //   - Caller opted in
        if ($usePatternGuesser && empty($emails) && $domain) {
            $guessed = $this->guessEmailsByPattern($domain);
            if ($guessed) {
                $emails   = array_merge($emails, $guessed);
                $foundVia[] = 'pattern_guesser';
                Log::info("ContactExtractor: guessed " . count($guessed) . " emails for {$domain}");
            }
        }

        return [
            'emails'      => $this->cleanEmails($emails),
            'phones'      => $this->cleanPhones($phones),
            'found_via'   => array_unique($foundVia),
            'is_js_heavy' => $isJsHeavy,
        ];
    }

    // =========================================================================
    // LAYER 1 — HTML attribute extraction
    // =========================================================================

    private function extractMailtoLinks(string $html): array
    {
        preg_match_all('/href=["\']mailto:([^"\'?\s]+)/i', $html, $m);
        return $m[1] ?? [];
    }

    private function extractTelLinks(string $html): array
    {
        preg_match_all('/href=["\']tel:([^"\'?\s]+)/i', $html, $m);
        return array_map(fn($p) => preg_replace('/[^\d+]/', '', $p), $m[1] ?? []);
    }

    private function extractWhatsAppLinks(string $html): array
    {
        // Matches both:
        //   https://api.whatsapp.com/send?phone=201098617164
        //   https://wa.me/201098617164
        preg_match_all(
            '/(?:api\.whatsapp\.com\/send\?phone=|wa\.me\/)([0-9]{7,15})/i',
            $html,
            $m
        );
        return $m[1] ?? [];
    }

    // =========================================================================
    // LAYER 2 — Plain text extraction
    // =========================================================================

    private function extractVisibleText(string $html): string
    {
        // Remove script/style blocks entirely before stripping tags
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is',  '', $html);
        return strip_tags($html);
    }

    private function extractEmailsFromText(string $text): array
    {
        preg_match_all(
            '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,6}/',
            $text,
            $m
        );
        return $m[0] ?? [];
    }

    private function extractPhonesFromText(string $text): array
    {
        // Conservative pattern: requires a + prefix OR starts with 0, min 8 digits
        // Avoids matching CSS values, version numbers, z-index, etc.
        preg_match_all(
            '/(?:\+[\d\s\-\(\)]{7,18}|\b0[\d\s\-\(\)]{7,17})\b/',
            $text,
            $m
        );

        $phones = [];
        foreach ($m[0] ?? [] as $match) {
            $clean = preg_replace('/[^\d+]/', '', $match);
            // Enforce digit count: 8–15 actual digits
            $digitsOnly = preg_replace('/\D/', '', $clean);
            if (strlen($digitsOnly) >= 8 && strlen($digitsOnly) <= 15) {
                $phones[] = $clean;
            }
        }
        return $phones;
    }

    // =========================================================================
    // LAYER 3 — Email pattern guesser with MX verification
    // =========================================================================

    /**
     * Try common email prefixes against the company domain,
     * returning only those for which the domain has valid MX records.
     *
     * We do NOT actually send any email — we only check DNS.
     * This is 100% free (standard DNS lookups).
     *
     * Priority order is intentional:
     *   info/contact/hello → most commonly used company emails
     *   hr/careers/jobs    → specifically useful for job outreach
     */
    private function guessEmailsByPattern(string $domain): array
    {
        // First: confirm the domain has MX records at all
        if (!$this->domainHasMxRecords($domain)) {
            Log::debug("ContactExtractor: {$domain} has no MX records — skipping pattern guesser.");
            return [];
        }

        // Return pattern candidates — DO NOT verify each address individually
        // (that would require SMTP probing which is itself flagged as suspicious)
        // We return the top 3 most common patterns as "probable" addresses.
        $guessed = [];
        $topPrefixes = array_slice($this->commonPrefixes, 0, 3); // info, contact, hello

        foreach ($topPrefixes as $prefix) {
            $guessed[] = "{$prefix}@{$domain}";
        }

        return $guessed;
    }

    private function domainHasMxRecords(string $domain): bool
    {
        try {
            $records = dns_get_record($domain, DNS_MX);
            return !empty($records);
        } catch (\Exception) {
            return false;
        }
    }

    // =========================================================================
    // JS DETECTION
    // =========================================================================

    /**
     * Detect if a homepage is a JS-rendered SPA shell.
     *
     * Heuristics (any one match = JS-heavy):
     *   - Very short visible text (< 200 chars after stripping tags)
     *   - Contains known SPA mount points
     *   - Contains JS framework fingerprints in source
     */
    private function detectJsRendered(string $html): bool
    {
        $lowerHtml = strtolower($html);

        // SPA mount point divs
        $spaMounts = ['id="root"', 'id="app"', 'id="__next"', 'id="nuxt"', 'id="gatsby-focus-wrapper"'];
        foreach ($spaMounts as $mount) {
            if (str_contains($lowerHtml, $mount)) {
                return true;
            }
        }

        // Framework fingerprints in source
        $jsFrameworks = [
            '_next/static',        // Next.js
            '__nuxt',              // Nuxt
            'gatsby-',             // Gatsby
            'data-reactroot',      // React
            'ng-version',          // Angular
            'vue-app',             // Vue
            'svelte',              // Svelte
        ];
        foreach ($jsFrameworks as $fp) {
            if (str_contains($lowerHtml, $fp)) {
                return true;
            }
        }

        // Very little visible text = JS probably renders it
        $visibleText = trim(strip_tags($html));
        if (strlen($visibleText) < 150) {
            return true;
        }

        return false;
    }

    // =========================================================================
    // CLEANUP
    // =========================================================================

    private function cleanEmails(array $emails): array
    {
        $cleaned = [];
        foreach ($emails as $email) {
            $email = strtolower(trim($email));

            // Reject blacklisted domains
            $blacklisted = false;
            foreach ($this->emailBlacklist as $blocked) {
                if (str_contains($email, $blocked)) {
                    $blacklisted = true;
                    break;
                }
            }
            if ($blacklisted) {
                continue;
            }

            // Reject image-filename false positives like avatar@2x.png
            if (preg_match('/@\d+x\b/i', $email)) {
                continue;
            }

            // Basic format check
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $cleaned[] = $email;
        }

        return array_values(array_unique($cleaned));
    }

    private function cleanPhones(array $phones): array
    {
        $cleaned = [];
        foreach ($phones as $phone) {
            $phone = preg_replace('/[^\d+]/', '', trim($phone));

            // Must be 8–16 characters after stripping non-digits (allows + prefix)
            $digits = preg_replace('/\D/', '', $phone);
            if (strlen($digits) < 8 || strlen($digits) > 15) {
                continue;
            }

            $cleaned[] = $phone;
        }

        return array_values(array_unique($cleaned));
    }

    private function extractDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }
        // Strip www.
        return preg_replace('/^www\./i', '', $host);
    }
}
