<?php

namespace App\Filament\Widgets;

use App\Models\OutreachLog;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ReplyInboxWidget extends BaseWidget
{
    protected static ?string $heading = 'Reply Inbox';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OutreachLog::query()
                    ->where('status', 'replied')
                    ->orderByDesc('replied_at')
                    ->limit(20)
            )
            ->columns([
                TextColumn::make('company.name')->label('Company')->searchable(),
                TextColumn::make('channel')->badge(),
                TextColumn::make('reply_content')->limit(80)->label('Reply'),
                TextColumn::make('replied_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('view_full')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Full Reply')
                    ->modalContent(fn (OutreachLog $record) => view(
                        'filament.reply-modal',
                        ['log' => $record]
                    )),
            ]);
    }
}
