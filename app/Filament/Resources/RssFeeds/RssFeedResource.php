<?php

namespace App\Filament\Resources\RssFeeds;

use App\Filament\Support\AdminNavigationGroup;
use App\Filament\Resources\RssFeeds\Pages\CreateRssFeed;
use App\Filament\Resources\RssFeeds\Pages\EditRssFeed;
use App\Filament\Resources\RssFeeds\Pages\ListRssFeeds;
use App\Filament\Resources\RssFeeds\Schemas\RssFeedForm;
use App\Filament\Resources\RssFeeds\Tables\RssFeedsTable;
use App\Models\RssFeed;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RssFeedResource extends Resource
{
    protected static ?string $model = RssFeed::class;

    protected static ?string $modelLabel = 'RSS-лента';

    protected static ?string $pluralModelLabel = 'RSS-ленты';

    protected static ?string $navigationLabel = 'RSS-ленты';

    protected static string | UnitEnum | null $navigationGroup = AdminNavigationGroup::Ingestion;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    public static function form(Schema $schema): Schema
    {
        return RssFeedForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RssFeedsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('category');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRssFeeds::route('/'),
            'create' => CreateRssFeed::route('/create'),
            'edit' => EditRssFeed::route('/{record}/edit'),
        ];
    }
}
