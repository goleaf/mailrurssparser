<?php

namespace App\Filament\Resources\Bookmarks;

use App\Filament\Resources\Bookmarks\Pages\CreateBookmark;
use App\Filament\Resources\Bookmarks\Pages\EditBookmark;
use App\Filament\Resources\Bookmarks\Pages\ListBookmarks;
use App\Filament\Resources\Bookmarks\Pages\ViewBookmark;
use App\Filament\Resources\Bookmarks\Schemas\BookmarkForm;
use App\Filament\Resources\Bookmarks\Schemas\BookmarkInfolist;
use App\Filament\Resources\Bookmarks\Tables\BookmarksTable;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\Bookmark;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BookmarkResource extends Resource
{
    protected static ?string $model = Bookmark::class;

    protected static ?string $modelLabel = 'закладка';

    protected static ?string $pluralModelLabel = 'закладки';

    protected static ?string $navigationLabel = 'Закладки';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Audience;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return BookmarkForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BookmarkInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookmarksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forAdminIndex();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookmarks::route('/'),
            'create' => CreateBookmark::route('/create'),
            'view' => ViewBookmark::route('/{record}'),
            'edit' => EditBookmark::route('/{record}/edit'),
        ];
    }
}
