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
