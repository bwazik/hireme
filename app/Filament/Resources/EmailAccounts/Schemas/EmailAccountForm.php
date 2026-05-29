<?php

namespace App\Filament\Resources\EmailAccounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EmailAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')->email()->required(),
                TextInput::make('password')->password()->required()
                    ->helperText('Will be encrypted with Laravel encrypt()'),
                TextInput::make('sender_name')->default('Abdullah Mohamed'),
                TextInput::make('smtp_host')->default('smtp.gmail.com'),
                TextInput::make('smtp_port')->numeric()->default(587),
                Select::make('smtp_encryption')->options(['tls'=>'TLS','ssl'=>'SSL'])->default('tls'),
                TextInput::make('daily_limit')->numeric()->default(80),
                Toggle::make('is_active')->default(true),
            ]);
    }
}
