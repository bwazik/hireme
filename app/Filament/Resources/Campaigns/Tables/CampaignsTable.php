<?php

namespace App\Filament\Resources\Campaigns\Tables;

use App\Jobs\BuildCampaignQueueJob;
use App\Models\Campaign;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft'     => 'secondary',
                        'active'    => 'success',
                        'paused'    => 'warning',
                        'completed' => 'primary',
                        default     => 'gray',
                    }),

                TextColumn::make('outreach_logs_count')
                    ->counts('outreachLogs')
                    ->label('Sent'),

                TextColumn::make('daily_email_limit')
                    ->label('Email Limit'),

                TextColumn::make('daily_whatsapp_limit')
                    ->label('WA Limit'),

                IconColumn::make('follow_up_enabled')
                    ->boolean(),

                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'active'    => 'Active',
                        'paused'    => 'Paused',
                        'completed' => 'Completed',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Campaign $record) => in_array($record->status, ['draft', 'paused']))
                    ->action(function (Campaign $record): void {
                        $record->update(['status' => 'active', 'started_at' => now()]);
                        BuildCampaignQueueJob::dispatch($record);
                        Notification::make()
                            ->title('Campaign activated! Queue is building.')
                            ->success()
                            ->send();
                    }),

                Action::make('pause')
                    ->label('Pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Campaign $record) => $record->status === 'active')
                    ->action(fn (Campaign $record) => $record->update(['status' => 'paused', 'paused_at' => now()])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
