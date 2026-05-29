<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\Campaign\CampaignContactSelector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BuildCampaignQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct(public readonly Campaign $campaign)
    {
        $this->onQueue('generation');
    }

    public function handle(): void
    {
        if ($this->campaign->status !== 'active') {
            return;
        }

        $contacts = (new CampaignContactSelector())->selectContacts($this->campaign);

        Log::info("BuildCampaignQueueJob: campaign #{$this->campaign->id} — {$contacts->count()} contacts eligible");

        $delaySeconds = 0;
        foreach ($contacts as $contact) {
            GenerateMessageJob::dispatch($this->campaign, $contact)
                ->onQueue('generation')
                ->delay(now()->addSeconds($delaySeconds));
            
            $delaySeconds += 10; // Space them 10 seconds apart
        }
    }
}
