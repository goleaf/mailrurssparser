<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Articles\Schemas\ArticleForm;
use App\Filament\Resources\Articles\Tables\ArticlesTable;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\Article;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $configurationClass = ArticleResourceConfiguration::class;

    protected static ?string $modelLabel = 'статья';

    protected static ?string $pluralModelLabel = 'статьи';

    protected static ?string $navigationLabel = 'Все статьи';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Editorial;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    public static function form(Schema $schema): Schema
    {
        return ArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArticlesTable::configure($table);
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
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['category', 'subCategory', 'tags', 'rssFeed'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        if ($configuration = static::getConfiguration()) {
            if ($configuration instanceof ArticleResourceConfiguration && filled($configuration->getStatus())) {
                $query->where('status', $configuration->getStatus());
            }
        }

        return $query;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    public static function getNavigationLabel(): string
    {
        $configuration = static::getConfiguration();

        if ($configuration instanceof ArticleResourceConfiguration && filled($configuration->getNavigationLabel())) {
            return $configuration->getNavigationLabel();
        }

        return parent::getNavigationLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $configuration = static::getConfiguration();

        if ($configuration instanceof ArticleResourceConfiguration && filled($configuration->getNavigationGroup())) {
            return $configuration->getNavigationGroup();
        }

        return parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        $configuration = static::getConfiguration();

        if ($configuration instanceof ArticleResourceConfiguration && filled($configuration->getNavigationSort())) {
            return $configuration->getNavigationSort();
        }

        return parent::getNavigationSort();
    }

    public static function getPluralModelLabel(): string
    {
        $configuration = static::getConfiguration();

        if ($configuration instanceof ArticleResourceConfiguration && filled($configuration->getPluralModelLabel())) {
            return $configuration->getPluralModelLabel();
        }

        return parent::getPluralModelLabel();
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $configuration = static::getConfiguration();

        if (! $configuration instanceof ArticleResourceConfiguration) {
            return 'gray';
        }

        return match ($configuration->getStatus()) {
            'pending' => 'warning',
            'published' => 'success',
            default => 'gray',
        };
    }
}
