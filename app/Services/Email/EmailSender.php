<?php

namespace App\Services\Email;

use App\Models\OutreachLog;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

class EmailSender
{
    public function send(OutreachLog $log): bool
    {
        // Dry-run guard: log the send but skip real SMTP call
        // Set system_setting 'dry_run' => 'false' when ready to go live
        $dryRun = filter_var(SystemSetting::get('dry_run', 'true'), FILTER_VALIDATE_BOOLEAN);
        if ($dryRun) {
            Log::info("[DRY RUN] Would email {$log->contact?->email} — log #{$log->id}");
            $log->update(['status' => 'sent', 'sent_at' => now()]);
            return true;
        }

        $account = (new AccountSelector())->select();

        if (! $account) {
            $log->update([
                'status'        => 'failed',
                'failed_reason' => 'No email accounts available or outside sending window',
            ]);
            return false;
        }

        $message = $log->outreachMessage;
        $contact = $log->contact;

        if (! $contact->email || ! $message) {
            $log->update(['status' => 'failed', 'failed_reason' => 'Missing email or message']);
            return false;
        }

        try {
            (new SmtpMailer($account))->send(
                to: $contact->email,
                subject: $message->email_subject,
                body: $message->email_body,
                attachCv: true,
            );

            $account->increment('sent_today');
            $account->update(['last_used_at' => now()]);

            $log->update([
                'status'           => 'sent',
                'sent_at'          => now(),
                'email_account_id' => $account->id,
            ]);

            return true;
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'failed_reason' => $e->getMessage()]);
            Log::error("EmailSender failed for log #{$log->id}: " . $e->getMessage());
            return false;
        }
    }
}
