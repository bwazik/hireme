<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use App\Services\AI\AiModelRouter;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class SystemSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'System Settings';
    protected static ?string $title           = 'System Settings';
    protected string $view                    = 'filament.pages.system-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $modelData = [];
        foreach (AiModelRouter::defaultModels() as $def) {
            $modelData["ai_model_{$def['id']}_enabled"]  = filter_var(SystemSetting::get("ai_model_{$def['id']}_enabled", 'true'), FILTER_VALIDATE_BOOLEAN);
            $modelData["ai_model_{$def['id']}_name"]     = SystemSetting::get("ai_model_{$def['id']}_name", $def['model']);
            $modelData["ai_model_{$def['id']}_rpd"]      = SystemSetting::get("ai_model_{$def['id']}_rpd", (string) $def['rpd']);
            $modelData["ai_model_{$def['id']}_used_today"] = SystemSetting::get("ai_model_{$def['id']}_used_today", '0');
        }

        $this->form->fill(array_merge([
            'gemini_api_key'         => SystemSetting::get('gemini_api_key'),
            'serper_api_key'         => SystemSetting::get('serper_api_key'),
            'groq_api_key'           => SystemSetting::get('groq_api_key'),
            'whatsapp_delay_min'     => SystemSetting::get('whatsapp_delay_min', '60'),
            'whatsapp_delay_max'     => SystemSetting::get('whatsapp_delay_max', '120'),
            'daily_start_hour'       => SystemSetting::get('daily_start_hour', '9'),
            'daily_end_hour'         => SystemSetting::get('daily_end_hour', '22'),
            'discovery_enabled'      => SystemSetting::get('discovery_enabled', 'true') === 'true',
            'sending_enabled'        => SystemSetting::get('sending_enabled', 'true') === 'true',
            'ai_generation_enabled'  => SystemSetting::get('ai_generation_enabled', 'true') === 'true',
            'serper_queries_used'    => SystemSetting::get('serper_queries_used', '0'),
            'email_cv_path'          => SystemSetting::get('email_cv_path', 'cv/Abdullah_Mohamed_CV.pdf'),
            'auto_language_enabled'  => filter_var(SystemSetting::get('auto_language_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
            'dry_run'                => SystemSetting::get('dry_run', 'true') === 'true',
            'discovery_query_offset' => SystemSetting::get('discovery_query_offset', '0'),
        ], $modelData));
    }

    public function form(Schema $schema): Schema
    {
        $modelSections = [];
        foreach (AiModelRouter::defaultModels() as $idx => $def) {
            $driver = strtoupper($def['driver']);
            $usedToday = (int) SystemSetting::get("ai_model_{$def['id']}_used_today", '0');
            $rpdLimit  = (int) SystemSetting::get("ai_model_{$def['id']}_rpd", (string) $def['rpd']);
            $remaining = max(0, $rpdLimit - $usedToday);

            $modelSections[] = Section::make("Model " . ($idx + 1) . " — {$driver}: {$def['model']}")
                ->schema([
                    Toggle::make("ai_model_{$def['id']}_enabled")
                        ->label('Enabled')
                        ->helperText("Used today: {$usedToday} / {$rpdLimit}  (Remaining: {$remaining})"),
                    TextInput::make("ai_model_{$def['id']}_name")
                        ->label('Model Name')
                        ->helperText('Override the exact model name sent to the API (e.g. gemini-2.0-flash-exp).'),
                    TextInput::make("ai_model_{$def['id']}_rpd")
                        ->label('Daily Request Limit (RPD)')
                        ->numeric()
                        ->helperText('Set to 0 to disable the daily cap for this model.'),
                ])
                ->collapsed($idx > 0);
        }

        return $schema
            ->schema([
                Section::make('API Keys')->schema([
                    TextInput::make('gemini_api_key')
                        ->label('Gemini API Key')
                        ->password()
                        ->helperText('Get free key at aistudio.google.com/app/apikey'),
                    TextInput::make('groq_api_key')
                        ->label('Groq API Key')
                        ->password()
                        ->helperText('Get free key at console.groq.com — used as fallback when all Gemini quotas are hit.'),
                    TextInput::make('serper_api_key')
                        ->label('Serper.dev API Key')
                        ->password()
                        ->helperText('Get free key at serper.dev (2500 searches)'),
                    TextInput::make('serper_queries_used')
                        ->label('Serper Queries Used')
                        ->disabled()
                        ->helperText('2500 total free. Remaining: ' . (2500 - (int) SystemSetting::get('serper_queries_used', 0))),
                    TextInput::make('discovery_query_offset')
                        ->label('Discovery Query Offset')
                        ->numeric()
                        ->helperText('Current offset cursor for Serper search. Increments by 15 on every discovery job.'),
                ]),

                Section::make('Sending Window & Attachments')->schema([
                    TextInput::make('daily_start_hour')
                        ->label('Send After Hour (24h)')
                        ->numeric()
                        ->minValue(0)->maxValue(23),
                    TextInput::make('daily_end_hour')
                        ->label('Send Before Hour (24h)')
                        ->numeric()
                        ->minValue(0)->maxValue(23),
                    TextInput::make('email_cv_path')
                        ->label('Email CV Storage Path')
                        ->helperText('Path inside storage/app/ (e.g. cv/Abdullah_Mohamed_CV.pdf)'),
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
                    Toggle::make('auto_language_enabled')
                        ->label('Arabic Auto-Detection')
                        ->helperText('Automatically send Arabic messages to Egyptian companies.'),
                    Toggle::make('dry_run')
                        ->label('Dry Run Mode')
                        ->helperText('When enabled, emails and WhatsApp messages are logged but not actually sent.'),
                ]),

                Section::make('AI Model Cascade (Priority Order)')
                    ->description('Models are tried in order. On a 429 quota error the next model is used automatically. Resets every midnight.')
                    ->schema($modelSections),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            SystemSetting::set($key, is_bool($value) ? ($value ? 'true' : 'false') : $value);
        }

        Notification::make()->title('Settings saved!')->success()->send();
    }
}
