<?php

namespace App\Filament\Resources\OutreachLogs\Pages;

use App\Filament\Resources\OutreachLogs\OutreachLogResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOutreachLog extends ViewRecord
{
    protected static string $resource = OutreachLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
