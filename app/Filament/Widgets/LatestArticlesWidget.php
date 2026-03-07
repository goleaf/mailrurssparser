<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestArticlesWidget extends TableWidget
{
    protected static ?string $heading = 'Последние статьи';

    protected static ?int $sort = 3;

    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Article::query()
                ->with('category')
                ->published()
                ->latest('published_at')
                ->limit(15))
            ->columns([
                TextColumn::make('title')
                    ->limit(50)
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('category.name')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('published_at')
                    ->since(),
                TextColumn::make('views_count')
                    ->numeric(),
            ])
            ->paginated(false);
    }
}
