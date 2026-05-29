<?php

namespace App\Filament\Widgets;

use App\Jobs\Discovery\EnrichCompanyJob;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OrphanCompaniesWidget extends BaseWidget
{
    protected static ?string $heading = '⚠️ Companies Without Contacts';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 4;

    // Only show this widget when there are actually orphan companies
    public static function canView(): bool
    {
        return Company::whereIn('contact_status', ['manual_required', 'no_website'])->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Company::query()
                    ->whereIn('contact_status', ['manual_required', 'no_website'])
                    ->orderByDesc('created_at')
            )
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('country')->badge(),
                TextColumn::make('industry')->badge()->color('info'),
                IconColumn::make('website')
                    ->label('Has Website')
                    ->boolean()
                    ->getStateUsing(fn ($record) => filled($record->website)),
                TextColumn::make('contact_status')
                    ->badge()
                    ->color(fn ($state) => $state === 'manual_required' ? 'danger' : 'gray'),
                TextColumn::make('created_at')->since()->label('Discovered'),
            ])
            ->recordActions([
                // Open website in browser to look manually
                Action::make('openWebsite')
                    ->label('Open Site')
                    ->icon('heroicon-o-globe-alt')
                    ->url(fn ($record) => $record->website ?? '#')
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => filled($record->website)),

                // Quick re-run enrichment
                Action::make('reEnrich')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => filled($record->website))
                    ->action(function ($record) {
                        $record->update(['contact_status' => 'pending_enrichment']);
                        EnrichCompanyJob::dispatch($record)->onQueue('discovery');

                        Notification::make()
                            ->title('Enrichment queued')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                // Bulk retry for all selected orphan companies
                BulkAction::make('bulkReEnrich')
                    ->label('Retry Enrichment')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function ($records) {
                        $queued = 0;
                        foreach ($records as $record) {
                            if (!$record->website) continue;
                            $record->update(['contact_status' => 'pending_enrichment']);
                            EnrichCompanyJob::dispatch($record)
                                ->onQueue('discovery')
                                ->delay(now()->addSeconds($queued * 15)); // stagger 15s each
                            $queued++;
                        }
                        Notification::make()
                            ->title("Queued {$queued} enrichment jobs")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('No orphan companies')
            ->emptyStateDescription('All discovered companies have at least one contact.');
    }
}
