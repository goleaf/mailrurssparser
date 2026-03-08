<?php

namespace App\Filament\Resources\RssFeeds\Tables;

use App\Filament\Resources\RssFeeds\RssFeedResource;
use App\Models\RssFeed;
use App\Services\RssParserService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\ColumnManagerLayout;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Table;

class RssFeedsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('category.name')
                    ->toggleable()
                    ->badge(),
                ToggleColumn::make('is_active')
                    ->toggleable(),
                TextColumn::make('last_parsed_at')
                    ->toggleable()
                    ->since(),
                TextColumn::make('last_run_new_count')
                    ->badge()
                    ->toggleable()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'success' : 'gray'),
                TextColumn::make('articles_parsed_total')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->numeric(),
                TextColumn::make('consecutive_failures')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'danger' : 'gray'),
                TextColumn::make('last_error')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn (?string $state): string => filled($state) ? 'danger' : 'gray'),
            ])
            ->filters([
                //
            ])
            ->reorderableColumns()
            ->columnManagerLayout(ColumnManagerLayout::Modal)
            ->columnManagerColumns(2)
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->columnManagerTriggerAction(
                fn (Action $action): Action => $action
                    ->button()
                    ->label('Вид таблицы')
                    ->icon(Heroicon::AdjustmentsHorizontal)
                    ->modalHeading('Вид таблицы RSS-лент'),
            )
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
                    ->label('Parse Now')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function (RssFeed $record, RssParserService $parser): void {
                        $result = $parser->parseFeed($record, 'filament');

                        if (! empty($result['error'])) {
                            Notification::make()
                                ->title('Parse Failed')
                                ->body((string) $result['error'])
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Parse Complete')
                            ->body("New: {$result['new']}, Skipped: {$result['skip']}")
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
