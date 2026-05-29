# Phase 4 — Campaign Engine
**Timeline**: Day 13–16 | **Goal**: Full campaign flow — create, target contacts, activate, watch queue process, see logs.

> 📌 **Reference [`CONSTANTS.md`](./CONSTANTS.md)** for: industry labels (§14), follow-up rules (§6), scheduler times (§11), queue priority (§12).

> **Prerequisite**: Phase 3 complete — email + WhatsApp sending works for a single contact.

---

## Context

This phase wires everything together. A Campaign defines:
- Which contacts to target (by country, industry, city)
- Which channels to use (email, WhatsApp)
- Daily send limits (see `CONSTANTS.md` §6 for defaults)
- Whether to send follow-ups after N days (default 7 — see `CONSTANTS.md` §6 “Follow-up Rules”)

When activated, `BuildCampaignQueueJob` selects eligible contacts, and for each one dispatches `GenerateMessageJob` → (after generation) `SendEmailJob` and/or `SendWhatsAppJob`.

---

## Step 1 — CampaignResource in Filament

```bash
php artisan make:filament-resource Campaign --generate
```

### Form

```php
Forms\Components\TextInput::make('name')->required(),
Forms\Components\Textarea::make('description'),

Forms\Components\CheckboxList::make('channels')
    ->options(['email' => 'Email', 'whatsapp' => 'WhatsApp'])
    ->required(),

Forms\Components\Section::make('Targeting')->schema([
    Forms\Components\TagsInput::make('target_countries')
        ->placeholder('EG, DE, US')
        ->helperText('Leave empty to target all countries'),
    Forms\Components\TagsInput::make('target_cities')
        ->placeholder('Cairo, Alexandria')
        ->helperText('Leave empty to target all cities'),
    Forms\Components\CheckboxList::make('target_industries')
        ->options([
            'software_house' => 'Software House',
            'saas'           => 'SaaS',
            'mobile_dev'     => 'Mobile Dev',
            'digital_agency' => 'Digital Agency',
            'ai_company'     => 'AI Company',
            'web_design'     => 'Web Design',
            'other'          => 'Other',
        ])
        ->helperText('Leave all unchecked to target all industries'),
]),

Forms\Components\Section::make('Sending Limits')->schema([
    Forms\Components\TextInput::make('daily_email_limit')->numeric()->default(50),
    Forms\Components\TextInput::make('daily_whatsapp_limit')->numeric()->default(30),
]),

Forms\Components\Section::make('Follow-up')->schema([
    Forms\Components\Toggle::make('follow_up_enabled'),
    Forms\Components\TextInput::make('follow_up_days')->numeric()->default(7)
        ->helperText('Days after initial outreach to send follow-up if no reply'),
]),

Forms\Components\Select::make('status')
    ->options([
        'draft'     => 'Draft',
        'active'    => 'Active',
        'paused'    => 'Paused',
        'completed' => 'Completed',
    ])
    ->default('draft'),
```

### Table

```php
Tables\Columns\TextColumn::make('name')->searchable(),
Tables\Columns\TextColumn::make('status')->badge()
    ->color(fn (string $state): string => match($state) {
        'draft'     => 'secondary',
        'active'    => 'success',
        'paused'    => 'warning',
        'completed' => 'primary',
        default     => 'gray',
    }),
Tables\Columns\TextColumn::make('outreachLogs_count')->counts('outreachLogs')->label('Sent'),
Tables\Columns\TextColumn::make('daily_email_limit')->label('Email Limit'),
Tables\Columns\TextColumn::make('daily_whatsapp_limit')->label('WA Limit'),
Tables\Columns\IconColumn::make('follow_up_enabled')->boolean(),
Tables\Columns\TextColumn::make('started_at')->dateTime(),
```

### Actions — Activate / Pause Campaign

Add to `->actions([])` in the table:

```php
Tables\Actions\Action::make('activate')
    ->label('Activate')
    ->icon('heroicon-o-play')
    ->color('success')
    ->requiresConfirmation()
    ->visible(fn (Campaign $record) => in_array($record->status, ['draft', 'paused']))
    ->action(function (Campaign $record): void {
        $record->update(['status' => 'active', 'started_at' => now()]);
        \App\Jobs\BuildCampaignQueueJob::dispatch($record);
        \Filament\Notifications\Notification::make()
            ->title("Campaign activated! Queue is building.")
            ->success()->send();
    }),

Tables\Actions\Action::make('pause')
    ->label('Pause')
    ->icon('heroicon-o-pause')
    ->color('warning')
    ->requiresConfirmation()
    ->visible(fn (Campaign $record) => $record->status === 'active')
    ->action(fn (Campaign $record) => $record->update(['status' => 'paused', 'paused_at' => now()])),
```

