<?php

namespace App\Services\Discovery;

use App\Models\ScrapingJob;
use App\Models\ScrapingResult;
use App\Models\SystemSetting;
use App\Services\Discovery\Extractors\ContactExtractor;
use App\Services\Discovery\Extractors\MetaExtractor;
use App\Services\Discovery\Sources\SerperMapsSource;
use App\Services\Discovery\Sources\SerperSearchSource;
use Illuminate\Support\Facades\Log;

class DiscoveryOrchestrator
{
    public function __construct(
        private readonly SerperMapsSource $mapsSource = new SerperMapsSource(),
        private readonly SerperSearchSource $searchSource = new SerperSearchSource(),
        private readonly ContactExtractor $contactExtractor = new ContactExtractor(),
        private readonly MetaExtractor $metaExtractor = new MetaExtractor(),
        private readonly LeadProcessor $leadProcessor = new LeadProcessor(),
    ) {}

    /**
     * Run a rotating batch of Maps queries (15/day).
     * Offset is persisted in system_settings so each daily run picks up where it left off,
     * cycling through all queries every ~3 days.
     */
    public function runMapsDiscovery(): void
    {
        $allQueries  = SerperMapsSource::defaultQueries();
        $totalCount  = count($allQueries);
        $batchSize   = 11;

        $offset = (int) SystemSetting::get('discovery_query_offset', 0);

        // Wrap offset if it has gone past the end
        if ($offset >= $totalCount) {
            $offset = 0;
        }

        // Slice the batch — wrapping around if near the end
        $batch = array_slice($allQueries, $offset, $batchSize);

        // If we hit the end mid-batch, wrap and append from the beginning
        if (count($batch) < $batchSize) {
            $remaining = $batchSize - count($batch);
            $batch     = array_merge($batch, array_slice($allQueries, 0, $remaining));
        }

        // Advance offset for the next run
        $nextOffset = ($offset + $batchSize) % $totalCount;
        SystemSetting::set('discovery_query_offset', $nextOffset);

        Log::info("DiscoveryOrchestrator: running Maps batch offset={$offset}, count=" . count($batch) . "/{$totalCount}");

        $this->executeMapsBatch($batch);
    }

    /**
     * Run Serper web-search queries and feed organic results into leads.
     * Organic results provide a website URL we can scrape for contacts.
     */
    public function runSearchDiscovery(): void
    {
        $batch = SerperSearchSource::defaultQueries();
        $this->executeSearchBatch($batch);
    }

    /**
     * Run custom manual discovery from UI.
     */
    public function runCustom(array $selectedQueries, ?string $customQuery, ?string $customLocation, ?string $customCountry, array $enabledMethods = []): void
    {
        $mapsBatch   = [];
        $searchBatch = [];

        // 1. Process selected built-in queries
        $allMaps   = SerperMapsSource::defaultQueries();
        $allSearch = SerperSearchSource::defaultQueries();

        $allowMaps   = empty($enabledMethods) || in_array('maps', $enabledMethods, true);
        $allowSearch = empty($enabledMethods) || in_array('search', $enabledMethods, true);

        foreach ($selectedQueries as $item) {
            if (str_starts_with($item, 'maps_') && $allowMaps) {
                $idx = (int) str_replace('maps_', '', $item);
                if (isset($allMaps[$idx])) {
                    $mapsBatch[] = $allMaps[$idx];
                }
            } elseif (str_starts_with($item, 'search_') && $allowSearch) {
                $idx = (int) str_replace('search_', '', $item);
                if (isset($allSearch[$idx])) {
                    $searchBatch[] = $allSearch[$idx];
                }
            }
        }

        // 2. Process custom query if provided
        if (!empty($customQuery)) {
            if (!empty($customLocation) && $allowMaps) {
                $mapsBatch[] = [
                    'query'    => $customQuery,
                    'location' => $customLocation,
                    'country'  => $customCountry ?? 'EG',
                ];
            } elseif (empty($customLocation) && $allowSearch) {
                $searchBatch[] = [
                    'query'   => $customQuery,
                    'country' => $customCountry ?? 'EG',
                ];
            } elseif (!empty($customLocation) && ! $allowMaps && $allowSearch) {
                // User provided a custom location but did not enable Maps;
                // fall back to Search so the user's query still runs.
                Log::info("DiscoveryOrchestrator: custom location provided but Maps not enabled — running Search for query='{$customQuery}'");
                $searchBatch[] = [
                    'query'   => $customQuery,
                    'country' => $customCountry ?? 'EG',
                ];
            }
        }

        // 3. Execute batches
        if (!empty($mapsBatch) && $allowMaps) {
            Log::info('DiscoveryOrchestrator: executing custom Maps batch count=' . count($mapsBatch));
            $this->executeMapsBatch($mapsBatch);
        }

        if (!empty($searchBatch) && $allowSearch) {
            Log::info('DiscoveryOrchestrator: executing custom Search batch count=' . count($searchBatch));
            $this->executeSearchBatch($searchBatch);
        }
    }

