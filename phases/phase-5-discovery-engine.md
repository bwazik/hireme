# Phase 5 — Discovery Engine
**Timeline**: Day 17–22 | **Goal**: System autonomously discovers 50+ new software companies/day from Serper.dev + website scraping, with no manual input.

> 📌 **Reference [`CONSTANTS.md`](./CONSTANTS.md)** for: Serper API details (§5.2), default discovery queries (§10), user-agent strings (§8), email blacklist (§9), industry classification (§14), source values (§15), scheduler times (§11).

> **Prerequisite**: Phase 4 complete — full campaign pipeline runs end-to-end.

---

## Context

The Discovery Engine finds new companies from external sources and feeds them into the Lead store. Architecture:

```
Scheduler → RunDiscoveryJob → SerperMapsSource / IndeedSource
                            → ContactExtractor (visits websites)
                            → LeadProcessor (dedup + save)
                            → companies + contacts tables
```

**Sources**:
- **Serper.dev Maps** (primary, API-based, reliable) — uses `POST https://google.serper.dev/maps`
- **Serper.dev Web Search** (secondary) — uses `POST https://google.serper.dev/search`
- **Indeed job board** (scraper — HTML parsing, fragile) — extracts hiring companies
- **Company website scraper** — visits company sites, extracts emails/phones

**Serper free tier**: 2,500 total searches. At 15/day → ~166 days of free discovery.

---

## Step 1 — Directory Structure

```
app/Services/Discovery/
├── DiscoveryOrchestrator.php
├── Sources/
│   ├── SerperMapsSource.php
│   ├── SerperSearchSource.php
│   └── IndeedSource.php
├── Extractors/
│   ├── ContactExtractor.php
│   └── MetaExtractor.php
└── LeadProcessor.php          ← Already created in Phase 1
```

---

## Step 2 — SerperMapsSource.php

`app/Services/Discovery/Sources/SerperMapsSource.php`:

```php
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
        $this->apiKey = SystemSetting::get('serper_api_key')
            ?: config('services.serper.key', '');
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
     * ⚠️  MANAGED IN CONSTANTS.md → Section 10.
     * Copy the full array from there so queries are only maintained in one place.
     */
    public static function defaultQueries(): array
    {
        // Paste the $queries array from CONSTANTS.md §10 here.
        return [];
    }
}
```

---

## Step 3 — SerperSearchSource.php

`app/Services/Discovery/Sources/SerperSearchSource.php`:

```php
<?php

namespace App\Services\Discovery\Sources;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerperSearchSource
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = SystemSetting::get('serper_api_key')
            ?: config('services.serper.key', '');
    }

    /**
     * Search Google Web via Serper.dev.
     *
     * @return array  Array of organic results: [{title, link, snippet, ...}]
     */
    public function search(string $query, string $countryCode): array
    {
        if (empty($this->apiKey)) {
            return [];
        }

        try {
            $response = Http::timeout(15)->withHeaders([
                'X-API-KEY'    => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://google.serper.dev/search', [
                'q'   => $query,
                'gl'  => strtolower($countryCode),
                'num' => 10,
            ]);

            $used = (int) SystemSetting::get('serper_queries_used', 0);
            SystemSetting::set('serper_queries_used', $used + 1);

            return $response->json('organic', []);
        } catch (\Exception $e) {
            Log::error('SerperSearchSource error: ' . $e->getMessage());
            return [];
        }
    }

    public static function defaultQueries(): array
    {
        return [
            ['query' => 'software development company Cairo email contact site:eg', 'country' => 'EG'],
            ['query' => 'web agency Egypt hire Laravel developer',                  'country' => 'EG'],
            ['query' => 'software house Germany "backend developer"',               'country' => 'DE'],
        ];
    }
}
```

---

## Step 4 — IndeedSource.php

`app/Services/Discovery/Sources/IndeedSource.php`:

> **Warning**: Indeed frequently changes their HTML. This scraper may break. Always wrap in try/catch.

