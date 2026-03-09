<?php

namespace App\Filament\Resources\ArticleViews;

use BackedEnum;
use App\Filament\Resources\ArticleViews\Pages\CreateArticleView;
use App\Filament\Resources\ArticleViews\Pages\EditArticleView;
use App\Filament\Resources\ArticleViews\Pages\ListArticleViews;
use App\Filament\Resources\ArticleViews\Pages\ViewArticleView;
use App\Filament\Resources\ArticleViews\Schemas\ArticleViewForm;
use App\Filament\Resources\ArticleViews\Schemas\ArticleViewInfolist;
use App\Filament\Resources\ArticleViews\Tables\ArticleViewsTable;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\ArticleView;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ArticleViewResource extends Resource
{
    protected static ?string $model = ArticleView::class;

    protected static ?string $modelLabel = 'просмотр статьи';

    protected static ?string $pluralModelLabel = 'просмотры статей';

    protected static ?string $navigationLabel = 'Просмотры статей';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Audience;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ArticleViewForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ArticleViewInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArticleViewsTable::configure($table);
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
            'index' => ListArticleViews::route('/'),
            'create' => CreateArticleView::route('/create'),
            'view' => ViewArticleView::route('/{record}'),
            'edit' => EditArticleView::route('/{record}/edit'),
        ];
    }
}
