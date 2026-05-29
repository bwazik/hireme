<?php

namespace App\Filament\Resources\WhatsappInstances\Pages;

use App\Filament\Resources\WhatsappInstances\WhatsappInstanceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatsappInstance extends EditRecord
{
    protected static string $resource = WhatsappInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
