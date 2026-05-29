<?php

use App\Jobs\BuildCampaignQueueJob;
use App\Jobs\Discovery\RunDiscoveryJob;
use App\Jobs\Maintenance\CleanScrapeResultsJob;
use App\Jobs\Maintenance\ResetDailyCountersJob;
use App\Jobs\ScheduleFollowUpsJob;
use App\Models\Campaign;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Midnight: reset daily counters
Schedule::job(new ResetDailyCountersJob())->dailyAt('00:01');

// 10 AM: schedule follow-ups for unreplied contacts
Schedule::job(new ScheduleFollowUpsJob())->dailyAt('10:00');

// Weekly: purge processed scraping results older than 30 days
Schedule::job(new CleanScrapeResultsJob())->weekly();
