<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\OutreachLog;
use App\Models\SystemSetting;
use App\Services\AI\MessageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\RateLimited;

class GenerateMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 100;
    public int $timeout = 60;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('gemini')];
    }

    public function __construct(
        public readonly Campaign $campaign,
        public readonly Contact $contact,
        public ?string $senderEmail = null,
        public readonly string $languageOverride = 'auto',
        public readonly bool $isFollowUp = false,
    ) {
        $this->senderEmail = $senderEmail ?? config('services.gmail.email', 'abdullahmohamed.swe@gmail.com');
        $this->onQueue('generation');
    }

    public function handle(): void
    {
        try {
            $generator = new MessageGenerator();
            $message = $this->isFollowUp
                ? $generator->generateFollowUp($this->campaign, $this->contact)
                : $generator->generate($this->campaign, $this->contact, $this->senderEmail, $this->languageOverride);

            // Queue send jobs for each channel enabled in the campaign
            if (in_array('email', $this->campaign->channels) && $this->contact->email) {
                $log = OutreachLog::create([
                    'campaign_id' => $this->campaign->id,
                    'contact_id' => $this->contact->id,
                    'company_id' => $this->contact->company_id,
                    'message_id' => $message->id,
                    'channel' => 'email',
                    'status' => 'pending',
                ]);
                SendEmailJob::dispatch($log)->onQueue('sending');
            }

            if (in_array('whatsapp', $this->campaign->channels) && $this->contact->phone) {
                $log = OutreachLog::create([
                    'campaign_id' => $this->campaign->id,
                    'contact_id' => $this->contact->id,
                    'company_id' => $this->contact->company_id,
                    'message_id' => $message->id,
                    'channel' => 'whatsapp',
                    'status' => 'pending',
                ]);

                $min = (int) SystemSetting::get('whatsapp_delay_min', 200);
                $max = (int) SystemSetting::get('whatsapp_delay_max', 350);
                if ($min > $max) {
                    [$min, $max] = [$max, $min];
                }

                $delaySeconds = rand($min, $max);
                $scheduledAt  = now()->addSeconds($delaySeconds);

                Log::info("GenerateMessageJob: scheduling WhatsApp send for contact #{$this->contact->id} delay={$delaySeconds}s scheduled_at={$scheduledAt->toDateTimeString()}");

                SendWhatsAppJob::dispatch($log)->onQueue('sending')->delay($scheduledAt);
            }
        } catch (\Exception $e) {
            Log::error("GenerateMessageJob failed for contact #{$this->contact->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