---

## Step 2 — CampaignContactSelector Service

Create `app/Services/Campaign/CampaignContactSelector.php`:

```php
<?php

namespace App\Services\Campaign;

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Collection;

class CampaignContactSelector
{
    /**
     * Return all contacts eligible for this campaign (not yet contacted by it).
     */
    public function selectContacts(Campaign $campaign): Collection
    {
        return Contact::query()
            ->with('company')
            ->whereHas('company', function ($q) use ($campaign) {
                $q->where('is_active', true);

                if (! empty($campaign->target_countries)) {
                    $q->whereIn('country', $campaign->target_countries);
                }
                if (! empty($campaign->target_industries)) {
                    $q->whereIn('industry', $campaign->target_industries);
                }
                if (! empty($campaign->target_cities)) {
                    $q->whereIn('city', $campaign->target_cities);
                }
            })
            ->where('is_active', true)
            // Not already contacted by this campaign
            ->whereDoesntHave('outreachLogs', fn ($q) => $q->where('campaign_id', $campaign->id))
            // Must have at least one channel available
            ->where(function ($q) use ($campaign) {
                $q->where(function ($inner) use ($campaign) {
                    if (in_array('email', $campaign->channels ?? [])) {
                        $inner->whereNotNull('email');
                    }
                });
                $q->orWhere(function ($inner) use ($campaign) {
                    if (in_array('whatsapp', $campaign->channels ?? [])) {
                        $inner->whereNotNull('phone');
                    }
                });
            })
            ->get();
    }
}
```

---

## Step 3 — BuildCampaignQueueJob

```bash
php artisan make:job BuildCampaignQueueJob
```

`app/Jobs/BuildCampaignQueueJob.php`:

```php
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

        foreach ($contacts as $contact) {
            GenerateMessageJob::dispatch($this->campaign, $contact)->onQueue('generation');
        }
    }
}
```

---

## Step 4 — ScheduleFollowUpsJob

```bash
php artisan make:job ScheduleFollowUpsJob
```

`app/Jobs/ScheduleFollowUpsJob.php`:

```php
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
        // Find campaigns with follow-ups enabled
        $campaigns = Campaign::where('status', 'active')
            ->where('follow_up_enabled', true)
            ->get();

        foreach ($campaigns as $campaign) {
            $cutoff = now()->subDays($campaign->follow_up_days);

            // Find logs that were sent, have no reply, no follow-up sent yet, older than cutoff
            $logsToFollowUp = OutreachLog::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', 'sent')
                ->where('follow_up_sent', false)
                ->where('sent_at', '<=', $cutoff)
                ->get();

            foreach ($logsToFollowUp as $log) {
                // Generate a follow-up message
                GenerateMessageJob::dispatch($campaign, $log->contact, isFollowUp: true)
                    ->onQueue('generation');

                $log->update(['follow_up_sent' => true, 'follow_up_sent_at' => now()]);
            }

            Log::info("ScheduleFollowUpsJob: queued {$logsToFollowUp->count()} follow-ups for campaign #{$campaign->id}");
        }
    }
}
```

> **Note**: `GenerateMessageJob` needs an `$isFollowUp` parameter added. Update `GenerateMessageJob.php`:

Add to the constructor:
```php
public function __construct(
    public readonly Campaign $campaign,
    public readonly Contact  $contact,
    public readonly bool     $isFollowUp = false,
)
```

In `handle()`, change the prompt builder call:
```php
// In handle():
$preview = $this->isFollowUp
    ? (new MessageGenerator())->generateFollowUp($this->campaign, $this->contact)
    : (new MessageGenerator())->generate($this->campaign, $this->contact);
```

Add `generateFollowUp` to `MessageGenerator.php`:
```php
public function generateFollowUp(Campaign $campaign, Contact $contact): OutreachMessage
{
    $company      = $contact->company;
    $daysSince    = (int) now()->diffInDays(
        OutreachLog::where('contact_id', $contact->id)
            ->where('campaign_id', $campaign->id)
            ->where('status', 'sent')
            ->value('sent_at') ?? now()
    );

    $systemPrompt = $this->builder->buildSystemPrompt();
    $userPrompt   = $this->builder->buildFollowUpPrompt($company, $daysSince);

    $parsed = $this->callWithRetry($systemPrompt, $userPrompt);

    if (! $this->isValidResponse($parsed)) {
        $parsed = [
            'email_subject'    => 'Just checking in',
            'email_body'       => "Hi,\n\nJust following up on my previous message about a potential opportunity at {$company->name}. Did you get a chance to review it?\n\nBest,\nAbdullah",
            'whatsapp_message' => "Hi again! Just checking if you had a chance to see my earlier message about joining your team. Would love to connect!",
        ];
    }

    return OutreachMessage::create([
        'campaign_id'      => $campaign->id,
        'contact_id'       => $contact->id,
        'company_id'       => $company->id,
        'email_subject'    => $parsed['email_subject'],
        'email_body'       => $parsed['email_body'],
        'whatsapp_message' => $parsed['whatsapp_message'],
        'ai_model'         => 'gemini-flash-latest',
        'generated_at'     => now(),
    ]);
}
```

