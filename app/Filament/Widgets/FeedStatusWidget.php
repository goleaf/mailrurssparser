<?php

namespace App\Filament\Widgets;

use App\Models\RssFeed;
use App\Services\RssParserService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class FeedStatusWidget extends TableWidget
{
    protected static ?string $heading = 'Статус RSS';

    protected static ?int $sort = 4;

    protected static ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => RssFeed::query()
                ->with('category')
                ->orderBy('last_parsed_at'))
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('category.name')
                    ->badge(),
                TextColumn::make('last_parsed_at')
                    ->since(),
                TextColumn::make('last_run_new_count'),
                TextColumn::make('consecutive_failures')
                    ->badge()
                    ->color(fn (?int $state): string => ($state ?? 0) > 3 ? 'danger' : 'gray'),
                TextColumn::make('last_error')
                    ->limit(25)
                    ->color(fn (?string $state): string => filled($state) ? 'danger' : 'gray'),
            ])
            ->recordActions([
                Action::make('parse')
                    ->label('Запустить')
                    ->icon('heroicon-o-arrow-path')
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
            ])
            ->paginated(false);
    }
}
