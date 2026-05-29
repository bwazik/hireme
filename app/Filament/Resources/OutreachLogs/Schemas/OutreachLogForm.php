<?php

namespace App\Filament\Resources\OutreachLogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OutreachLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('campaign_id')
                    ->relationship('campaign', 'name')
                    ->required(),
                Select::make('contact_id')
                    ->relationship('contact', 'name')
                    ->required(),
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->required(),
                TextInput::make('message_id')
                    ->numeric(),
                Select::make('channel')
                    ->options(['email' => 'Email', 'whatsapp' => 'Whatsapp'])
                    ->required(),
                Select::make('status')
                    ->options(['pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed', 'replied' => 'Replied'])
                    ->default('pending')
                    ->required(),
                Select::make('email_account_id')
                    ->relationship('emailAccount', 'id'),
                DateTimePicker::make('sent_at'),
                Textarea::make('failed_reason')
                    ->columnSpanFull(),
                DateTimePicker::make('replied_at'),
                Textarea::make('reply_content')
                    ->columnSpanFull(),
                Toggle::make('follow_up_sent')
                    ->required(),
                DateTimePicker::make('follow_up_sent_at'),
            ]);
    }
}
