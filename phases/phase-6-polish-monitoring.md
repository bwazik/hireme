# Phase 6 — Polish & Monitoring
**Timeline**: Day 23–28 | **Goal**: System is fully autonomous and observable. Dashboard shows everything. Abdullah only needs to check replies.

> 📌 **Reference [`CONSTANTS.md`](./CONSTANTS.md)** for: all system setting keys & defaults (§7), API key names (§5), scheduler summary (§11), queue command (§12).

> **Prerequisite**: Phase 5 complete — discovery runs overnight and adds real companies.

---

## Step 1 — System Settings Page in Filament

Create a custom Filament page for managing all system settings (API keys, limits, toggles).

```bash
php artisan make:filament-page SystemSettings
```

`app/Filament/Pages/SystemSettings.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SystemSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'System Settings';
    protected static ?string $title           = 'System Settings';
    protected static string  $view            = 'filament.pages.system-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'gemini_api_key'       => SystemSetting::get('gemini_api_key'),
            'serper_api_key'       => SystemSetting::get('serper_api_key'),
            'whatsapp_delay_min'   => SystemSetting::get('whatsapp_delay_min', '60'),
            'whatsapp_delay_max'   => SystemSetting::get('whatsapp_delay_max', '120'),
            'daily_start_hour'     => SystemSetting::get('daily_start_hour', '9'),
            'daily_end_hour'       => SystemSetting::get('daily_end_hour', '22'),
            'discovery_enabled'    => SystemSetting::get('discovery_enabled', 'true') === 'true',
            'sending_enabled'      => SystemSetting::get('sending_enabled', 'true') === 'true',
            'ai_generation_enabled'=> SystemSetting::get('ai_generation_enabled', 'true') === 'true',
            'serper_queries_used'  => SystemSetting::get('serper_queries_used', '0'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('API Keys')->schema([
                    TextInput::make('gemini_api_key')
                        ->label('Gemini API Key')
                        ->password()
                        ->helperText('Get free key at aistudio.google.com/app/apikey'),
                    TextInput::make('serper_api_key')
                        ->label('Serper.dev API Key')
                        ->password()
                        ->helperText('Get free key at serper.dev (2500 searches)'),
                    TextInput::make('serper_queries_used')
                        ->label('Serper Queries Used')
                        ->disabled()
                        ->helperText('2500 total free. Remaining: ' . (2500 - (int) SystemSetting::get('serper_queries_used', 0))),
                ]),

                Section::make('Sending Window')->schema([
                    TextInput::make('daily_start_hour')
                        ->label('Send After Hour (24h)')
                        ->numeric()
                        ->minValue(0)->maxValue(23),
                    TextInput::make('daily_end_hour')
                        ->label('Send Before Hour (24h)')
                        ->numeric()
                        ->minValue(0)->maxValue(23),
                ]),

                Section::make('WhatsApp Delays')->schema([
                    TextInput::make('whatsapp_delay_min')
                        ->label('Min Delay (seconds)')
                        ->numeric(),
                    TextInput::make('whatsapp_delay_max')
                        ->label('Max Delay (seconds)')
                        ->numeric(),
                ]),

                Section::make('Feature Toggles')->schema([
                    Toggle::make('discovery_enabled')->label('Discovery Engine'),
                    Toggle::make('sending_enabled')->label('Message Sending'),
                    Toggle::make('ai_generation_enabled')->label('AI Generation'),
                ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            // Convert booleans to strings for storage
            SystemSetting::set($key, is_bool($value) ? ($value ? 'true' : 'false') : $value);
        }

        Notification::make()->title('Settings saved!')->success()->send();
    }
}
```

Create the view `resources/views/filament/pages/system-settings.blade.php`:

```blade
<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save Settings
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
```

---

## Step 2 — Reply Inbox Widget

```bash
php artisan make:filament-widget ReplyInboxWidget --table
```

`app/Filament/Widgets/ReplyInboxWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\OutreachLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ReplyInboxWidget extends BaseWidget
{
    protected static ?string $heading = 'Reply Inbox';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OutreachLog::query()
                    ->where('status', 'replied')
                    ->orderByDesc('replied_at')
                    ->limit(20)
            )
            ->columns([
                Tables\Columns\TextColumn::make('company.name')->label('Company')->searchable(),
                Tables\Columns\TextColumn::make('channel')->badge(),
                Tables\Columns\TextColumn::make('reply_content')->limit(80)->label('Reply'),
                Tables\Columns\TextColumn::make('replied_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_full')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Full Reply')
                    ->modalContent(fn (OutreachLog $record) => view(
                        'filament.reply-modal',
                        ['log' => $record]
                    )),
            ]);
    }
}
```

Create view `resources/views/filament/reply-modal.blade.php`:

```blade
<div class="space-y-4 p-4">
    <div><strong>Company:</strong> {{ $log->company->name ?? '—' }}</div>
    <div><strong>Channel:</strong> {{ ucfirst($log->channel) }}</div>
    <div><strong>Replied At:</strong> {{ $log->replied_at?->format('Y-m-d H:i') ?? '—' }}</div>
    <div>
        <strong>Reply Content:</strong>
        <pre class="mt-2 bg-gray-50 p-3 rounded text-sm whitespace-pre-wrap">{{ $log->reply_content ?? 'No content recorded.' }}</pre>
    </div>
    <div>
        <strong>Original Message Sent:</strong>
        <pre class="mt-2 bg-gray-50 p-3 rounded text-sm whitespace-pre-wrap">{{ $log->outreachMessage?->email_body ?? '—' }}</pre>
    </div>
</div>
```

---

