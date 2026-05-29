<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppJob;
use App\Models\OutreachLog;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequeueWhatsAppSends extends Command
{
    protected $signature = 'whatsapp:requeue {--hours=24 : lookback window in hours} {--dry-run : do not modify jobs, just show what would be done} {--stagger-per=0 : if >0, spread sends by this many seconds between each job}';
    protected $description = 'Re-dispatch pending WhatsApp sends with randomized delays and remove old queued duplicates (best-effort)';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Looking for pending WhatsApp outreach logs in the last {$hours} hours...");

        $logs = OutreachLog::where('channel', 'whatsapp')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subHours($hours))
            ->get();

        if ($logs->isEmpty()) {
            $this->info('No pending WhatsApp outreach logs found.');
            return 0;
        }

        $this->info('Found ' . $logs->count() . ' pending logs.');

        $staggerPer = (int) $this->option('stagger-per');

        foreach ($logs as $index => $log) {
            $min = (int) SystemSetting::get('whatsapp_delay_min', 200);
            $max = (int) SystemSetting::get('whatsapp_delay_max', 350);
            if ($min > $max) { [$min, $max] = [$max, $min]; }

            if ($staggerPer > 0) {
                // choose a random start offset within min/max for the first job
                if ($index === 0) {
                    $startOffset = rand($min, $max);
                }
                $delaySeconds = $startOffset + ($index * $staggerPer);
                $scheduledAt = now()->addSeconds($delaySeconds);
            } else {
                $delaySeconds = rand($min, $max);
                $scheduledAt = now()->addSeconds($delaySeconds);
            }

            $this->line("- OutreachLog #{$log->id} -> delay={$delaySeconds}s scheduled_at={$scheduledAt}");

            if ($dryRun) {
                continue;
            }

            // Best-effort: remove existing DB queued jobs referencing this outreach log
            try {
                $deleted = DB::table('jobs')
                    ->where('queue', 'sending')
                    ->where('payload', 'like', "%SendWhatsAppJob%{$log->id}%")
                    ->delete();

                if ($deleted) {
                    Log::info("RequeueWhatsAppSends: removed {$deleted} queued job(s) for outreach_log #{$log->id}");
                }
            } catch (\Exception $e) {
                Log::warning("RequeueWhatsAppSends: failed to remove queued jobs for outreach_log #{$log->id}: " . $e->getMessage());
            }

            // Dispatch new job with randomized delay
            SendWhatsAppJob::dispatch($log)->onQueue('sending')->delay($scheduledAt);
            Log::info("RequeueWhatsAppSends: dispatched SendWhatsAppJob for outreach_log #{$log->id} scheduled_at={$scheduledAt}");
        }

        $this->info('Re-dispatch complete. Monitor workers and logs.');

        return 0;
    }
}
