<?php

namespace App\Filament\Resources\Contacts\Tables;

use App\Jobs\GenerateMessageJob;
use App\Models\Campaign;
use App\Models\Contact;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Table;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('company.name')->label('Company')->searchable(),
                IconColumn::make('is_whatsapp')->boolean()->label('WA?'),
                TextColumn::make('outreachLogs_count')->counts('outreachLogs')->label('Sent'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Filter::make('created_on')
                    ->schema([
                        DatePicker::make('created_on')->label('Created On'),
                    ])
                    ->query(function ($query, array $data) {
                        if (!empty($data['created_on'])) {
                            $query->whereDate('created_at', $data['created_on']);
                        }
                        return $query;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('blacklist')
                    ->label('Blacklist')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Contact $record) => $record->update(['is_active' => false])),
                Action::make('send_now')
                    ->label('Send Now')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (Contact $record): void {
                        // Use campaign_id=1 for testing (create a test campaign first)
                        $campaign = Campaign::first();
                        if (! $campaign) {
                            Notification::make()
                                ->title('Create a campaign first.')->warning()->send();
                            return;
                        }
                        GenerateMessageJob::dispatch($campaign, $record);
                        Notification::make()
                            ->title('Message generation queued!')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