---

## Step 5 — Scheduler Setup

Update `routes/console.php`:

```php
<?php

use App\Jobs\BuildCampaignQueueJob;
use App\Jobs\Maintenance\ResetDailyCountersJob;
use App\Jobs\ScheduleFollowUpsJob;
use App\Models\Campaign;
use Illuminate\Support\Facades\Schedule;

// Midnight: reset daily counters
Schedule::job(new ResetDailyCountersJob())->dailyAt('00:01');

// Daily: schedule follow-ups for unanswered contacts
Schedule::job(new ScheduleFollowUpsJob())->dailyAt('10:00');

// Every hour: build queue for active campaigns (picks up newly added contacts)
Schedule::call(function () {
    Campaign::where('status', 'active')->each(function (Campaign $campaign) {
        BuildCampaignQueueJob::dispatch($campaign);
    });
})->hourly();
```

---

## Step 6 — Dashboard Widgets

### StatsOverviewWidget

```bash
php artisan make:filament-widget StatsOverviewWidget --stats-overview
```

`app/Filament/Widgets/StatsOverviewWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Contact;
use App\Models\OutreachLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Companies', Company::count()),
            Stat::make('Total Contacts', Contact::count()),
            Stat::make('Emails Sent', OutreachLog::where('channel', 'email')->where('status', 'sent')->count()),
            Stat::make('WhatsApp Sent', OutreachLog::where('channel', 'whatsapp')->where('status', 'sent')->count()),
            Stat::make('Contacted Today', OutreachLog::where('status', 'sent')->whereDate('sent_at', today())->count()),
            Stat::make('Replies This Week', OutreachLog::where('status', 'replied')->whereBetween('replied_at', [now()->startOfWeek(), now()])->count()),
        ];
    }
}
```

### CampaignStatusWidget

```bash
php artisan make:filament-widget CampaignStatusWidget --table
```

`app/Filament/Widgets/CampaignStatusWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Campaign;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class CampaignStatusWidget extends BaseWidget
{
    protected static ?string $heading = 'Campaign Status';

    public function table(Table $table): Table
    {
        return $table
            ->query(Campaign::query()->orderByDesc('created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('outreachLogs_count')
                    ->counts('outreachLogs')->label('Sent'),
                Tables\Columns\TextColumn::make('replied_count')
                    ->label('Replied')
                    ->getStateUsing(fn (Campaign $r) => $r->outreachLogs()->where('status', 'replied')->count()),
            ]);
    }
}
```

Register both widgets in the Filament panel provider (`app/Providers/Filament/AdminPanelProvider.php`) inside `->widgets([])`.

---

## Step 7 — Full End-to-End Test

1. Ensure queue worker is running:
   ```bash
   php artisan queue:work --queue=sending,generation,discovery,default --tries=3
   ```

2. In Filament, create a Campaign:
   - Channels: Email + WhatsApp
   - No targeting filters (targets all)
   - Status: Draft

3. Make sure you have at least 1 company + 1 contact with email/phone

4. Click **Activate** on the campaign

5. Watch the terminal — you should see:
   ```
   Processing: App\Jobs\BuildCampaignQueueJob
   Processing: App\Jobs\GenerateMessageJob
   Processing: App\Jobs\SendEmailJob
   ```

6. Check `/admin/outreach-logs` — entries should appear with status `sent`

---

## Deliverable

- [ ] `CampaignResource` with Activate/Pause actions in Filament
- [ ] `CampaignContactSelector` correctly filters contacts
- [ ] `BuildCampaignQueueJob` dispatches `GenerateMessageJob` for each eligible contact
- [ ] `ScheduleFollowUpsJob` created and in scheduler
- [ ] `StatsOverviewWidget` and `CampaignStatusWidget` on Filament dashboard
- [ ] Full pipeline tested end-to-end: Campaign activated → messages generated → sent → logs visible

**Next**: Phase 5 — Discovery Engine (Serper.dev + website scraper)
