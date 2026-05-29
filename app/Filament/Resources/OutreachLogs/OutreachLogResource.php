<?php

namespace App\Filament\Resources\OutreachLogs;

use App\Filament\Resources\OutreachLogs\Pages\CreateOutreachLog;
use App\Filament\Resources\OutreachLogs\Pages\EditOutreachLog;
use App\Filament\Resources\OutreachLogs\Pages\ListOutreachLogs;
use App\Filament\Resources\OutreachLogs\Pages\ViewOutreachLog;
use App\Filament\Resources\OutreachLogs\Schemas\OutreachLogForm;
use App\Filament\Resources\OutreachLogs\Schemas\OutreachLogInfolist;
use App\Filament\Resources\OutreachLogs\Tables\OutreachLogsTable;
use App\Models\OutreachLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OutreachLogResource extends Resource
{
    protected static ?string $model = OutreachLog::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Outreach';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return OutreachLogForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OutreachLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OutreachLogsTable::configure($table);
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
            'index' => ListOutreachLogs::route('/'),
            'view' => ViewOutreachLog::route('/{record}'),
        ];
    }
}
