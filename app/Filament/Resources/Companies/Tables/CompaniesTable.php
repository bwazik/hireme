<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Jobs\Discovery\EnrichCompanyJob;
use App\Models\Company;
use App\Services\AI\MessageGenerator;
use App\Services\Discovery\Extractors\MetaExtractor;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('industry')->badge(),
                TextColumn::make('country')->sortable(),
                TextColumn::make('city')->searchable(),
                TextColumn::make('contacts_count')->counts('contacts')->label('Contacts'),
                TextColumn::make('source')->badge(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('contact_status')
                    ->label('Contacts')
                    ->badge()
                    ->colors([
                        'success' => 'has_contacts',
                        'warning' => 'pending_enrichment',
                        'danger'  => 'manual_required',
                        'gray'    => 'no_website',
                    ])
                    ->icons([
                        'heroicon-o-check-circle'       => 'has_contacts',
                        'heroicon-o-clock'              => 'pending_enrichment',
                        'heroicon-o-exclamation-circle' => 'manual_required',
                        'heroicon-o-globe-alt'          => 'no_website',
                    ]),
                TextColumn::make('needs_description')
                    ->label('Fetch Desc?')
                    ->getStateUsing(function ($record) {
                        if (blank($record->website)) {
                            return 'No Website';
                        }
                        return blank($record->description) ? 'Needs Fetch' : 'Done';
                    })
                    ->badge()
                    ->colors([
                        'warning' => 'Needs Fetch',
                        'success' => 'Done',
                        'gray'    => 'No Website',
                    ])
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderByRaw("CASE WHEN website IS NOT NULL AND (description IS NULL OR description = '') THEN 0 ELSE 1 END " . $direction);
                    }),
                TextColumn::make('needs_enrichment')
                    ->label('Re-enrich?')
                    ->getStateUsing(function ($record) {
                        if (blank($record->website)) {
                            return 'No Website';
                        }
                        return in_array($record->contact_status, ['manual_required', 'no_website']) ? 'Needs Enrich' : 'Done';
                    })
                    ->badge()
                    ->colors([
                        'warning' => 'Needs Enrich',
                        'success' => 'Done',
                        'gray'    => 'No Website',
                    ])
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderByRaw("CASE WHEN website IS NOT NULL AND contact_status IN ('manual_required', 'no_website') THEN 0 ELSE 1 END " . $direction);
                    }),
            ])
            ->filters([
                SelectFilter::make('industry')
                    ->multiple()
                    ->options([
                        'software_house' => 'Software House', 'saas' => 'SaaS', 'mobile_dev' => 'Mobile Dev',
                        'fintech' => 'FinTech', 'e_commerce' => 'E-Commerce',
                        'digital_agency' => 'Digital Agency', 'ai_company' => 'AI Company', 'web_design' => 'Web Design', 'other' => 'Other',
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['values'])) {
                            return $query;
                        }
                        return $query->where(function ($q) use ($data) {
                            foreach ($data['values'] as $value) {
                                $q->orWhereJsonContains('industry', $value);
                            }
                        });
                    }),
                SelectFilter::make('source')->options([
                    'google_maps' => 'Google Maps', 'serper_search' => 'Serper Search',
                    'linkedin' => 'LinkedIn', 'indeed' => 'Indeed',
                    'wellfound' => 'Wellfound', 'manual' => 'Manual', 'facebook' => 'Facebook',
                ]),
                SelectFilter::make('country'),
                TernaryFilter::make('is_active'),
                SelectFilter::make('contact_status')
                    ->options([
                        'has_contacts'       => 'Has Contacts',
                        'pending_enrichment' => 'Pending Enrichment',
                        'manual_required'    => 'Needs Manual Work',
                        'no_website'         => 'No Website',
                    ]),
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
                Action::make('reEnrich')
                    ->label('Re-run Enrichment')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => in_array($record->contact_status, ['manual_required', 'no_website']))
                    ->action(function ($record) {
                        if (!$record->website) {
                            Notification::make()
                                ->title('No website — add one first')
                                ->warning()
                                ->send();
                            return;
                        }

                        $record->update(['contact_status' => 'pending_enrichment']);
                        EnrichCompanyJob::dispatch($record)->onQueue('discovery');

                        Notification::make()
                            ->title('Enrichment job queued')
                            ->body('Check back in a minute.')
                            ->success()
                            ->send();
                    }),
                Action::make('fetchDescription')
                    ->label('Fetch Description')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn ($record) => filled($record->website) && blank($record->description))
                    ->action(function ($record) {
                        $extractor = new MetaExtractor();
                        $meta = $extractor->extract($record->website);

                        if ($meta['description']) {
                            $record->update(['description' => $meta['description']]);

                            Notification::make()
                                ->title('Description fetched')
                                ->body($meta['description'])
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Nothing found')
                                ->body('The website did not return a usable description. Add one manually.')
                                ->warning()
                                ->send();
                        }
                    }),
                Action::make('blacklist')
                    ->label('Blacklist')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Company $record) => $record->update(['is_active' => false])),
                Action::make('preview_message')
                    ->label('AI Preview')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->modalHeading('AI Message Preview')
                    ->modalContent(function (Company $record) {
                        $contact = $record->contacts()->where('is_active', true)->first();

                        if (! $contact) {
                            return view('filament.ai-preview-modal', [
                                'preview' => null,
                                'error' => 'No contacts found for this company.'
                            ]);
                        }

                        $preview = (new MessageGenerator())->preview($contact);

                        return view('filament.ai-preview-modal', [
                            'preview' => $preview,
                            'error' => null
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('fetchDescriptions')
                        ->label('Fetch Descriptions')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $extractor = new \App\Services\Discovery\Extractors\MetaExtractor();
                            $updated   = 0;

                            foreach ($records as $record) {
                                if (!$record->website || $record->description) {
                                    continue;
                                }

                                $meta = $extractor->extract($record->website);

                                if ($meta['description']) {
                                    $record->update(['description' => $meta['description']]);
                                    $updated++;
                                }
                            }

                            Notification::make()
                                ->title("Updated {$updated} companies")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('bulkReEnrich')
                        ->label('Re-run Enrichment')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function (Collection $records) {
                            $queued = 0;

                            foreach ($records as $record) {
                                if ($record->website) {
                                    $record->update(['contact_status' => 'pending_enrichment']);
                                    EnrichCompanyJob::dispatch($record)->onQueue('discovery');
                                    $queued++;
                                }
                            }

                            Notification::make()
                                ->title("Queued {$queued} enrichment jobs")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->headerActions([
                Action::make('enrich_all')
                    ->label('Re-enrich All')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Re-enrich All Active Companies')
                    ->modalDescription('This will queue enrichment (email/phone scraping) for all active companies that have a website but no contacts yet (status is Manual Required or No Website).')
                    ->action(function () {
                        $companies = Company::where('is_active', true)
                            ->whereNotNull('website')
                            ->whereIn('contact_status', ['manual_required', 'no_website'])
                            ->get();

                        $queued = 0;
                        foreach ($companies as $company) {
                            $company->update(['contact_status' => 'pending_enrichment']);
                            EnrichCompanyJob::dispatch($company)->onQueue('discovery');
                            $queued++;
                        }

                        Notification::make()
                            ->title("Queued {$queued} enrichment jobs")
                            ->success()
                            ->send();
                    }),

                Action::make('fetch_all_descriptions')
                    ->label('Fetch All Descriptions')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Fetch All Missing Descriptions')
                    ->modalDescription('This will fetch descriptions in the background for all active companies that have a website but no description currently.')
                    ->action(function () {
                        $companies = Company::where('is_active', true)
                            ->whereNotNull('website')
                            ->where(fn ($q) => $q->whereNull('description')->orWhere('description', ''))
                            ->get();

                        $extractor = new MetaExtractor();
                        $updated   = 0;

                        foreach ($companies as $company) {
                            $meta = $extractor->extract($company->website);
                            if (!empty($meta['description'])) {
                                $company->update(['description' => $meta['description']]);
                                $updated++;
                            }
                        }

                        Notification::make()
                            ->title("Updated {$updated} company descriptions")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
