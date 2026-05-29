<?php

namespace App\Filament\Resources\ScrapingJobs\Pages;

use App\Filament\Resources\ScrapingJobs\ScrapingJobResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScrapingJob extends CreateRecord
{
    protected static string $resource = ScrapingJobResource::class;
}
