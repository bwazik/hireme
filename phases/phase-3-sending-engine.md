# Phase 3 — Sending Engine
**Timeline**: Day 9–12 | **Goal**: Send real emails via Gmail SMTP and WhatsApp via Evolution API. Logs appear in Filament.

> 📌 **Reference [`CONSTANTS.md`](./CONSTANTS.md)** for: Gmail SMTP details (§5.4), Evolution API URL (§5.3), rate limits (§6), sending window (§7), queue priority (§12), CV path (§13).

> **Prerequisite**: Phase 2 complete — `MessageGenerator` works, `OutreachMessage` records can be created.

---

## Context

This phase builds the actual sending layer:
- **Email**: PHPMailer → Gmail SMTP (App Password), with CV attachment, account rotation, daily limits
- **WhatsApp**: Evolution API (self-hosted — see `CONSTANTS.md` §5.3 for base URL), random delay between messages
- **Logging**: Every send attempt (success or failure) is recorded in `outreach_logs`

**Key rules** — all values live in `CONSTANTS.md` §6:
- Email: max 80/account/day, delay 2–8s, window 9•10 PM
- WhatsApp: max 50/instance/day, delay 60–120s, window 9•10 PM
- Account rotation: pick account with oldest `last_used_at` that hasn’t hit its `daily_limit`

---

## Step 1 — Email Service Layer

Create directory: `app/Services/Email/`

### AccountSelector.php

`app/Services/Email/AccountSelector.php`:

```php
<?php

namespace App\Services\Email;

use App\Models\EmailAccount;
use App\Models\SystemSetting;

class AccountSelector
{
    /**
     * Pick the least-recently-used active email account that hasn't hit its daily limit.
     * Returns null if all accounts are exhausted.
     */
    public function select(): ?EmailAccount
    {
        $startHour = (int) SystemSetting::get('daily_start_hour', 9);
        $endHour   = (int) SystemSetting::get('daily_end_hour', 22);
        $currentHour = (int) now()->format('G');

        if ($currentHour < $startHour || $currentHour >= $endHour) {
            return null; // Outside sending window
        }

        return EmailAccount::query()
            ->where('is_active', true)
            ->whereRaw('sent_today < daily_limit')
            ->orderBy('last_used_at', 'asc')
            ->first();
    }
}
```

### SmtpMailer.php

`app/Services/Email/SmtpMailer.php`:

```php
<?php

namespace App\Services\Email;

use App\Models\EmailAccount;
use App\Models\SystemSetting;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class SmtpMailer
{
    public function __construct(private readonly EmailAccount $account) {}

    /**
     * Send an email. Throws PHPMailerException on failure.
     *
     * @param  bool  $attachCv  Whether to attach Abdullah_Mohamed_CV.pdf
     */
    public function send(string $to, string $subject, string $body, bool $attachCv = true): void
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $this->account->smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->account->email;
        $mail->Password   = decrypt($this->account->password);
        $mail->SMTPSecure = $this->account->smtp_encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $this->account->smtp_port;

        $mail->setFrom($this->account->email, $this->account->sender_name);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false); // Plain text

        if ($attachCv) {
            $cvPath = SystemSetting::get('email_cv_path', 'cv/Abdullah_Mohamed_CV.pdf');
            $fullPath = storage_path('app/' . $cvPath);

            if (file_exists($fullPath)) {
                $mail->addAttachment($fullPath, 'Abdullah_Mohamed_CV.pdf');
            }
        }

        $mail->send();
    }
}
```

### EmailSender.php

`app/Services/Email/EmailSender.php`:

```php
<?php

namespace App\Services\Email;

use App\Models\OutreachLog;
use Illuminate\Support\Facades\Log;

class EmailSender
{
    public function send(OutreachLog $log): bool
    {
        // Dry-run guard: log the send but skip real SMTP call
        // Set system_setting 'dry_run' => 'false' when ready to go live
        if (\App\Models\SystemSetting::get('dry_run', 'true') === 'true') {
            \Illuminate\Support\Facades\Log::info("[DRY RUN] Would email {$log->contact?->email} — log #{$log->id}");
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
```

