<?php

namespace App\Filament\Resources\RssFeeds;

use BackedEnum;
use App\Filament\Resources\RssFeeds\Pages\CreateRssFeed;
use App\Filament\Resources\RssFeeds\Pages\EditRssFeed;
use App\Filament\Resources\RssFeeds\Pages\ListRssFeeds;
use App\Filament\Resources\RssFeeds\Pages\ViewRssFeed;
use App\Filament\Resources\RssFeeds\Schemas\RssFeedForm;
use App\Filament\Resources\RssFeeds\Tables\RssFeedsTable;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn as RepeatableTableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class RssFeedResource extends Resource
{
    protected static ?string $model = RssFeed::class;

    protected static ?string $modelLabel = 'RSS-лента';

    protected static ?string $pluralModelLabel = 'RSS-ленты';

    protected static ?string $navigationLabel = 'RSS-ленты';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Ingestion;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return RssFeedForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Обзор ленты')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')
                            ->weight('bold'),
                        TextEntry::make('category.name')
                            ->badge()
                            ->placeholder('Без категории'),
                        TextEntry::make('url')
                            ->columnSpanFull()
                            ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                            ->copyable(),
                        TextEntry::make('source_name'),
                        TextEntry::make('fetch_interval')
                            ->suffix(' мин'),
                        TextEntry::make('is_active')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Активна' : 'Отключена')
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('auto_publish')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Автопубликация' : 'Ручная модерация')
                            ->color(fn (bool $state): string => $state ? 'info' : 'warning'),
                        TextEntry::make('auto_featured')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Автовыделение' : 'Обычный импорт')
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                    ]),
                Section::make('Состояние парсера')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('last_parsed_at')
                            ->label('Последний запуск')
                            ->since()
                            ->placeholder('Никогда'),
                        TextEntry::make('next_parse_at')
                            ->label('Следующий запуск')
                            ->since()
                            ->placeholder('Ожидает расписания'),
                        TextEntry::make('articles_parsed_total')
                            ->label('Импортировано статей')
                            ->numeric(),
                        TextEntry::make('last_run_new_count')
                            ->label('Новых за последний запуск')
                            ->badge()
                            ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'success' : 'gray'),
                        TextEntry::make('last_run_skip_count')
                            ->label('Пропущено за последний запуск')
                            ->numeric(),
                        TextEntry::make('consecutive_failures')
                            ->label('Сбоев подряд')
                            ->badge()
                            ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'danger' : 'gray'),
                        TextEntry::make('last_error')
                            ->columnSpanFull()
                            ->placeholder('Свежих ошибок нет.'),
                    ]),
                Section::make('Переопределения ленты')
                    ->schema([
                        EmptyState::make('Переопределения не заданы')
                            ->description('Сейчас эта лента использует общие настройки импортера.')
                            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                            ->visible(fn (RssFeed $record): bool => blank($record->extra_settings)),
                        KeyValueEntry::make('extra_settings')
                            ->columnSpanFull()
                            ->hidden(fn (RssFeed $record): bool => blank($record->extra_settings)),
                    ]),
                Section::make('Последние запуски парсинга')
                    ->schema([
                        EmptyState::make('Запусков парсинга ещё не было')
                            ->description('Запустите эту ленту вручную или дождитесь первого запуска по расписанию.')
                            ->icon(Heroicon::OutlinedClock)
                            ->visible(fn (RssFeed $record): bool => ! $record->parseLogs()->exists()),
                        RepeatableEntry::make('recent_parse_logs')
                            ->hidden(fn (RssFeed $record): bool => ! $record->parseLogs()->exists())
                            ->state(fn (RssFeed $record): array => static::recentParseLogsState($record))
                            ->table([
                                RepeatableTableColumn::make('Запуск')
                                    ->width('170px'),
                                RepeatableTableColumn::make('Источник')
                                    ->width('130px'),
                                RepeatableTableColumn::make('Новые')
                                    ->alignment('center')
                                    ->width('80px'),
                                RepeatableTableColumn::make('Пропущено')
                                    ->alignment('center')
                                    ->width('80px'),
                                RepeatableTableColumn::make('Ошибки')
                                    ->alignment('center')
                                    ->width('80px'),
                                RepeatableTableColumn::make('Длительность')
                                    ->alignment('center')
                                    ->width('100px'),
                                RepeatableTableColumn::make('Статус')
                                    ->width('120px'),
                                RepeatableTableColumn::make('Ошибка')
                                    ->wrapHeader(),
                            ])
                            ->schema([
                                TextEntry::make('started_at')
                                    ->label('Запуск')
                                    ->dateTime('d.m.Y H:i:s'),
                                TextEntry::make('triggered_by')
                                    ->label('Источник')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => match ((string) $state) {
                                        'scheduler' => 'Планировщик',
                                        'manual' => 'Вручную',
                                        'api' => 'API',
                                        'filament' => 'Filament',
                                        default => Str::headline((string) $state),
                                    }),
                                TextEntry::make('new_count')
                                    ->label('Новые')
                                    ->numeric(),
                                TextEntry::make('skip_count')
                                    ->label('Пропущено')
                                    ->numeric(),
                                TextEntry::make('error_count')
                                    ->label('Ошибки')
                                    ->numeric(),
                                TextEntry::make('duration_ms')
                                    ->label('Длительность')
                                    ->formatStateUsing(fn (?int $state): string => number_format((int) $state).' ms'),
                                TextEntry::make('success')
                                    ->label('Статус')
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Успешно' : 'Сбой')
                                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                                TextEntry::make('error_message')
                                    ->label('Ошибка')
                                    ->placeholder('—')
                                    ->limit(60),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return RssFeedsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forAdminIndex();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRssFeeds::route('/'),
            'create' => CreateRssFeed::route('/create'),
            'view' => ViewRssFeed::route('/{record}'),
            'edit' => EditRssFeed::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function recentParseLogsState(RssFeed $record): array
    {
        return $record->parseLogs()
            ->latest('started_at')
            ->limit(5)
            ->get()
            ->map(fn (RssParseLog $log): array => [
                'started_at' => $log->started_at,
                'triggered_by' => $log->triggered_by,
                'new_count' => $log->new_count,
                'skip_count' => $log->skip_count,
                'error_count' => $log->error_count,
                'duration_ms' => $log->duration_ms,
                'success' => $log->success,
                'error_message' => $log->error_message,
            ])
            ->all();
    }
}
