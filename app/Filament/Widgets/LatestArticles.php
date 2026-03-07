<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestArticles extends TableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Article::query()
                ->published()
                ->latest('published_at')
                ->limit(10))
            ->columns([
                TextColumn::make('title')
                    ->limit(50),
                TextColumn::make('category.name')
                    ->badge(),
                TextColumn::make('published_at')
                    ->since(),
                TextColumn::make('views_count')
                    ->numeric(),
            ])
            ->recordUrl(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record]))
            ->paginated(false);
    }
}
