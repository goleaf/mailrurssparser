<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Models\Article;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Support\Colors\Color;
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
                    ->searchable()
                    ->limit(55),
                TextColumn::make('category.name')
                    ->badge()
                    ->color(fn (Article $record): array|string => filled($record->category?->color) ? Color::generatePalette($record->category->color) : 'gray'),
                TextColumn::make('content_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'news' => 'Новости',
                        'article' => 'Статья',
                        'opinion' => 'Мнение',
                        'analysis' => 'Аналитика',
                        'interview' => 'Интервью',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'analysis' => 'info',
                        'opinion' => 'warning',
                        'interview' => 'success',
                        'article' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Черновик',
                        'pending' => 'На модерации',
                        'published' => 'Опубликовано',
                        'archived' => 'Архив',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'published' => 'success',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Реком.'),
                IconColumn::make('is_breaking')
                    ->boolean()
                    ->label('Срочн.'),
                IconColumn::make('is_pinned')
                    ->boolean()
                    ->label('Закреп.'),
                TextColumn::make('importance')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 9 => 'danger',
                        $state >= 7 => 'warning',
                        $state >= 5 => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('views_count')
                    ->sortable()
                    ->numeric(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Черновик',
                        'pending' => 'На модерации',
                        'published' => 'Опубликовано',
                        'archived' => 'Архив',
                    ]),
                SelectFilter::make('content_type')
                    ->options([
                        'news' => 'Новости',
                        'article' => 'Статья',
                        'opinion' => 'Мнение',
                        'analysis' => 'Аналитика',
                        'interview' => 'Интервью',
                    ]),
                TernaryFilter::make('is_featured')
                    ->label('Рекомендуемая'),
                TernaryFilter::make('is_breaking')
                    ->label('Срочная'),
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
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publishSelected')
                        ->label('Опубликовать')
                        ->action(function (Collection $records): void {
                            $records->each(function (Article $record): void {
                                $record->update(['status' => 'published']);
                            });
                        }),
                    BulkAction::make('draftSelected')
                        ->label('В черновик')
                        ->color('gray')
                        ->action(function (Collection $records): void {
                            $records->each(function (Article $record): void {
                                $record->update(['status' => 'draft']);
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
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
