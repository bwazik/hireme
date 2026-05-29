<?php

namespace App\Services\Discovery\Sources;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerperMapsSource
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) (SystemSetting::get('serper_api_key') ?? config('services.serper.key') ?? '');
    }

    /**
     * Search Google Maps via Serper.dev.
     *
     * @return array  Array of place objects: [{title, address, website, phone, ...}]
     */
    public function search(string $query, string $location, string $countryCode): array
    {
        if (empty($this->apiKey)) {
            Log::warning('SerperMapsSource: no API key configured');

            return [];
        }

        try {
            $response = Http::timeout(15)->withHeaders([
                'X-API-KEY'    => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://google.serper.dev/maps', [
                'q'        => $query,
                'location' => $location,
                'gl'       => strtolower($countryCode),
                'num'      => 20,
            ]);

            // Increment usage counter
            $used = (int) SystemSetting::get('serper_queries_used', 0);
            SystemSetting::set('serper_queries_used', $used + 1);

            return $response->json('places', []);
        } catch (\Exception $e) {
            Log::error('SerperMapsSource error: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Pre-defined queries to rotate through daily.
     * Managed in CONSTANTS.md → Section 10.
     */
    public static function defaultQueries(): array
    {
        return [
            // Egypt — Cairo
            ['query' => 'software development company', 'location' => 'Cairo, Egypt',        'country' => 'EG'],
            ['query' => 'software house',               'location' => 'Cairo, Egypt',        'country' => 'EG'],
            ['query' => 'web development company',      'location' => 'Cairo, Egypt',        'country' => 'EG'],
            ['query' => 'IT company',                   'location' => 'Cairo, Egypt',        'country' => 'EG'],
            ['query' => 'SaaS company',                 'location' => 'Cairo, Egypt',        'country' => 'EG'],
            ['query' => 'tech startup',                 'location' => 'Cairo, Egypt',        'country' => 'EG'],
            ['query' => 'digital marketing agency',     'location' => 'Cairo, Egypt',        'country' => 'EG'],
            // Egypt — Alexandria
            ['query' => 'software company',             'location' => 'Alexandria, Egypt',   'country' => 'EG'],
            ['query' => 'web development',              'location' => 'Alexandria, Egypt',   'country' => 'EG'],
            ['query' => 'digital marketing agency',     'location' => 'Alexandria, Egypt',   'country' => 'EG'],
            // Germany
            ['query' => 'software development company', 'location' => 'Berlin, Germany',     'country' => 'DE'],
            ['query' => 'web agency',                   'location' => 'Munich, Germany',     'country' => 'DE'],
            ['query' => 'software house',               'location' => 'Hamburg, Germany',    'country' => 'DE'],
            ['query' => 'digital marketing agency',     'location' => 'Berlin, Germany',     'country' => 'DE'],
            // USA
            ['query' => 'software company',             'location' => 'New York, USA',       'country' => 'US'],
            ['query' => 'web development agency',       'location' => 'San Francisco, USA',  'country' => 'US'],
            ['query' => 'digital marketing agency',     'location' => 'New York, USA',       'country' => 'US'],
            // Switzerland
            ['query' => 'software company',             'location' => 'Zurich, Switzerland', 'country' => 'CH'],
            ['query' => 'digital marketing agency',     'location' => 'Zurich, Switzerland', 'country' => 'CH'],
            // Canada
            ['query' => 'software company',             'location' => 'Toronto, Canada',     'country' => 'CA'],
            ['query' => 'web development agency',       'location' => 'Vancouver, Canada',   'country' => 'CA'],
            ['query' => 'digital marketing agency',     'location' => 'Toronto, Canada',     'country' => 'CA'],
            // Australia
            ['query' => 'software company',             'location' => 'Sydney, Australia',   'country' => 'AU'],
            ['query' => 'web development agency',       'location' => 'Melbourne, Australia', 'country' => 'AU'],
            ['query' => 'digital marketing agency',     'location' => 'Sydney, Australia',   'country' => 'AU'],
            // United Kingdom
            ['query' => 'software company',             'location' => 'London, UK',          'country' => 'GB'],
            ['query' => 'web development agency',       'location' => 'London, UK',          'country' => 'GB'],
            ['query' => 'digital marketing agency',     'location' => 'Manchester, UK',    'country' => 'GB'],
            // Netherlands
            ['query' => 'software company',             'location' => 'Amsterdam, NL',       'country' => 'NL'],
            ['query' => 'web development agency',       'location' => 'Amsterdam, NL',       'country' => 'NL'],
            ['query' => 'digital marketing agency',     'location' => 'Rotterdam, NL',       'country' => 'NL'],
            // UAE
            ['query' => 'software company',             'location' => 'Dubai, UAE',          'country' => 'AE'],
            ['query' => 'web development agency',       'location' => 'Dubai, UAE',          'country' => 'AE'],
            ['query' => 'digital marketing agency',     'location' => 'Abu Dhabi, UAE',      'country' => 'AE'],
            // Singapore
            ['query' => 'software company',             'location' => 'Singapore, SG',       'country' => 'SG'],
            ['query' => 'web development agency',       'location' => 'Singapore, SG',       'country' => 'SG'],
            ['query' => 'digital marketing agency',     'location' => 'Singapore, SG',       'country' => 'SG'],
            // Poland
            ['query' => 'software company',             'location' => 'Warsaw, Poland',      'country' => 'PL'],
            ['query' => 'web development agency',       'location' => 'Krakow, Poland',      'country' => 'PL'],
            ['query' => 'digital marketing agency',     'location' => 'Warsaw, Poland',      'country' => 'PL'],
            // Spain
            ['query' => 'software company',             'location' => 'Madrid, Spain',       'country' => 'ES'],
            ['query' => 'web development agency',       'location' => 'Barcelona, Spain',    'country' => 'ES'],
            ['query' => 'digital marketing agency',     'location' => 'Madrid, Spain',       'country' => 'ES'],
        ];
    }
}