---

## Step 2 — WhatsApp Service Layer

Create directory: `app/Services/WhatsApp/`

### EvolutionApiClient.php

`app/Services/WhatsApp/EvolutionApiClient.php`:

```php
<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionApiClient
{
    /**
     * Send a WhatsApp text message.
     *
     * @return array{success: bool, http_code: int, body: mixed}
     */
    public function sendText(WhatsappInstance $instance, string $phone, string $message): array
    {
        $apiKey  = decrypt($instance->api_key);
        $url     = rtrim($instance->api_url, '/') . '/message/sendText/' . $instance->name;

        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'apikey'       => $apiKey,
            ])->post($url, [
                'number'      => $phone,
                'text'        => $message,
                'delay'       => 1200,      // typing indicator delay (ms)
                'linkPreview' => true,
            ]);

            return [
                'success'   => $response->successful(),
                'http_code' => $response->status(),
                'body'      => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("EvolutionApiClient error: " . $e->getMessage());
            return ['success' => false, 'http_code' => 0, 'body' => $e->getMessage()];
        }
    }

    /**
     * Check if a number exists on WhatsApp.
     */
    public function checkNumber(WhatsappInstance $instance, string $phone): bool
    {
        $apiKey = decrypt($instance->api_key);
        $url    = rtrim($instance->api_url, '/') . '/chat/whatsappNumbers/' . $instance->name;

        try {
            $response = Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
                'apikey'       => $apiKey,
            ])->post($url, [
                'numbers' => [$phone],
            ]);

            if (! $response->successful()) return false;

            $data = $response->json();
            return isset($data[0]['exists']) && $data[0]['exists'] === true;
        } catch (\Exception $e) {
            Log::error("EvolutionApiClient checkNumber error: " . $e->getMessage());
            return false;
        }
    }
}
```

### WhatsAppSender.php

`app/Services/WhatsApp/WhatsAppSender.php`:

```php
<?php

namespace App\Services\WhatsApp;

use App\Models\OutreachLog;
use App\Models\SystemSetting;
use App\Models\WhatsappInstance;

class WhatsAppSender
{
    public function send(OutreachLog $log): bool
    {
        // Dry-run guard: log the send but skip real Evolution API call
        if (\App\Models\SystemSetting::get('dry_run', 'true') === 'true') {
            \Illuminate\Support\Facades\Log::info("[DRY RUN] Would WhatsApp {$log->contact?->phone} — log #{$log->id}");
            $log->update(['status' => 'sent', 'sent_at' => now()]);
            return true;
        }

        $startHour   = (int) SystemSetting::get('daily_start_hour', 9);
        $endHour     = (int) SystemSetting::get('daily_end_hour', 22);
        $currentHour = (int) now()->format('G');

        if ($currentHour < $startHour || $currentHour >= $endHour) {
            $log->update(['status' => 'failed', 'failed_reason' => 'Outside sending window']);
            return false;
        }

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
```

---

## Step 3 — Queue Jobs

### SendEmailJob

```bash
php artisan make:job SendEmailJob
```

`app/Jobs/SendEmailJob.php`:

```php
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
```

### SendWhatsAppJob

```bash
php artisan make:job SendWhatsAppJob
```

`app/Jobs/SendWhatsAppJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\OutreachLog;
use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppJob implements ShouldQueue
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
        (new WhatsAppSender())->send($this->log);
    }
}
```

### ResetDailyCountersJob

```bash
php artisan make:job ResetDailyCountersJob
```

`app/Jobs/Maintenance/ResetDailyCountersJob.php`:

```php
<?php

namespace App\Jobs\Maintenance;

use App\Models\EmailAccount;
use App\Models\WhatsappInstance;
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
    }
}
```

Add to `routes/console.php`:
```php
use App\Jobs\Maintenance\ResetDailyCountersJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ResetDailyCountersJob())->dailyAt('00:01');
```

---

## Step 4 — OutreachLogResource in Filament

```bash
php artisan make:filament-resource OutreachLog --generate
```

Make it **read-only** (no create form). Table columns:

