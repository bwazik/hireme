<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Jobs\BuildCampaignQueueJob;
use App\Models\Campaign;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateDailyCampaign')
                ->label('Generate Daily Campaign')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->form([
                    DatePicker::make('date')
                        ->label('Select Discovery Date')
                        ->default(today())
                        ->required(),
                    CheckboxList::make('channels')
                        ->label('Sending Channels')
                        ->options([
                            'email' => 'Email',
                            'whatsapp' => 'WhatsApp',
                        ])
                        ->default(['email', 'whatsapp'])
                        ->required(),
                    Toggle::make('follow_up_enabled')
                        ->label('Enable Follow-ups')
                        ->default(true),
                    TextInput::make('follow_up_days')
                        ->label('Follow-up Interval (Days)')
                        ->numeric()
                        ->default(3)
                        ->required(),
                    TextInput::make('daily_email_limit')
                        ->label('Daily Email Limit')
                        ->numeric()
                        ->default(50)
                        ->required(),
                    TextInput::make('daily_whatsapp_limit')
                        ->label('Daily WhatsApp Limit')
                        ->numeric()
                        ->default(50)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $campaignName = 'Outreach - ' . $data['date'];

                    if (Campaign::where('name', $campaignName)->exists()) {
                        Notification::make()
                            ->title('Campaign already exists')
                            ->body("A campaign named '{$campaignName}' has already been generated.")
                            ->danger()
                            ->send();
                        return;
                    }

                    // Count matching companies
                    $companyCount = Company::whereDate('created_at', $data['date'])->count();

                    if ($companyCount === 0) {
                        Notification::make()
                            ->title('No companies found')
                            ->body("No companies were discovered on {$data['date']}.")
                            ->warning()
                            ->send();
                        return;
                    }

                    // Create the campaign
                    $campaign = Campaign::create([
                        'name' => $campaignName,
                        'description' => "Manual daily campaign generated on " . now()->toDateTimeString() . " targeting {$companyCount} companies fetched on " . $data['date'],
                        'channels' => $data['channels'],
                        'daily_email_limit' => $data['daily_email_limit'],
                        'daily_whatsapp_limit' => $data['daily_whatsapp_limit'],
                        'follow_up_enabled' => $data['follow_up_enabled'],
                        'follow_up_days' => $data['follow_up_days'],
                        'status' => 'active',
                        'started_at' => now(),
                    ]);

                    // Trigger building the queue manually!
                    BuildCampaignQueueJob::dispatch($campaign)->onQueue('generation');

                    Notification::make()
                        ->title('Campaign Created Successfully')
                        ->body("Daily campaign '{$campaignName}' was created for {$companyCount} companies and is queuing messages.")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
