<?php

namespace App\Filament\Resources\RssParseLogs;

use App\Filament\Resources\RssParseLogs\Pages\CreateRssParseLog;
use App\Filament\Resources\RssParseLogs\Pages\EditRssParseLog;
use App\Filament\Resources\RssParseLogs\Pages\ListRssParseLogs;
use App\Filament\Resources\RssParseLogs\Pages\ViewRssParseLog;
use App\Filament\Resources\RssParseLogs\Schemas\RssParseLogForm;
use App\Filament\Resources\RssParseLogs\Schemas\RssParseLogInfolist;
use App\Filament\Resources\RssParseLogs\Tables\RssParseLogsTable;
use App\Models\RssParseLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RssParseLogResource extends Resource
{
    protected static ?string $model = RssParseLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return RssParseLogForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RssParseLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RssParseLogsTable::configure($table);
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
            'index' => ListRssParseLogs::route('/'),
            'create' => CreateRssParseLog::route('/create'),
            'view' => ViewRssParseLog::route('/{record}'),
            'edit' => EditRssParseLog::route('/{record}/edit'),
        ];
    }
}
