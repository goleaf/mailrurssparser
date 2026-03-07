<?php

namespace App\Filament\Resources\RssFeeds\Tables;

use App\Models\RssFeed;
use App\Services\RssParserService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class RssFeedsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->badge(),
                ToggleColumn::make('is_active'),
                TextColumn::make('last_parsed_at')
                    ->since(),
                TextColumn::make('last_run_new_count')
                    ->badge()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'success' : 'gray'),
                TextColumn::make('articles_parsed_total')
                    ->numeric(),
                TextColumn::make('consecutive_failures')
                    ->badge()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'danger' : 'gray'),
                TextColumn::make('last_error')
                    ->limit(30)
                    ->color(fn (?string $state): string => filled($state) ? 'danger' : 'gray'),
            ])
            ->filters([
                //
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
