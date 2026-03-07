<?php

namespace App\Filament\Resources\RssFeeds\Tables;

use App\Models\RssFeed;
use App\Services\RssParserService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
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
                TextColumn::make('url')
                    ->limit(60)
                    ->copyable(),
                ToggleColumn::make('is_active'),
                TextColumn::make('last_parsed_at')
                    ->dateTime('d M H:i')
                    ->label('Last Parsed')
                    ->since(),
                TextColumn::make('last_run_new_count')
                    ->label('Last New')
                    ->badge()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'success' : 'gray'),
                TextColumn::make('articles_parsed_total')
                    ->label('Total')
                    ->numeric(),
                TextColumn::make('last_error')
                    ->label('Error')
                    ->limit(40)
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
                        $result = $parser->parseFeed($record);

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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
