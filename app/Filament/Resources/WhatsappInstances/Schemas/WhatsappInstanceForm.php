<?php

namespace App\Filament\Resources\WhatsappInstances\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WhatsappInstanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('api_url')
                    ->url()
                    ->required(),
                TextInput::make('api_key')
                    ->required(),
                TextInput::make('phone_number')
                    ->tel(),
                TextInput::make('daily_limit')
                    ->required()
                    ->numeric()
                    ->default(50),
                TextInput::make('sent_today')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
                DateTimePicker::make('last_used_at'),
            ]);
    }
}