## Step 3 — Discovery Activity Widget

```bash
php artisan make:filament-widget DiscoveryActivityWidget --table
```

`app/Filament/Widgets/DiscoveryActivityWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\ScrapingJob;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DiscoveryActivityWidget extends BaseWidget
{
    protected static ?string $heading = 'Discovery Activity';

    public function table(Table $table): Table
    {
        return $table
            ->query(ScrapingJob::query()->orderByDesc('created_at')->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('source')->badge(),
                Tables\Columns\TextColumn::make('query')->limit(35),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match($state) {
                        'completed' => 'success',
                        'failed'    => 'danger',
                        'running'   => 'warning',
                        default     => 'gray',
                    }),
                Tables\Columns\TextColumn::make('results_found')->label('Found'),
                Tables\Columns\TextColumn::make('new_leads')->label('New'),
                Tables\Columns\TextColumn::make('duplicates')->label('Dups'),
                Tables\Columns\TextColumn::make('completed_at')->dateTime()->sortable(),
            ]);
    }
}
```

---

## Step 4 — Blacklist Management

Add a "Blacklist" action to `ContactResource` and `CompanyResource`:

In `ContactResource` table actions:
```php
Tables\Actions\Action::make('blacklist')
    ->label('Blacklist')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->requiresConfirmation()
    ->action(fn (Contact $record) => $record->update(['is_active' => false])),
```

In `CompanyResource` table actions:
```php
Tables\Actions\Action::make('blacklist')
    ->label('Blacklist')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->requiresConfirmation()
    ->action(fn (Company $record) => $record->update(['is_active' => false])),
```

---

## Step 5 — Export Outreach Logs to CSV

Add a header action to `OutreachLogResource`:

```php
Tables\Actions\Action::make('export_csv')
    ->label('Export CSV')
    ->icon('heroicon-o-arrow-down-tray')
    ->action(function () {
        $logs = \App\Models\OutreachLog::with(['company', 'contact', 'campaign'])
            ->orderByDesc('sent_at')
            ->get();

        $filename = 'outreach-logs-' . now()->format('Y-m-d') . '.csv';
        $headers  = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename={$filename}"];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Company', 'Email', 'Phone', 'Channel', 'Status', 'Sent At', 'Replied At', 'Reply Content']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->company->name ?? '',
                    $log->contact->email ?? '',
                    $log->contact->phone ?? '',
                    $log->channel,
                    $log->status,
                    $log->sent_at?->format('Y-m-d H:i'),
                    $log->replied_at?->format('Y-m-d H:i'),
                    $log->reply_content ?? '',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }),
```

---

## Step 6 — Register All Widgets on Dashboard

In `app/Providers/Filament/AdminPanelProvider.php`, ensure all widgets are registered:

```php
->widgets([
    \App\Filament\Widgets\StatsOverviewWidget::class,
    \App\Filament\Widgets\CampaignStatusWidget::class,
    \App\Filament\Widgets\ReplyInboxWidget::class,
    \App\Filament\Widgets\DiscoveryActivityWidget::class,
])
```

---

## Step 7 — Error Alerting in Dashboard

Add a "Failed Jobs" stat to `StatsOverviewWidget`:

```php
Stat::make('Failed Jobs (24h)',
    \Illuminate\Support\Facades\DB::table('failed_jobs')
        ->where('failed_at', '>=', now()->subDay())
        ->count()
)->color('danger'),
```

Ensure the `failed_jobs` table exists:
```bash
php artisan queue:failed-table
php artisan migrate
```

---

## Step 8 — Final Local Run Instructions

Run all 3 processes simultaneously (3 terminals):

**Terminal 1 — Laravel server**:
```bash
php artisan serve
```

**Terminal 2 — Queue worker**:
```bash
php artisan queue:work --queue=sending,generation,discovery,default --tries=3 --timeout=120
```

**Terminal 3 — Scheduler**:
```bash
php artisan schedule:work
```

---

## Deliverable Checklist

- [ ] System Settings page accessible at `/admin/system-settings` with all toggles and API key inputs
- [ ] Reply Inbox widget shows all replied contacts with full reply content
- [ ] Discovery Activity widget shows last 10 scraping jobs with stats
- [ ] Blacklist action works on both Company and Contact resources
- [ ] "Export CSV" action on OutreachLog downloads a valid CSV file
- [ ] All 4 widgets registered and visible on `/admin` dashboard
- [ ] Failed jobs count shown on StatsOverviewWidget
- [ ] System runs autonomously: scheduler triggers discovery at 7am, campaign building hourly, follow-ups at 10am, counter reset at midnight

---

## Final System Architecture Summary

```
Every day @ 07:00  → RunDiscoveryJob → scrapes Serper Maps → adds companies to DB
Every hour          → BuildCampaignQueueJob → finds new eligible contacts
                    → GenerateMessageJob per contact → Gemini Flash
                    → SendEmailJob (Gmail SMTP, LRU rotation)
                    → SendWhatsAppJob (Evolution API, 60-120s random delay)
Every day @ 10:00  → ScheduleFollowUpsJob → re-contacts unreplied (7 days later)
Every day @ 00:01  → ResetDailyCountersJob → resets sent_today on all accounts

Dashboard → Stats, Reply Inbox, Campaign Status, Discovery Activity
Abdullah  → Checks dashboard each morning, marks replies, system does the rest.
```

---

## Abdullah's Daily Routine (2 minutes)

1. Open `/admin` dashboard
2. Check **Sent Today** count — confirm system ran overnight
3. Check **Reply Inbox** — any new replies?
4. Mark replies, optionally paste reply content
5. Done. System continues autonomously.
