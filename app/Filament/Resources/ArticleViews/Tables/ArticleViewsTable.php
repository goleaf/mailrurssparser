<?php

namespace App\Filament\Resources\ArticleViews\Tables;

use App\Filament\Resources\ArticleViews\ArticleViewResource;
use App\Models\ArticleView;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArticleViewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('viewed_at', 'desc')
            ->columns([
                TextColumn::make('article.title')
                    ->label('Статья')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('article.category.name')
                    ->label('Рубрика')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('device_type')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('referrer_type')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('country_code')
                    ->label('Страна')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('locale')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('referrer_domain')
                    ->label('Домен')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('session_hash')
                    ->searchable()
                    ->sortable()
                    ->limit(18)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('viewed_at')
                    ->dateTime()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('device_type')
                    ->options([
                        'desktop' => 'Desktop',
                        'mobile' => 'Mobile',
                        'tablet' => 'Tablet',
                    ]),
                SelectFilter::make('referrer_type')
                    ->options([
                        'direct' => 'Direct',
                        'search' => 'Search',
                        'social' => 'Social',
                        'internal' => 'Internal',
                    ]),
                SelectFilter::make('locale')
                    ->options(fn (): array => ArticleView::query()
                        ->whereNotNull('locale')
                        ->orderBy('locale')
                        ->pluck('locale', 'locale')
                        ->all()),
            ])
            ->recordActions([
                Action::make('viewRecord')
                    ->label('Просмотр')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (ArticleView $record): string => ArticleViewResource::getUrl('view', ['record' => $record])),
                Action::make('editRecord')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (ArticleView $record): string => ArticleViewResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
