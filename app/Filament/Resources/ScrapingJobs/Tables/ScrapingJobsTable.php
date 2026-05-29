<?php

namespace App\Filament\Resources\ScrapingJobs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScrapingJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'serper_maps'   => 'info',
                        'serper_search' => 'primary',
                        'indeed'        => 'warning',
                        default         => 'gray',
                    }),

                TextColumn::make('query')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('location')
                    ->limit(30),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'running'   => 'warning',
                        'completed' => 'success',
                        'failed'    => 'danger',
                        default     => 'gray',
                    }),

                TextColumn::make('results_found')
                    ->label('Found')
                    ->numeric(),

                TextColumn::make('new_leads')
                    ->label('New')
                    ->numeric(),

                TextColumn::make('duplicates')
                    ->label('Dups')
                    ->numeric(),

                TextColumn::make('error_message')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'running'   => 'Running',
                        'completed' => 'Completed',
                        'failed'    => 'Failed',
                    ]),

                SelectFilter::make('source')
                    ->options([
                        'serper_maps'   => 'Serper Maps',
                        'serper_search' => 'Serper Search',
                        'indeed'        => 'Indeed',
                    ]),
            ])
            ->defaultSort('completed_at', 'desc');
    }
}
