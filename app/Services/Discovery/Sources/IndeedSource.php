<?php

namespace App\Services\Discovery\Sources;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class IndeedSource
{
    // User-agent strings from CONSTANTS.md §8
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
    ];

    /**
     * Scrape Indeed for job listings → extract company names.
     *
     * @return array  [{company, location, link}]
     */
    public function scrape(string $query, string $location): array
    {
        $url = 'https://www.indeed.com/jobs?' . http_build_query([
            'q'    => $query,
            'l'    => $location,
            'sort' => 'date',
        ]);

        try {
            $userAgent = $this->userAgents[array_rand($this->userAgents)];
            $html = Http::timeout(15)->withHeaders([
                'User-Agent'      => $userAgent,
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->get($url)->body();

            $crawler = new Crawler($html);
            $results = [];

            $crawler->filter('.job_seen_beacon')->each(function (Crawler $node) use (&$results) {
                try {
                    $results[] = [
                        'company'  => $node->filter('.companyName')->text(''),
                        'location' => $node->filter('.companyLocation')->text(''),
                        'link'     => $node->filter('a.jcs-JobTitle')->attr('href', ''),
                    ];
                } catch (\Exception) {
                    // Skip malformed nodes
                }
            });

            return array_filter($results, fn ($r) => ! empty($r['company']));
        } catch (\Exception $e) {
            Log::warning('IndeedSource scrape failed: ' . $e->getMessage());

            return [];
        }
    }

    public static function defaultQueries(): array
    {
        return [
            ['query' => 'backend developer',  'location' => 'Cairo, Egypt'],
            ['query' => 'Laravel developer',  'location' => 'Cairo, Egypt'],
            ['query' => 'PHP developer',      'location' => 'Cairo, Egypt'],
            ['query' => 'software developer', 'location' => 'Remote'],
        ];
    }
}