```php
Tables\Columns\TextColumn::make('company.name')->label('Company')->searchable(),
Tables\Columns\TextColumn::make('contact.email')->label('Contact'),
Tables\Columns\TextColumn::make('channel')->badge()
    ->color(fn (string $state): string => match($state) {
        'email'    => 'primary',
        'whatsapp' => 'success',
        default    => 'gray',
    }),
Tables\Columns\TextColumn::make('status')->badge()
    ->color(fn (string $state): string => match($state) {
        'pending' => 'warning',
        'sent'    => 'success',
        'failed'  => 'danger',
        'replied' => 'info',
        default   => 'gray',
    }),
Tables\Columns\TextColumn::make('sent_at')->dateTime()->sortable(),
Tables\Columns\TextColumn::make('failed_reason')->limit(40)->toggleable(),
Tables\Columns\IconColumn::make('follow_up_sent')->boolean(),
```

Filters:
```php
Tables\Filters\SelectFilter::make('status')
    ->options(['pending'=>'Pending','sent'=>'Sent','failed'=>'Failed','replied'=>'Replied']),
Tables\Filters\SelectFilter::make('channel')
    ->options(['email'=>'Email','whatsapp'=>'WhatsApp']),
```

Add a **"Mark as Replied"** row action:
```php
Tables\Actions\Action::make('mark_replied')
    ->label('Mark Replied')
    ->icon('heroicon-o-check-circle')
    ->color('success')
    ->form([
        Forms\Components\Textarea::make('reply_content')
            ->label('Reply content (optional)')
            ->rows(3),
    ])
    ->action(function (OutreachLog $record, array $data): void {
        $record->update([
            'status'        => 'replied',
            'replied_at'    => now(),
            'reply_content' => $data['reply_content'] ?? null,
        ]);
    })
    ->visible(fn (OutreachLog $record) => $record->status === 'sent'),
```

---

## Step 5 — "Send Now" Action on ContactResource

Add a manual send trigger to `ContactResource` for testing:

```php
Tables\Actions\Action::make('send_now')
    ->label('Send Now')
    ->icon('heroicon-o-paper-airplane')
    ->color('primary')
    ->requiresConfirmation()
    ->action(function (Contact $record): void {
        // Use campaign_id=1 for testing (create a test campaign first)
        $campaign = \App\Models\Campaign::first();
        if (! $campaign) {
            \Filament\Notifications\Notification::make()
                ->title('Create a campaign first.')->warning()->send();
            return;
        }
        \App\Jobs\GenerateMessageJob::dispatch($campaign, $record);
        \Filament\Notifications\Notification::make()
            ->title('Message generation queued!')->success()->send();
    }),
```

---

## Step 6 — Run and Test

**Start the queue worker**:
```bash
php artisan queue:work --queue=sending,generation,discovery,default --tries=3 --timeout=60
```

**Test email sending** (use your own email):
1. Add yourself as a contact in Filament
2. Click "Send Now" on that contact
3. Watch the queue worker output
4. Check `outreach_logs` table — status should be `sent`
5. Check your inbox

**Test WhatsApp sending** (use your own number):
1. Ensure a `WhatsappInstance` row exists in DB (add via Filament)
2. Add yourself as a contact with `phone = +20XXXXXXXXXX` and `is_whatsapp = true`
3. Click "Send Now"
4. Watch your WhatsApp

---

## Deliverable

- [ ] `EmailSender`, `SmtpMailer`, `AccountSelector` created in `app/Services/Email/`
- [ ] `WhatsAppSender`, `EvolutionApiClient` created in `app/Services/WhatsApp/`
- [ ] `SendEmailJob`, `SendWhatsAppJob`, `ResetDailyCountersJob` created
- [ ] `OutreachLogResource` visible in Filament with "Mark as Replied" action
- [ ] Successfully sent a test email to your own address
- [ ] Successfully sent a test WhatsApp to your own number
- [ ] Logs appear in `outreach_logs` table with correct status
- [ ] `sent_today` increments on the email account after each send

**Next**: Phase 4 — Campaign Engine (create campaigns, target contacts, full pipeline)