```php
<?php

namespace App\Services\Discovery\Sources;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class IndeedSource
{
    // ⚠️  User-agent strings are managed in CONSTANTS.md → Section 8.
    // Copy the $userAgents array from there.
    private array $userAgents = [
        // Paste from CONSTANTS.md §8
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
            ['query' => 'Laravel developer',   'location' => 'Cairo, Egypt'],
            ['query' => 'PHP developer',       'location' => 'Cairo, Egypt'],
            ['query' => 'software developer',  'location' => 'Remote'],
        ];
    }
}
```

---

## Step 5 — MetaExtractor.php

`app/Services/Discovery/Extractors/MetaExtractor.php`:

```php
<?php

namespace App\Services\Discovery\Extractors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class MetaExtractor
{
    /**
     * Extract company description from a website's meta tags.
     */
    public function extract(string $websiteUrl): ?string
    {
        try {
            $html = Http::timeout(10)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; OutreachBot/1.0)',
            ])->get($websiteUrl)->body();

            $crawler = new Crawler($html);

            // Try meta description first
            $meta = $crawler->filterXPath('//meta[@name="description"]');
            if ($meta->count() > 0) {
                return substr(trim($meta->attr('content') ?? ''), 0, 500);
            }

            // Try og:description
            $og = $crawler->filterXPath('//meta[@property="og:description"]');
            if ($og->count() > 0) {
                return substr(trim($og->attr('content') ?? ''), 0, 500);
            }

            return null;
        } catch (\Exception $e) {
            Log::debug("MetaExtractor failed for {$websiteUrl}: " . $e->getMessage());
            return null;
        }
    }
}
```

---

## Step 6 — ContactExtractor.php

`app/Services/Discovery/Extractors/ContactExtractor.php`:

```php
<?php

namespace App\Services\Discovery\Extractors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContactExtractor
{
    // ⚠️  Blacklist is managed in CONSTANTS.md → Section 9.
    // Copy the $emailBlacklist array from there and keep both in sync.
    private array $emailBlacklist = [
        // Paste from CONSTANTS.md §9
    ];

    /**
     * Visit a company website and extract emails and phone numbers.
     *
     * @return array{emails: string[], phones: string[]}
     */
    public function extract(string $websiteUrl): array
    {
        $websiteUrl  = rtrim($websiteUrl, '/');
        $emails      = [];
        $phones      = [];

        $pagesToCheck = [
            $websiteUrl,
            $websiteUrl . '/contact',
            $websiteUrl . '/contact-us',
            $websiteUrl . '/about',
            $websiteUrl . '/careers',
            $websiteUrl . '/jobs',
        ];

        foreach ($pagesToCheck as $pageUrl) {
            try {
                $html = Http::timeout(8)->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])->get($pageUrl)->body();

                // Extract emails
                preg_match_all(
                    '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
                    $html,
                    $emailMatches
                );
                $emails = array_merge($emails, $emailMatches[0]);

                // Extract phones
                preg_match_all(
                    '/(?:\+?[\d\s\-\(\)\.]{7,20})/',
                    $html,
                    $phoneMatches
                );
                $phones = array_merge($phones, $phoneMatches[0]);

            } catch (\Exception) {
                continue; // Page not accessible — skip
            }
        }

        // Filter emails
        $emails = array_filter($emails, function (string $email) {
            foreach ($this->emailBlacklist as $blocked) {
                if (str_contains(strtolower($email), $blocked)) {
                    return false;
                }
            }
            // Filter image filenames like @2x, @3x
            if (preg_match('/@\d+x/i', $email)) {
                return false;
            }
            return true;
        });

        return [
            'emails' => array_values(array_unique($emails)),
            'phones' => array_values(array_unique(array_filter($phones, fn ($p) => strlen(trim($p)) >= 7))),
        ];
    }
}
```

---

## Step 7 — DiscoveryOrchestrator.php

