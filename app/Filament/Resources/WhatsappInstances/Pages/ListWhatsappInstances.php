<?php

namespace App\Filament\Resources\WhatsappInstances\Pages;

use App\Filament\Resources\WhatsappInstances\WhatsappInstanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsappInstances extends ListRecords
{
    protected static string $resource = WhatsappInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
