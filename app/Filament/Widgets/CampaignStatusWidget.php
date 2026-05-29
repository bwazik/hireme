<?php

namespace App\Filament\Widgets;

use App\Models\Campaign;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class CampaignStatusWidget extends BaseWidget
{
    protected static ?string $heading = 'Campaign Status';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Campaign::query()->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

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

                TextColumn::make('replied_count')
                    ->label('Replied')
                    ->getStateUsing(fn (Campaign $record) => $record->outreachLogs()->where('status', 'replied')->count()),

                TextColumn::make('daily_email_limit')
                    ->label('Email Limit'),

                TextColumn::make('started_at')
                    ->dateTime()
                    ->label('Started'),
            ])
            ->paginated(false);
    }
}