`app/Services/Discovery/DiscoveryOrchestrator.php`:

```php
<?php

namespace App\Services\Discovery;

use App\Models\ScrapingJob;
use App\Models\ScrapingResult;
use App\Services\Discovery\Extractors\ContactExtractor;
use App\Services\Discovery\Extractors\MetaExtractor;
use App\Services\Discovery\Sources\SerperMapsSource;
use App\Services\Discovery\Sources\SerperSearchSource;
use Illuminate\Support\Facades\Log;

class DiscoveryOrchestrator
{
    public function __construct(
        private readonly SerperMapsSource  $mapsSource    = new SerperMapsSource(),
        private readonly SerperSearchSource $searchSource  = new SerperSearchSource(),
        private readonly ContactExtractor  $contactExtractor = new ContactExtractor(),
        private readonly MetaExtractor     $metaExtractor = new MetaExtractor(),
        private readonly LeadProcessor     $leadProcessor = new LeadProcessor(),
    ) {}

    /**
     * Run all Serper Maps queries and save results to scraping_results.
     */
    public function runMapsDiscovery(): void
    {
        foreach (SerperMapsSource::defaultQueries() as $queryConfig) {
            $scrapingJob = ScrapingJob::create([
                'source'       => 'serper_maps',
                'query'        => $queryConfig['query'],
                'location'     => $queryConfig['location'],
                'country_code' => $queryConfig['country'],
                'status'       => 'running',
                'started_at'   => now(),
                'created_at'   => now(),
            ]);

            try {
                $places = $this->mapsSource->search(
                    $queryConfig['query'],
                    $queryConfig['location'],
                    $queryConfig['country']
                );

                $newLeads   = 0;
                $duplicates = 0;

                foreach ($places as $place) {
                    $website = $place['website'] ?? null;

                    // Save raw result
                    ScrapingResult::create([
                        'scraping_job_id' => $scrapingJob->id,
                        'raw_data'        => $place,
                        'company_name'    => $place['title'] ?? null,
                        'website'         => $website,
                        'phone'           => $place['phone'] ?? null,
                        'location'        => $place['address'] ?? null,
                        'created_at'      => now(),
                    ]);

                    // Extract contact info from website
                    $contactInfo = $website ? $this->contactExtractor->extract($website) : ['emails' => [], 'phones' => []];
                    $description = $website ? $this->metaExtractor->extract($website) : null;

                    $email = $contactInfo['emails'][0] ?? null;
                    $phone = $contactInfo['phones'][0] ?? ($place['phone'] ?? null);

                    $result = $this->leadProcessor->process(
                        companyData: [
                            'name'        => $place['title'] ?? 'Unknown',
                            'website'     => $website,
                            'country'     => $queryConfig['country'],
                            'city'        => $queryConfig['location'],
                            'description' => $description,
                            'source'      => 'google_maps',
                            'source_url'  => null,
                        ],
                        contactData: [
                            'email'      => $email,
                            'phone'      => $phone,
                            'source'     => 'google_maps',
                            'source_url' => $website,
                        ],
                        countryCode: $queryConfig['country']
                    );

                    if ($result['skipped']) {
                        $duplicates++;
                    } else {
                        $newLeads++;
                    }
                }

                $scrapingJob->update([
                    'status'        => 'completed',
                    'results_found' => count($places),
                    'new_leads'     => $newLeads,
                    'duplicates'    => $duplicates,
                    'completed_at'  => now(),
                ]);

            } catch (\Exception $e) {
                $scrapingJob->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at'  => now(),
                ]);
                Log::error("DiscoveryOrchestrator Maps failed for query '{$queryConfig['query']}': " . $e->getMessage());
            }

            // Polite delay between queries
            sleep(rand(2, 5));
        }
    }
}
```

---

## Step 8 — RunDiscoveryJob

```bash
php artisan make:job RunDiscoveryJob
```

`app/Jobs/Discovery/RunDiscoveryJob.php`:

