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

    public int $tries   = 3;
    public int $timeout = 1800; // 10 minutes — scraping many sites takes time

    public function __construct(
        public array $selectedQueries = [],
        public ?string $customQuery = null,
        public ?string $customLocation = null,
        public ?string $customCountry = null,
        public array $methods = []
    ) {
        $this->onQueue('discovery');
    }

    public function handle(): void
    {
        if (SystemSetting::get('discovery_enabled', 'true') !== 'true') {
            Log::info('RunDiscoveryJob: discovery is disabled. Skipping.');

            return;
        }

        $creditsUsed = (int) SystemSetting::get('serper_queries_used', 0);
        $creditLimit = 2400; // Hard stop — keeps 100-credit safety buffer before Serper's 2,500 limit

        if ($creditsUsed >= $creditLimit) {
            Log::warning("RunDiscoveryJob: Serper credit limit reached ({$creditsUsed}/{$creditLimit}). Discovery paused.");

            return;
        }

        // Log incoming parameters for debugging (safe: no secrets)
        Log::info('RunDiscoveryJob params: ' . json_encode([
            'selectedQueries' => $this->selectedQueries,
            'customQuery'     => $this->customQuery,
            'customLocation'  => $this->customLocation,
            'customCountry'   => $this->customCountry,
            'methods'         => $this->methods,
        ]));

        $orchestrator = new DiscoveryOrchestrator();

        if (!empty($this->selectedQueries) || !empty($this->customQuery)) {
            Log::info("RunDiscoveryJob: starting custom manual discovery run...");
            $orchestrator->runCustom(
                $this->selectedQueries,
                $this->customQuery,
                $this->customLocation,
                $this->customCountry,
                $this->methods
            );
        } elseif (!empty($this->methods)) {
            // If methods are provided but no specific queries/custom query,
            // run the default discovery for the selected sources.
            Log::info('RunDiscoveryJob: starting discovery for methods: ' . implode(',', $this->methods));

            if (in_array('maps', $this->methods, true)) {
                $orchestrator->runMapsDiscovery();
            }
            if (in_array('search', $this->methods, true)) {
                $orchestrator->runSearchDiscovery();
            }
        } else {
            Log::info('RunDiscoveryJob: starting discovery run...');
            $orchestrator->runAll();
        }
        Log::info('RunDiscoveryJob: complete.');
    }
}
