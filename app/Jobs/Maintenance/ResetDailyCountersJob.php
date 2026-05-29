<?php

namespace App\Jobs\Maintenance;

use App\Models\EmailAccount;
use App\Models\WhatsappInstance;
use App\Services\AI\AiModelRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResetDailyCountersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        EmailAccount::query()->update(['sent_today' => 0, 'last_reset_at' => now()]);
        WhatsappInstance::query()->update(['sent_today' => 0]);
        AiModelRouter::resetDailyCounters();
    }
}

