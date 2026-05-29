<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\OutreachLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScheduleFollowUpsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $campaigns = Campaign::where('status', 'active')
            ->where('follow_up_enabled', true)
            ->get();

        foreach ($campaigns as $campaign) {
            $cutoff = now()->subDays($campaign->follow_up_days);

            // Logs that were sent, unreplied, no follow-up yet, older than cutoff
            $logsToFollowUp = OutreachLog::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', 'sent')
                ->where('follow_up_sent', false)
                ->where('sent_at', '<=', $cutoff)
                ->with('contact')
                ->get();

            $delaySeconds = 0;
            foreach ($logsToFollowUp as $log) {
                if (! $log->contact) {
                    continue;
                }

                GenerateMessageJob::dispatch($campaign, $log->contact, isFollowUp: true)
                    ->onQueue('generation')
                    ->delay(now()->addSeconds($delaySeconds));

                $log->update(['follow_up_sent' => true, 'follow_up_sent_at' => now()]);

                $delaySeconds += 10; // Space them 10 seconds apart
            }

            Log::info("ScheduleFollowUpsJob: queued {$logsToFollowUp->count()} follow-ups for campaign #{$campaign->id}");
        }
    }
}