```php
<?php

namespace App\Jobs\Discovery;

use App\Models\SystemSetting;
use App\Services\Discovery\DiscoveryOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunDiscoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600; // 10 minutes

    public function __construct()
    {
        $this->onQueue('discovery');
    }

    public function handle(): void
    {
        if (SystemSetting::get('discovery_enabled', 'true') !== 'true') {
            Log::info('RunDiscoveryJob: discovery is disabled. Skipping.');
            return;
        }

        Log::info('RunDiscoveryJob: starting discovery run...');
        (new DiscoveryOrchestrator())->runMapsDiscovery();
        Log::info('RunDiscoveryJob: complete.');
    }
}
```

---

## Step 9 — Update Scheduler

Add to `routes/console.php`:

```php
use App\Jobs\Discovery\RunDiscoveryJob;
use App\Jobs\Maintenance\CleanScrapeResultsJob;

// Discovery runs every morning at 7 AM
Schedule::job(new RunDiscoveryJob())->dailyAt('07:00');

// Weekly cleanup: purge processed scraping_results older than 30 days
Schedule::job(new CleanScrapeResultsJob())->weekly();
```

Create `CleanScrapeResultsJob`:

```bash
php artisan make:job CleanScrapeResultsJob
```

```php
<?php

namespace App\Jobs\Maintenance;

use App\Models\ScrapingResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanScrapeResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        ScrapingResult::where('processed', true)
            ->where('created_at', '<', now()->subDays(30))
            ->delete();
    }
}
```

---

## Step 10 — ScrapingJobResource in Filament

```bash
php artisan make:filament-resource ScrapingJob --generate
```

Make it **read-only**. Table columns:

```php
Tables\Columns\BadgeColumn::make('source'),
Tables\Columns\TextColumn::make('query')->limit(40),
Tables\Columns\TextColumn::make('location'),
Tables\Columns\BadgeColumn::make('status')
    ->colors([
        'warning' => 'running',
        'success' => 'completed',
        'danger'  => 'failed',
    ]),
Tables\Columns\TextColumn::make('results_found')->label('Found'),
Tables\Columns\TextColumn::make('new_leads')->label('New'),
Tables\Columns\TextColumn::make('duplicates')->label('Dups'),
Tables\Columns\TextColumn::make('error_message')->limit(50)->toggleable(),
Tables\Columns\TextColumn::make('completed_at')->dateTime()->sortable(),
```

---

## Step 11 — Store Serper API Key

```bash
php artisan tinker --execute '\App\Models\SystemSetting::set("serper_api_key", "YOUR_KEY_HERE");'
```

Get a free key at: https://serper.dev (2,500 searches free, no credit card)

---

## Step 12 — Test Discovery Manually

```bash
php artisan tinker --execute '
\App\Jobs\Discovery\RunDiscoveryJob::dispatch();
echo "Dispatched. Run queue:work to process.";
'
```

Then check `scraping_jobs` and `companies` tables:

```bash
php artisan tinker --execute '
echo "ScrapingJobs: " . \App\Models\ScrapingJob::count() . "\n";
echo "Companies: " . \App\Models\Company::count() . "\n";
echo "Contacts: " . \App\Models\Contact::count() . "\n";
'
```

---

## Deliverable

- [ ] `SerperMapsSource`, `SerperSearchSource`, `IndeedSource` created
- [ ] `ContactExtractor`, `MetaExtractor` created
- [ ] `DiscoveryOrchestrator` created and orchestrates the full flow
- [ ] `RunDiscoveryJob` dispatches to `discovery` queue
- [ ] `ScrapingJobResource` visible in Filament showing discovery history
- [ ] Scheduler updated with `dailyAt('07:00')` for RunDiscoveryJob
- [ ] Running discovery manually adds real companies to DB
- [ ] `serper_queries_used` counter increments in system_settings

**Next**: Phase 6 — Polish & Monitoring (Reply inbox, settings page, error alerting, CSV export)
