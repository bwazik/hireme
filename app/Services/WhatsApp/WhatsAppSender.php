<?php

namespace App\Services\WhatsApp;

use App\Models\OutreachLog;
use App\Models\SystemSetting;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Log;

class WhatsAppSender
{
    public function send(OutreachLog $log): bool
    {
        // Dry-run guard: log the send but skip real Evolution API call
        $dryRun = filter_var(SystemSetting::get('dry_run', 'true'), FILTER_VALIDATE_BOOLEAN);
        if ($dryRun) {
            Log::info("[DRY RUN] Would WhatsApp {$log->contact?->phone} — log #{$log->id}");
            $log->update(['status' => 'sent', 'sent_at' => now()]);
            return true;
        }

        // Time window check removed per user request

        $instance = WhatsappInstance::query()
            ->where('is_active', true)
            ->whereRaw('sent_today < daily_limit')
            ->orderBy('last_used_at', 'asc')
            ->first();

        if (! $instance) {
            $log->update(['status' => 'failed', 'failed_reason' => 'No WhatsApp instances available']);
            return false;
        }

        $message = $log->outreachMessage;
        $contact = $log->contact;

        if (! $contact->phone || ! $message) {
            $log->update(['status' => 'failed', 'failed_reason' => 'Missing phone or message']);
            return false;
        }

        $result = (new EvolutionApiClient())->sendText($instance, $contact->phone, $message->whatsapp_message);

        if ($result['success']) {
            $instance->increment('sent_today');
            $instance->update(['last_used_at' => now()]);
            $log->update(['status' => 'sent', 'sent_at' => now()]);
            return true;
        }

        $log->update([
            'status'        => 'failed',
            'failed_reason' => is_array($result['body']) ? json_encode($result['body']) : (string) $result['body'],
        ]);
        return false;
    }
}
