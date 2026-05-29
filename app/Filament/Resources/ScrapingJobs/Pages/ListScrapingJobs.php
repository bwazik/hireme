<?php

namespace App\Filament\Resources\ScrapingJobs\Pages;

use App\Filament\Resources\ScrapingJobs\ScrapingJobResource;
use App\Jobs\Discovery\RunDiscoveryJob;
use App\Services\Discovery\Sources\SerperMapsSource;
use App\Services\Discovery\Sources\SerperSearchSource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListScrapingJobs extends ListRecords
{
    protected static string $resource = ScrapingJobResource::class;

    protected function getHeaderActions(): array
    {
        $mapOptions = [];
        foreach (SerperMapsSource::defaultQueries() as $index => $q) {
            $mapOptions["maps_{$index}"] = "[Maps] {$q['country']} ({$q['location']}) — {$q['query']}";
        }

        $searchOptions = [];
        foreach (SerperSearchSource::defaultQueries() as $index => $q) {
            $searchOptions["search_{$index}"] = "[Search] {$q['country']} — {$q['query']}";
        }

        $allOptions = array_merge($mapOptions, $searchOptions);

        return [
            Action::make('runDiscovery')
                ->label('Run Discovery Engine')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->form([
                    Select::make('selected_queries')
                        ->label('Select Built-in Queries to Run')
                        ->options($allOptions)
                        ->multiple()
                        ->searchable()
                        ->columnSpanFull()
                        ->helperText('Choose one or more built-in queries from the system constants. You can mix Maps and Search queries!'),
                    TextInput::make('custom_query')
                        ->label('Or Enter a Custom Query (Optional)')
                        ->placeholder('e.g., AI legal tech startup Zurich')
                        ->helperText('Use this if you want to scrape something not in the built-in list above.'),
                    TextInput::make('custom_location')
                        ->label('Custom Location (Maps Only)')
                        ->placeholder('e.g., Zurich, Switzerland')
                        ->helperText('Required if you provide a custom query for Google Maps.'),
                    Select::make('custom_country')
                        ->label('Custom Country Code')
                        ->options([
                            'EG' => 'Egypt',
                            'DE' => 'Germany',
                            'US' => 'United States',
                            'CH' => 'Switzerland',
                            'GB' => 'United Kingdom',
                            'CA' => 'Canada',
                            'AU' => 'Australia',
                            'NL' => 'Netherlands',
                            'AE' => 'United Arab Emirates',
                            'SG' => 'Singapore',
                            'PL' => 'Poland',
                            'ES' => 'Spain',
                        ])
                        ->default('EG'),
                    Select::make('methods')
                        ->label('Scraping Methods')
                        ->options([
                            'maps'   => 'Google Maps (Places)',
                            'search' => 'Web Search (Serper)',
                            'indeed' => 'Indeed (Job Listings)',
                        ])
                        ->multiple()
                        ->default(['maps', 'search'])
                        ->helperText('Choose which scraping sources to run for this discovery job.'),
                ])
                ->modalWidth('lg')
                ->modalHeading('Configure Discovery Run')
                ->modalDescription('Select multiple built-in queries to run at once, or enter a custom query and location.')
                ->modalSubmitActionLabel('Start Scraping')
                ->action(function (array $data) {
                    RunDiscoveryJob::dispatch(
                        $data['selected_queries'] ?? [],
                        $data['custom_query'] ?? null,
                        $data['custom_location'] ?? null,
                        $data['custom_country'] ?? null,
                        $data['methods'] ?? []
                    )->onQueue('discovery');

                    Notification::make()
                        ->title('Discovery Engine Started')
                        ->body('Queued custom manual discovery job in the background.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
