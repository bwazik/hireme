<?php

namespace App\Services\Discovery;

use App\Jobs\Discovery\EnrichCompanyJob;
use App\Models\Company;
use App\Models\Contact;

class LeadProcessor
{
    /**
     * Normalise a raw domain string like 'https://www.example.com/page' → 'example.com'
     */
    public function normalizeDomain(string $url): string
    {
        $host = parse_url(trim($url), PHP_URL_HOST) ?? $url;

        return strtolower(preg_replace('/^www\./', '', $host));
    }

    /**
     * Normalise a phone number to E.164 format.
     * countryCode: 'EG' → prefix '20', 'DE' → '49', 'US' → '1', etc.
     */
    public function normalizePhone(string $rawPhone, string $countryCode = 'EG'): ?string
    {
        // Strip all non-digit characters except leading +
        $digits = preg_replace('/[^\d+]/', '', $rawPhone);

        // Normalize leading 00 to +
        if (str_starts_with($digits, '00')) {
            $digits = '+' . substr($digits, 2);
        }

        if (strtoupper($countryCode) === 'EG') {
            // Strip explicit country codes
            if (str_starts_with($digits, '+20')) {
                $digits = substr($digits, 3);
            } elseif (str_starts_with($digits, '0201')) {
                // Mistyped 0020 as 020
                $digits = substr($digits, 3);
            }

            // Remove leading 0
            if (str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            }

            // Handle numbers that include the country code without + or 00 (e.g. 201207865714)
            if (strlen($digits) === 12 && str_starts_with($digits, '201')) {
                $digits = substr($digits, 2);
            }

            // Valid mobile in Egypt: 10 digits starting with 10, 11, 12, or 15
            if (strlen($digits) === 10 && preg_match('/^1[0125]/', $digits)) {
                return '+20' . $digits;
            }

            // Valid landline in Egypt: 8 or 9 digits
            if (strlen($digits) >= 8 && strlen($digits) <= 9) {
                return '+20' . $digits;
            }

            // Invalid or malformed number
            return null;
        }

        // For other countries, fallback to basic logic
        if (str_starts_with($digits, '+')) {
            return strlen($digits) >= 10 ? $digits : null;
        }

        $prefixMap = [
            'DE' => '49', 'US' => '1', 'CH' => '41', 
            'GB' => '44', 'AE' => '971',
        ];

        $prefix = $prefixMap[strtoupper($countryCode)] ?? null;

        if (! $prefix) {
            return null;
        }

        $e164 = '+' . $prefix . $digits;

        return strlen($e164) >= 10 ? $e164 : null;
    }

    /**
     * Auto-classify industries from a text description.
     * @return string[]
     */
    public function classifyIndustry(string $text): array
    {
        $text = strtolower($text);
        $found = [];

        $rules = [
            'ai_company'     => ['ai', 'artificial intelligence', 'machine learning', ' ml ', 'deep learning', 'neural', 'llm', 'gpt'],
            'saas'           => ['saas', 'platform', 'subscription', 'cloud-based', 'software as a service'],
            'mobile_dev'     => ['mobile', 'ios', 'android', ' app ', 'flutter', 'react native', 'swift', 'kotlin'],
            'fintech'        => ['fintech', 'banking', 'payment', 'wallet', 'crypto', 'blockchain', 'financial technology'],
            'e_commerce'     => ['shopify', 'woocommerce', 'magento', 'e-commerce', 'online store', 'retail tech'],
            'digital_agency' => ['digital marketing', 'seo', 'social media', 'branding', 'advertising', 'marketing agency'],
            'web_design'     => ['web design', 'ui/ux', 'creative', 'design agency', 'frontend', 'user experience', 'user interface'],
            'software_house' => ['software', 'development', 'tech', ' it ', 'backend', 'fullstack', 'consulting', 'system integrator', 'programming'],
        ];

        foreach ($rules as $label => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) {
                    $found[] = $label;
                    break; // Move to next rule once one keyword matches
                }
            }
        }

        return ! empty($found) ? array_unique($found) : ['other'];
    }

    /**
     * Process a single company+contact payload and save to DB (with dedup).
     *
     * @param  array{name:string, website:?string, country:?string, city:?string, description:?string, source:string, source_url:?string}  $companyData
     * @param  array{email:?string, phone:?string, name:?string, role:?string, source:string}  $contactData
     * @param  string  $countryCode  Used for phone normalisation.
     * @return array{company:Company|null, contact:Contact|null, skipped:bool, reason:string}
     */
    public function process(array $companyData, array $contactData, string $countryCode = 'EG'): array
    {
        // ── Company dedup ──────────────────────────────────────────────────
        $domain = null;

        if (! empty($companyData['website'])) {
            $domain = $this->normalizeDomain($companyData['website']);
        }

        if ($domain && Company::where('website_domain', $domain)->exists()) {
            return ['company' => null, 'contact' => null, 'skipped' => true, 'reason' => 'duplicate_domain'];
        }

        // Classify industry from description
        $industry = $this->classifyIndustry($companyData['description'] ?? $companyData['name'] ?? '');

        $company = Company::create([
            'name'           => $companyData['name'],
            'industry'       => $industry,
            'country'        => $companyData['country'] ?? null,
            'city'           => $companyData['city'] ?? null,
            'website'        => $companyData['website'] ?? null,
            'website_domain' => $domain,
            'description'    => $companyData['description'] ?? null,
            'source'         => $companyData['source'],
            'source_url'     => $companyData['source_url'] ?? null,
        ]);

        // ── Contact dedup ──────────────────────────────────────────────────
        $email      = ! empty($contactData['email']) ? strtolower(trim($contactData['email'])) : null;
        $phoneRaw   = $contactData['phone'] ?? null;
        $phoneE164  = $phoneRaw ? $this->normalizePhone($phoneRaw, $countryCode) : null;

        if ($email && Contact::where('email', $email)->exists()) {
            return ['company' => $company, 'contact' => null, 'skipped' => true, 'reason' => 'duplicate_email'];
        }

        if ($phoneE164 && Contact::where('phone', $phoneE164)->exists()) {
            return ['company' => $company, 'contact' => null, 'skipped' => true, 'reason' => 'duplicate_phone'];
        }

        $contact = null;
        $savedAny = false;

        if ($email || $phoneE164) {
            $contact = Contact::create([
                'company_id' => $company->id,
                'name'       => $contactData['name'] ?? null,
                'role'       => $contactData['role'] ?? null,
                'email'      => $email,
                'phone_raw'  => $phoneRaw,
                'phone'      => $phoneE164,
                'is_whatsapp'=> ! empty($phoneE164),
                'source'     => $contactData['source'],
                'source_url' => $contactData['source_url'] ?? null,
            ]);
            $savedAny = true;
        }

        if (!$savedAny) {
            $company->update([
                'contact_status' => $company->website ? 'pending_enrichment' : 'no_website',
            ]);

            EnrichCompanyJob::dispatch($company)
                ->onQueue('discovery')
                ->delay(now()->addSeconds(rand(30, 90)));
        } else {
            $company->update(['contact_status' => 'has_contacts']);
        }

        return ['company' => $company, 'contact' => $contact, 'skipped' => false, 'reason' => 'created'];
    }
}