    /**
     * Execute a batch of Maps queries.
     */
    private function executeMapsBatch(array $batch): void
    {
        foreach ($batch as $queryConfig) {
            $scrapingJob = ScrapingJob::create([
                'source'       => 'serper_maps',
                'query'        => $queryConfig['query'],
                'location'     => $queryConfig['location'],
                'country_code' => $queryConfig['country'],
                'status'       => 'running',
                'started_at'   => now(),
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

                    // Save raw result for auditing
                    ScrapingResult::create([
                        'scraping_job_id' => $scrapingJob->id,
                        'raw_data'        => $place,
                        'company_name'    => $place['title'] ?? null,
                        'website'         => $website,
                        'phone'           => $place['phone'] ?? null,
                        'location'        => $place['address'] ?? null,
                    ]);

                    // Extract contact info + description from website
                    $contactInfo = $website
                        ? $this->contactExtractor->extract($website, $place['phone'] ?? null)
                        : ['emails' => [], 'phones' => []];

                    $meta = $website ? $this->metaExtractor->extract($website) : ['description' => null, 'title' => null];

                    $email = $contactInfo['emails'][0] ?? null;
                    $phone = $contactInfo['phones'][0] ?? ($place['phone'] ?? null);

                    $result = $this->leadProcessor->process(
                        companyData: [
                            'name'        => $place['title'] ?? $meta['title'] ?? 'Unknown',
                            'website'     => $website,
                            'country'     => $queryConfig['country'],
                            'city'        => $queryConfig['location'],
                            'description' => $meta['description'],
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
                $errorMessage = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
                $scrapingJob->update([
                    'status'        => 'failed',
                    'error_message' => mb_substr($errorMessage, 0, 1000, 'UTF-8'),
                    'completed_at'  => now(),
                ]);
                Log::error("DiscoveryOrchestrator Maps failed for query '{$queryConfig['query']}': " . $errorMessage);
            }

            // Polite delay between queries to avoid rate limiting
            sleep(rand(2, 5));
        }
    }

    /**
     * Execute a batch of Search queries.
     */
    private function executeSearchBatch(array $batch): void
    {
        foreach ($batch as $queryConfig) {
            $scrapingJob = ScrapingJob::create([
                'source'       => 'serper_search',
                'query'        => $queryConfig['query'],
                'location'     => null,
                'country_code' => $queryConfig['country'],
                'status'       => 'running',
                'started_at'   => now(),
            ]);

            try {
                $results = $this->searchSource->search(
                    $queryConfig['query'],
                    $queryConfig['country']
                );

                $newLeads   = 0;
                $duplicates = 0;

                foreach ($results as $result) {
                    $website = $result['link'] ?? null;

                    if (! $website) {
                        continue;
                    }

                    ScrapingResult::create([
                        'scraping_job_id' => $scrapingJob->id,
                        'raw_data'        => $result,
                        'company_name'    => $result['title'] ?? null,
                        'website'         => $website,
                        'phone'           => null,
                        'location'        => null,
                    ]);

                    $contactInfo = $this->contactExtractor->extract($website);
                    $meta = $this->metaExtractor->extract($website);

                    $email = $contactInfo['emails'][0] ?? null;
                    $phone = $contactInfo['phones'][0] ?? null;

                    $outcome = $this->leadProcessor->process(
                        companyData: [
                            'name'        => $result['title'] ?? $meta['title'] ?? 'Unknown',
                            'website'     => $website,
                            'country'     => $queryConfig['country'],
                            'city'        => null,
                            'description' => $meta['description'] ?? ($result['snippet'] ?? null),
                            'source'      => 'serper_search',
                            'source_url'  => $website,
                        ],
                        contactData: [
                            'email'      => $email,
                            'phone'      => $phone,
                            'source'     => 'serper_search',
                            'source_url' => $website,
                        ],
                        countryCode: $queryConfig['country']
                    );

                    $outcome['skipped'] ? $duplicates++ : $newLeads++;
                }

                $scrapingJob->update([
                    'status'        => 'completed',
                    'results_found' => count($results),
                    'new_leads'     => $newLeads,
                    'duplicates'    => $duplicates,
                    'completed_at'  => now(),
                ]);

            } catch (\Exception $e) {
                $errorMessage = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
                $scrapingJob->update([
                    'status'        => 'failed',
                    'error_message' => mb_substr($errorMessage, 0, 1000, 'UTF-8'),
                    'completed_at'  => now(),
                ]);
                Log::error("DiscoveryOrchestrator Search failed for query '{$queryConfig['query']}': " . $errorMessage);
            }

            sleep(rand(2, 4));
        }
    }

    /**
     * Run all discovery sources in sequence.
     * Called by RunDiscoveryJob.
     */
    public function runAll(): void
    {
        $this->runMapsDiscovery();
        $this->runSearchDiscovery();
    }
}
