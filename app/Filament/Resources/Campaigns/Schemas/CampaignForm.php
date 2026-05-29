<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->rows(3)
                ->columnSpanFull(),

            CheckboxList::make('channels')
                ->options(['email' => 'Email', 'whatsapp' => 'WhatsApp'])
                ->required()
                ->columns(2),

            Section::make('Targeting')
                ->schema([
                    TagsInput::make('target_countries')
                        ->placeholder('EG, DE, US')
                        ->helperText('Leave empty to target all countries'),

                    TagsInput::make('target_cities')
                        ->placeholder('Cairo, Alexandria')
                        ->helperText('Leave empty to target all cities'),

                    CheckboxList::make('target_industries')
                        ->options([
                            'software_house' => 'Software House',
                            'saas'           => 'SaaS',
                            'mobile_dev'     => 'Mobile Dev',
                            'digital_agency' => 'Digital Agency',
                            'ai_company'     => 'AI Company',
                            'web_design'     => 'Web Design',
                            'other'          => 'Other',
                        ])
                        ->helperText('Leave all unchecked to target all industries')
                        ->columns(2),
                ]),

            Section::make('Sending Limits')
                ->schema([
                    TextInput::make('daily_email_limit')
                        ->numeric()
                        ->default(50)
                        ->label('Daily Email Limit'),

                    TextInput::make('daily_whatsapp_limit')
                        ->numeric()
                        ->default(30)
                        ->label('Daily WhatsApp Limit'),
                ])
                ->columns(2),

            Section::make('Follow-up')
                ->schema([
                    Toggle::make('follow_up_enabled')
                        ->label('Enable Follow-ups'),

                    TextInput::make('follow_up_days')
                        ->numeric()
                        ->default(7)
                        ->helperText('Days after initial outreach to send follow-up if no reply'),
                ]),

            Select::make('status')
                ->options([
                    'draft'     => 'Draft',
                    'active'    => 'Active',
                    'paused'    => 'Paused',
                    'completed' => 'Completed',
                ])
                ->default('draft')
                ->required(),
        ]);
    }
}
