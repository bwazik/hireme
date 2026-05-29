<?php

namespace App\Filament\Resources\OutreachLogs\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OutreachLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('campaign.name')
                    ->label('Campaign'),
                TextEntry::make('contact.name')
                    ->label('Contact'),
                TextEntry::make('company.name')
                    ->label('Company'),
                TextEntry::make('message_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('channel')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('emailAccount.id')
                    ->label('Email account')
                    ->placeholder('-'),
                TextEntry::make('sent_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('failed_reason')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('replied_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('reply_content')
                    ->placeholder('-')
                    ->columnSpanFull(),
                IconEntry::make('follow_up_sent')
                    ->boolean(),
                TextEntry::make('follow_up_sent_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
