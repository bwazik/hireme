<?php

namespace App\Jobs;

use App\Models\OutreachLog;
use App\Services\Email\EmailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(public readonly OutreachLog $log)
    {
        $this->onQueue('sending');
    }

    public function handle(): void
    {
        (new EmailSender())->send($this->log);
    }
}
