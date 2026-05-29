<?php

namespace App\Filament\Resources\ScrapingJobs;

use App\Filament\Resources\ScrapingJobs\Pages\ListScrapingJobs;
use App\Filament\Resources\ScrapingJobs\Tables\ScrapingJobsTable;
use App\Models\ScrapingJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ScrapingJobResource extends Resource
{
    protected static ?string $model = ScrapingJob::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Discovery';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static bool $canCreate = false;

    public static function table(Table $table): Table
    {
        return ScrapingJobsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScrapingJobs::route('/'),
        ];
    }
}
