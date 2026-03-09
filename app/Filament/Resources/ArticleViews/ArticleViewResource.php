<?php

namespace App\Filament\Resources\ArticleViews;

use App\Filament\Resources\ArticleViews\Pages\CreateArticleView;
use App\Filament\Resources\ArticleViews\Pages\EditArticleView;
use App\Filament\Resources\ArticleViews\Pages\ListArticleViews;
use App\Filament\Resources\ArticleViews\Pages\ViewArticleView;
use App\Filament\Resources\ArticleViews\Schemas\ArticleViewForm;
use App\Filament\Resources\ArticleViews\Schemas\ArticleViewInfolist;
use App\Filament\Resources\ArticleViews\Tables\ArticleViewsTable;
use App\Models\ArticleView;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ArticleViewResource extends Resource
{
    protected static ?string $model = ArticleView::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
