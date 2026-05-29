<?php

namespace App\Services\Discovery\Extractors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MetaExtractor
 *
 * Visits a company website and pulls structured context:
 *   - Meta description (what Google shows in search results)
 *   - Page title
 *   - First meaningful paragraph from homepage
 *   - About page summary
 */
class MetaExtractor
{
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
    ];

    /**
     * Extract company context from their website.
     *
     * @return array{
     *   description: string|null,
     *   title:       string|null,
     *   raw_about:   string|null,
     * }
     */
    public function extract(string $websiteUrl): array
    {
        $websiteUrl = rtrim($websiteUrl, '/');
        $userAgent  = $this->userAgents[array_rand($this->userAgents)];

        $description = null;
        $title       = null;
        $rawAbout    = null;

        // ── Step 1: Homepage ──────────────────────────────────────────────────
        $homeHtml = $this->fetch($websiteUrl, $userAgent);

        if ($homeHtml) {
            $title       = $this->extractTitle($homeHtml);
            $metaDesc    = $this->extractMetaDescription($homeHtml);
            $ogDesc      = $this->extractOpenGraphDescription($homeHtml);
            $firstPara   = $this->extractFirstMeaningfulParagraph($homeHtml);

            // Priority: meta description > OG description > first paragraph
            $description = $metaDesc ?? $ogDesc ?? $firstPara;
        }

        // ── Step 2: About page (if homepage gave nothing useful) ──────────────
        if (!$description || strlen($description) < 40) {
            $aboutUrls = [
                $websiteUrl . '/about',
                $websiteUrl . '/about-us',
                $websiteUrl . '/who-we-are',
                $websiteUrl . '/company',
            ];

            foreach ($aboutUrls as $aboutUrl) {
                $aboutHtml = $this->fetch($aboutUrl, $userAgent);
                if (!$aboutHtml) {
                    continue;
                }

                $aboutMeta  = $this->extractMetaDescription($aboutHtml);
                $aboutPara  = $this->extractFirstMeaningfulParagraph($aboutHtml);
                $rawAbout   = $aboutPara;

                // Prefer the about page paragraph — it's usually richer than meta
                $description = $aboutPara ?? $aboutMeta ?? $description;

                if ($description && strlen($description) >= 40) {
                    break; // good enough — stop checking more about pages
                }
            }
        }

        // ── Step 3: Sanitise and cap length ──────────────────────────────────
        if ($description) {
            $description = $this->sanitise($description);
            // Cap at 400 chars — enough for the AI, not so much it dominates the prompt
            if (mb_strlen($description, 'UTF-8') > 400) {
                $description = mb_substr($description, 0, 397, 'UTF-8') . '...';
            }
        }

        return [
            'description' => $description ?: null,
            'title'       => $title ? $this->sanitise($title) : null,
            'raw_about'   => $rawAbout ? $this->sanitise($rawAbout) : null,
        ];
    }

    // =========================================================================
    // EXTRACTION METHODS
    // =========================================================================

    private function extractMetaDescription(string $html): ?string
    {
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']{20,})["\'][^>]*>/i', $html, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']{20,})["\'][^>]+name=["\']description["\'][^>]*>/i', $html, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractOpenGraphDescription(string $html): ?string
    {
        if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']{20,})["\'][^>]*>/i', $html, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']{20,})["\'][^>]+property=["\']og:description["\'][^>]*>/i', $html, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>([^<]{3,100})<\/title>/i', $html, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractFirstMeaningfulParagraph(string $html): ?string
    {
        $html = preg_replace('/<(nav|header|footer|script|style)[^>]*>.*?<\/\1>/is', '', $html);
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches);

        $skipPatterns = [
            '/^cookie/i',
            '/^we use cookies/i',
            '/^all rights reserved/i',
            '/^©/',
            '/^privacy policy/i',
            '/^terms/i',
            '/^\s*$/',
        ];

        foreach ($matches[1] ?? [] as $rawP) {
            $text = trim(strip_tags($rawP));
            if (strlen($text) < 60) {
                continue;
            }

            $skip = false;
            foreach ($skipPatterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            return $text;
        }

        return null;
    }

    // =========================================================================
    // HTTP
    // =========================================================================

    private function fetch(string $url, string $userAgent): ?string
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => $userAgent])
                ->get($url);

            return $response->successful() ? $response->body() : null;

        } catch (\Exception $e) {
            Log::debug("MetaExtractor: failed to fetch {$url} — " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function sanitise(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
