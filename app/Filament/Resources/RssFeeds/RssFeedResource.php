<?php

namespace App\Filament\Resources\RssFeeds;

use App\Filament\Resources\RssFeeds\Pages\CreateRssFeed;
use App\Filament\Resources\RssFeeds\Pages\EditRssFeed;
use App\Filament\Resources\RssFeeds\Pages\ListRssFeeds;
use App\Filament\Resources\RssFeeds\Pages\ViewRssFeed;
use App\Filament\Resources\RssFeeds\Schemas\RssFeedForm;
use App\Filament\Resources\RssFeeds\Tables\RssFeedsTable;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use BackedEnum;
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

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    public static function form(Schema $schema): Schema
    {
        return RssFeedForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Feed overview')
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
                            ->suffix(' min'),
                        TextEntry::make('is_active')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Disabled')
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('auto_publish')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Auto publish' : 'Manual moderation')
                            ->color(fn (bool $state): string => $state ? 'info' : 'warning'),
                        TextEntry::make('auto_featured')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Auto featured' : 'Standard import')
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                    ]),
                Section::make('Parser status')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('last_parsed_at')
                            ->label('Last parsed')
                            ->since()
                            ->placeholder('Never'),
                        TextEntry::make('next_parse_at')
                            ->label('Next parse')
                            ->since()
                            ->placeholder('Waiting for schedule'),
                        TextEntry::make('articles_parsed_total')
                            ->label('Imported articles')
                            ->numeric(),
                        TextEntry::make('last_run_new_count')
                            ->label('Last run new')
                            ->badge()
                            ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'success' : 'gray'),
                        TextEntry::make('last_run_skip_count')
                            ->label('Last run skipped')
                            ->numeric(),
                        TextEntry::make('consecutive_failures')
                            ->badge()
                            ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'danger' : 'gray'),
                        TextEntry::make('last_error')
                            ->columnSpanFull()
                            ->placeholder('No recent errors recorded.'),
                    ]),
                Section::make('Feed overrides')
                    ->schema([
                        EmptyState::make('No feed overrides configured')
                            ->description('This feed currently uses the shared importer defaults.')
                            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                            ->visible(fn (RssFeed $record): bool => blank($record->extra_settings)),
                        KeyValueEntry::make('extra_settings')
                            ->columnSpanFull()
                            ->hidden(fn (RssFeed $record): bool => blank($record->extra_settings)),
                    ]),
                Section::make('Recent parse runs')
                    ->schema([
                        EmptyState::make('No parse runs yet')
                            ->description('Run this feed once or wait for the scheduler to collect the first batch.')
                            ->icon(Heroicon::OutlinedClock)
                            ->visible(fn (RssFeed $record): bool => ! $record->parseLogs()->exists()),
                        RepeatableEntry::make('recent_parse_logs')
                            ->hidden(fn (RssFeed $record): bool => ! $record->parseLogs()->exists())
                            ->state(fn (RssFeed $record): array => static::recentParseLogsState($record))
                            ->table([
                                RepeatableTableColumn::make('Started')
                                    ->width('170px'),
                                RepeatableTableColumn::make('Trigger')
                                    ->width('130px'),
                                RepeatableTableColumn::make('New')
                                    ->alignment('center')
                                    ->width('80px'),
                                RepeatableTableColumn::make('Skip')
                                    ->alignment('center')
                                    ->width('80px'),
                                RepeatableTableColumn::make('Errors')
                                    ->alignment('center')
                                    ->width('80px'),
                                RepeatableTableColumn::make('Duration')
                                    ->alignment('center')
                                    ->width('100px'),
                                RepeatableTableColumn::make('Status')
                                    ->width('120px'),
                                RepeatableTableColumn::make('Error')
                                    ->wrapHeader(),
                            ])
                            ->schema([
                                TextEntry::make('started_at')
                                    ->label('Started')
                                    ->dateTime('d.m.Y H:i:s'),
                                TextEntry::make('triggered_by')
                                    ->label('Trigger')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => Str::headline((string) $state)),
                                TextEntry::make('new_count')
                                    ->label('New')
                                    ->numeric(),
                                TextEntry::make('skip_count')
                                    ->label('Skip')
                                    ->numeric(),
                                TextEntry::make('error_count')
                                    ->label('Errors')
                                    ->numeric(),
                                TextEntry::make('duration_ms')
                                    ->label('Duration')
                                    ->formatStateUsing(fn (?int $state): string => number_format((int) $state).' ms'),
                                TextEntry::make('success')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Success' : 'Failure')
                                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                                TextEntry::make('error_message')
                                    ->label('Error')
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
        return parent::getEloquentQuery()
            ->with('category');
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
