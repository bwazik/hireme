<?php

namespace App\Filament\Resources\ScrapingJobs\Pages;

use App\Filament\Resources\ScrapingJobs\ScrapingJobResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditScrapingJob extends EditRecord
{
    protected static string $resource = ScrapingJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
