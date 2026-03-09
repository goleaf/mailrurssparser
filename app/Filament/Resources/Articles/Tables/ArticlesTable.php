<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Models\Tag;
use App\Services\ArticleContentType;
use App\Services\ArticleStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(['title', 'slug', 'author', 'source_name'])
                    ->sortable()
                    ->toggleable()
                    ->limit(55)
                    ->description(fn (Article $record): ?string => filled($record->author) || filled($record->source_name)
                        ? collect([$record->author, $record->source_name])->filter()->implode(' · ')
                        : null),
                TextColumn::make('category.name')
                    ->badge()
                    ->toggleable()
                    ->searchable()
                    ->sortable()
                    ->color(fn (Article $record): array|string => filled($record->category?->color) ? Color::generatePalette($record->category->color) : 'gray'),
                TextColumn::make('subCategory.name')
                    ->label('Подкатегория')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('rssFeed.title')
                    ->label('Лента')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(28)
                    ->placeholder('Ручной материал'),
                TextColumn::make('editor.name')
                    ->label('Редактор')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Не назначен'),
                TextColumn::make('tags_summary')
                    ->label('Теги')
                    ->state(fn (Article $record): string => $record->tags->take(3)->pluck('name')->implode(', '))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'tags',
                        fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%'),
                    ))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Tag::query()
                            ->selectRaw('min(tags.name)')
                            ->join('article_tag', 'article_tag.tag_id', '=', 'tags.id')
                            ->whereColumn('article_tag.article_id', 'articles.id'),
                        $direction,
                    ))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Без тегов'),
                TextColumn::make('content_type')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->formatStateUsing(fn (ArticleContentType|string|null $state): string => ArticleContentType::fromValue($state)?->getLabel() ?? (string) $state)
                    ->color(fn (ArticleContentType|string|null $state): string|array|null => ArticleContentType::fromValue($state)?->getColor() ?? 'gray'),
                TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->formatStateUsing(fn (ArticleStatus|string|null $state): string => ArticleStatus::fromValue($state)?->getLabel() ?? (string) $state)
                    ->color(fn (ArticleStatus|string|null $state): string|array|null => ArticleStatus::fromValue($state)?->getColor() ?? 'gray'),
                IconColumn::make('is_featured')
                    ->boolean()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Реком.'),
                IconColumn::make('is_breaking')
                    ->boolean()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Срочн.'),
                IconColumn::make('is_pinned')
                    ->boolean()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Закреп.'),
                TextColumn::make('importance')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 9 => 'danger',
                        $state >= 7 => 'warning',
                        $state >= 5 => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('views_count')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->numeric(),
                TextColumn::make('bookmarked_by_count')
                    ->label('Закладки')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->numeric(),
                TextColumn::make('related_articles_count')
                    ->label('Связи')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->numeric(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('sub_category_id')
                    ->relationship('subCategory', 'name')
                    ->label('Подкатегория')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('rss_feed_id')
                    ->relationship('rssFeed', 'title')
                    ->label('RSS-лента')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('editor_id')
                    ->relationship('editor', 'name')
                    ->label('Редактор')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options(ArticleStatus::class),
                SelectFilter::make('content_type')
                    ->options(ArticleContentType::class),
                Filter::make('search_fields')
                    ->label('Поиск по полям')
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок'),
                        TextInput::make('author')
                            ->label('Автор'),
                        TextInput::make('source_name')
                            ->label('Источник'),
                        TextInput::make('slug')
                            ->label('Slug'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['title'] ?? null),
                                fn (Builder $query): Builder => $query->where('title', 'like', '%'.$data['title'].'%'),
                            )
                            ->when(
                                filled($data['author'] ?? null),
                                fn (Builder $query): Builder => $query->where('author', 'like', '%'.$data['author'].'%'),
                            )
                            ->when(
                                filled($data['source_name'] ?? null),
                                fn (Builder $query): Builder => $query->where('source_name', 'like', '%'.$data['source_name'].'%'),
                            )
                            ->when(
                                filled($data['slug'] ?? null),
                                fn (Builder $query): Builder => $query->where('slug', 'like', '%'.$data['slug'].'%'),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        return collect([
                            filled($data['title'] ?? null) ? 'Заголовок: '.$data['title'] : null,
                            filled($data['author'] ?? null) ? 'Автор: '.$data['author'] : null,
                            filled($data['source_name'] ?? null) ? 'Источник: '.$data['source_name'] : null,
                            filled($data['slug'] ?? null) ? 'Slug: '.$data['slug'] : null,
                        ])->filter()->values()->all();
                    }),
                TernaryFilter::make('is_featured')
                    ->label('Рекомендуемая'),
                TernaryFilter::make('is_breaking')
                    ->label('Срочная'),
                TernaryFilter::make('is_pinned')
                    ->label('Закреплена'),
                TernaryFilter::make('is_editors_choice')
                    ->label('Выбор редакции'),
                TernaryFilter::make('is_sponsored')
                    ->label('Партнёрская'),
                Filter::make('published_at')
                    ->schema([
                        DatePicker::make('published_from')
                            ->label('С'),
                        DatePicker::make('published_until')
                            ->label('По'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['published_from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('published_at', '>=', $date),
                            )
                            ->when(
                                $data['published_until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('published_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['published_from'] ?? null)) {
                            $indicators[] = 'Опубликовано от '.$data['published_from'];
                        }

                        if (filled($data['published_until'] ?? null)) {
                            $indicators[] = 'Опубликовано до '.$data['published_until'];
                        }

                        return $indicators;
                    }),
                Filter::make('importance_min')
                    ->schema([
                        TextInput::make('value')
                            ->label('Важность от')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            filled($data['value'] ?? null),
                            fn (Builder $query): Builder => $query->where('importance', '>=', (int) $data['value']),
                        );
                    })
                    ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null) ? 'Важность от '.$data['value'] : null),
                TrashedFilter::make(),
            ])
            ->defaultSort('published_at', 'desc')
            ->columnManager(false)
            ->recordActions([
                Action::make('editRecord')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publishSelected')
                        ->label('Опубликовать')
                        ->action(function (Collection $records): void {
                            $records->each(function (Article $record): void {
                                $record->update(['status' => ArticleStatus::Published->value]);
                            });
                        }),
                    BulkAction::make('draftSelected')
                        ->label('В черновик')
                        ->color('gray')
                        ->action(function (Collection $records): void {
                            $records->each(function (Article $record): void {
                                $record->update(['status' => ArticleStatus::Draft->value]);
                            });
                        }),
                    BulkAction::make('featureSelected')
                        ->label('Отметить как featured')
                        ->color('info')
                        ->action(function (Collection $records): void {
                            $records->each(function (Article $record): void {
                                $record->update(['is_featured' => true]);
                            });
                        }),
                    BulkAction::make('unfeatureSelected')
                        ->label('Снять featured')
                        ->color('gray')
                        ->action(function (Collection $records): void {
                            $records->each(function (Article $record): void {
                                $record->update(['is_featured' => false]);
                            });
                        }),
                    BulkAction::make('markBreakingSelected')
                        ->label('Отметить как breaking')
                        ->color('danger')
                        ->action(function (Collection $records): void {
                            $records->each(function (Article $record): void {
                                $record->update(['is_breaking' => true]);
                            });
                        }),
                    BulkAction::make('clearBreakingSelected')
                        ->label('Снять breaking')
                        ->color('gray')
                        ->action(function (Collection $records): void {
                            $records->each(function (Article $record): void {
                                $record->update(['is_breaking' => false]);
                            });
                        }),
                ]),
            ]);
    }
}
