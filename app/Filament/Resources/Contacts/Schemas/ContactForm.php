<?php

namespace App\Filament\Resources\Contacts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name'),
                TextInput::make('role'),
                TextInput::make('email')->email(),
                TextInput::make('phone_raw'),
                TextInput::make('phone')->helperText('E.164 format: +201098617164'),
                Toggle::make('is_whatsapp'),
                Select::make('source')->options([
                    'google_maps'   => 'Google Maps',
                    'serper_search' => 'Serper Search',
                    'linkedin'      => 'LinkedIn',
                    'indeed'        => 'Indeed',
                    'wellfound'     => 'Wellfound',
                    'manual'        => 'Manual',
                    'facebook'      => 'Facebook',
                ])->required(),
                Toggle::make('is_active')->default(true),
            ]);
    }
}
