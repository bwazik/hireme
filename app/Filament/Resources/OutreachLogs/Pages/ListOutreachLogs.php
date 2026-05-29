<?php

namespace App\Filament\Resources\OutreachLogs\Pages;

use App\Filament\Resources\OutreachLogs\OutreachLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOutreachLogs extends ListRecords
{
    protected static string $resource = OutreachLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
