<?php

namespace App\Filament\Resources\WhatsappInstances;

use App\Filament\Resources\WhatsappInstances\Pages\CreateWhatsappInstance;
use App\Filament\Resources\WhatsappInstances\Pages\EditWhatsappInstance;
use App\Filament\Resources\WhatsappInstances\Pages\ListWhatsappInstances;
use App\Filament\Resources\WhatsappInstances\Schemas\WhatsappInstanceForm;
use App\Filament\Resources\WhatsappInstances\Tables\WhatsappInstancesTable;
use App\Models\WhatsappInstance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WhatsappInstanceResource extends Resource
{
    protected static ?string $model = WhatsappInstance::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Outreach';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    public static function form(Schema $schema): Schema
    {
        return WhatsappInstanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsappInstancesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappInstances::route('/'),
            'create' => CreateWhatsappInstance::route('/create'),
            'edit' => EditWhatsappInstance::route('/{record}/edit'),
        ];
    }
}
