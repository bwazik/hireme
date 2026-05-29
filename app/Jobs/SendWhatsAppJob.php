<?php

namespace App\Jobs;

use App\Models\OutreachLog;
use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;

class SendWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(public readonly OutreachLog $log)
    {
        $this->onQueue('sending');
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('whatsapp')];
    }

    public function handle(): void
    {
        Log::info("SendWhatsAppJob: sending outreach_log #{$this->log->id} to {$this->log->contact_id}");
        (new WhatsAppSender())->send($this->log);
    }
}
