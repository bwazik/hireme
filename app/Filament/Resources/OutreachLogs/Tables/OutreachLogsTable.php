<?php

namespace App\Filament\Resources\OutreachLogs\Tables;

use App\Models\OutreachLog;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Table;

class OutreachLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable(),
                TextColumn::make('contact.email')
                    ->label('Contact'),
                TextColumn::make('channel')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'email'    => 'primary',
                        'whatsapp' => 'success',
                        default    => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'pending' => 'warning',
                        'sent'    => 'success',
                        'failed'  => 'danger',
                        'replied' => 'info',
                        default   => 'gray',
                    })->sortable(),
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('failed_reason')
                    ->limit(40)
                    ->toggleable(),
                IconColumn::make('follow_up_sent')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sent'    => 'Sent',
                        'failed'  => 'Failed',
                        'replied' => 'Replied',
                    ]),
                SelectFilter::make('channel')
                    ->options([
                        'email'    => 'Email',
                        'whatsapp' => 'WhatsApp',
                    ]),
                Filter::make('sent_on')
                    ->schema([
                        DatePicker::make('sent_on')->label('Sent On'),
                    ])
                    ->query(function ($query, array $data) {
                        if (!empty($data['sent_on'])) {
                            $query->whereDate('sent_at', $data['sent_on']);
                        }
                        return $query;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('mark_replied')
                    ->label('Mark Replied')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Textarea::make('reply_content')
                            ->label('Reply content (optional)')
                            ->rows(3),
                    ])
                    ->action(function (OutreachLog $record, array $data): void {
                        $record->update([
                            'status'        => 'replied',
                            'replied_at'    => now(),
                            'reply_content' => $data['reply_content'] ?? null,
                        ]);
                    })
                    ->visible(fn (OutreachLog $record) => $record->status === 'sent'),
            ])
            ->headerActions([
                Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $logs = OutreachLog::with(['company', 'contact', 'campaign'])
                            ->orderByDesc('sent_at')
                            ->get();

                        $filename = 'outreach-logs-' . now()->format('Y-m-d') . '.csv';
                        $headers  = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename={$filename}"];

                        $callback = function () use ($logs) {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['Company', 'Email', 'Phone', 'Channel', 'Status', 'Sent At', 'Replied At', 'Reply Content']);

                            foreach ($logs as $log) {
                                fputcsv($file, [
                                    $log->company->name ?? '',
                                    $log->contact->email ?? '',
                                    $log->contact->phone ?? '',
                                    $log->channel,
                                    $log->status,
                                    $log->sent_at?->format('Y-m-d H:i'),
                                    $log->replied_at?->format('Y-m-d H:i'),
                                    $log->reply_content ?? '',
                                ]);
                            }
                            fclose($file);
                        };

                        return response()->stream($callback, 200, $headers);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
