<?php

namespace App\Filament\Resources\RssFeeds\Tables;

use App\Filament\Resources\RssFeeds\RssFeedResource;
use App\Models\RssFeed;
use App\Services\RssParserService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RssFeedsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->toggleable()
                    ->searchable(['title', 'url', 'source_name', 'last_error'])
                    ->sortable(),
                TextColumn::make('source_name')
                    ->label('Источник')
                    ->toggleable()
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('category.name')
                    ->toggleable()
                    ->badge()
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                IconColumn::make('auto_publish')
                    ->label('Автопубл.')
                    ->boolean()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('auto_featured')
                    ->label('Авто-featured')
                    ->boolean()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('articles_count')
                    ->label('Статей')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parse_logs_count')
                    ->label('Логов')
                    ->numeric()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fetch_interval')
                    ->label('Интервал')
                    ->suffix(' мин')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_parsed_at')
                    ->toggleable()
                    ->since()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('next_parse_at')
                    ->label('Следующий запуск')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->since()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_run_new_count')
                    ->badge()
                    ->toggleable()
                    ->searchable()
                    ->sortable()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'success' : 'gray'),
                TextColumn::make('consecutive_failures')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'danger' : 'gray'),
                TextColumn::make('last_error')
                    ->limit(30)
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn (?string $state): string => filled($state) ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active'),
                TernaryFilter::make('auto_publish')
                    ->label('Автопубликация'),
                TernaryFilter::make('auto_featured')
                    ->label('Автовыделение'),
            ])
            ->columnManager(false)
            ->emptyStateIcon(Heroicon::OutlinedRss)
            ->emptyStateHeading('RSS-ленты ещё не настроены')
            ->emptyStateDescription('Добавьте первую ленту, чтобы запустить парсинг и наполнить портал новостями.')
            ->emptyStateActions([
                Action::make('createFeed')
                    ->label('Добавить RSS-ленту')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(RssFeedResource::getUrl('create')),
            ])
            ->recordActions([
                Action::make('parseNow')
                    ->label('Запустить')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->action(function (RssFeed $record, RssParserService $parser): void {
                        $result = $parser->parseFeed($record, 'filament');

                        if (! empty($result['error'])) {
                            Notification::make()
                                ->title('Ошибка парсинга')
                                ->body((string) $result['error'])
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Парсинг завершён')
                            ->body("Новые: {$result['new']}, Пропущено: {$result['skip']}")
                            ->success()
                            ->send();
                    }),
                Action::make('viewRecord')
                    ->label('Просмотр')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (RssFeed $record): string => RssFeedResource::getUrl('view', ['record' => $record])),
                Action::make('editRecord')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (RssFeed $record): string => RssFeedResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
