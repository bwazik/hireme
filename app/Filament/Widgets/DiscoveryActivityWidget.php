<?php

namespace App\Filament\Widgets;

use App\Models\ScrapingJob;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class DiscoveryActivityWidget extends BaseWidget
{
    protected static ?string $heading = 'Discovery Activity';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(ScrapingJob::query())
            ->defaultSort('started_at', 'desc')
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('source')->badge(),
                TextColumn::make('query')->limit(35),
                TextColumn::make('location')->limit(35),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match($state) {
                        'completed' => 'success',
                        'failed'    => 'danger',
                        'running'   => 'warning',
                        default     => 'gray',
                    }),
                TextColumn::make('results_found')->label('Found'),
                TextColumn::make('new_leads')->label('New'),
                TextColumn::make('duplicates')->label('Dups'),
                TextColumn::make('completed_at')->dateTime()->sortable(),
            ])
            ->filters([
                Filter::make('started_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Started From'),
                        DatePicker::make('created_until')
                            ->label('Started Until'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('started_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('started_at', '<=', $date),
                            );
                    })
            ]);
    }
}

