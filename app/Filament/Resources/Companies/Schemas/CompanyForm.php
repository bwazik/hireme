<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                Select::make('industry')
                    ->multiple()
                    ->options([
                    'software_house' => 'Software House',
                    'saas'           => 'SaaS',
                    'mobile_dev'     => 'Mobile Dev',
                    'fintech'        => 'FinTech',
                    'e_commerce'     => 'E-Commerce',
                    'digital_agency' => 'Digital Agency',
                    'ai_company'     => 'AI Company',
                    'web_design'     => 'Web Design',
                    'other'          => 'Other',
                ]),
                Select::make('size')
                    ->options(['unknown' => 'Unknown', 'small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                    ->default('unknown'),
                TextInput::make('country'),
                TextInput::make('city'),
                TextInput::make('website')->url(),
                TextInput::make('website_domain'),
                Textarea::make('description')->columnSpanFull(),
                TextInput::make('linkedin_url')->url(),
                Select::make('source')->options([
                    'google_maps'   => 'Google Maps',
                    'serper_search' => 'Serper Search',
                    'linkedin'      => 'LinkedIn',
                    'indeed'        => 'Indeed',
                    'wellfound'     => 'Wellfound',
                    'manual'        => 'Manual',
                    'facebook'      => 'Facebook',
                ])->required(),
                Textarea::make('source_url')->columnSpanFull(),
                Textarea::make('notes')->columnSpanFull(),
                Toggle::make('is_active')->default(true),
            ]);
    }
}
