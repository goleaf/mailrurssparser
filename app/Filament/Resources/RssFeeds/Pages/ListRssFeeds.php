<?php

namespace App\Filament\Resources\RssFeeds\Pages;

use App\Filament\Resources\RssFeeds\RssFeedResource;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Services\RssParserService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ListRssFeeds extends ListRecords
{
    protected static string $resource = RssFeedResource::class;

    protected string $view = 'filament.resources.rss-feeds.pages.list-rss-feeds';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Новая лента')
                ->icon(Heroicon::OutlinedPlus),
            Action::make('parseAllFeeds')
                ->label('Запустить все активные')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (RssParserService $parser): void {
                    $results = $parser->parseAllFeeds('filament');

                    $newCount = 0;
                    $skippedCount = 0;
                    $errorCount = 0;

                    foreach ($results as $result) {
                        $newCount += (int) ($result['new'] ?? 0);
                        $skippedCount += (int) ($result['skip'] ?? 0);

                        if (! empty($result['error'])) {
                            $errorCount++;
                        }
                    }

                    $body = "Новые: {$newCount}, Пропущено: {$skippedCount}";

                    if ($errorCount > 0) {
                        $body .= ", Ошибки: {$errorCount}";
                    }

                    $notification = Notification::make()
                        ->title($errorCount > 0 ? 'Парсинг завершён с ошибками' : 'Парсинг завершён')
                        ->body($body);

                    if ($errorCount > 0) {
                        $notification->danger();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Каталог RSS-лент, быстрые сигналы по здоровью контура и запуск парсинга без лишних переходов.';
    }

    /**
     * @return array{
     *     total_feeds: int,
     *     active_feeds: int,
     *     due_feeds: int,
     *     failing_feeds: int,
     *     categories: int,
     *     articles_parsed_total: int,
     *     runs_today: int,
     *     new_today: int
     * }
     */
    public function getSummaryProperty(): array
    {
        $baseQuery = RssFeed::query();
        $todayLogs = RssParseLog::query()
            ->whereBetween('started_at', [today()->startOfDay(), today()->endOfDay()]);

        return [
            'total_feeds' => (clone $baseQuery)->count(),
            'active_feeds' => (clone $baseQuery)->active()->count(),
            'due_feeds' => (clone $baseQuery)->dueForParsing()->count(),
            'failing_feeds' => (clone $baseQuery)->withErrors()->count(),
            'categories' => (clone $baseQuery)->whereNotNull('category_id')->distinct()->count('category_id'),
            'articles_parsed_total' => (int) ((clone $baseQuery)->sum('articles_parsed_total') ?? 0),
            'runs_today' => (clone $todayLogs)->count(),
            'new_today' => (int) ((clone $todayLogs)->sum('new_count') ?? 0),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function getTableSignalsProperty(): array
    {
        $signals = [];

        if (filled($this->tableSearch)) {
            $signals[] = [
                'label' => 'Поиск',
                'value' => (string) $this->tableSearch,
            ];
        }

        if (filled($this->getTableSortColumn())) {
            $signals[] = [
                'label' => 'Сортировка',
                'value' => sprintf(
                    '%s · %s',
                    $this->getTableSortColumn(),
                    $this->getTableSortDirection() === 'desc' ? 'по убыванию' : 'по возрастанию',
                ),
            ];
        }

        return $signals;
    }

    /**
     * @return EloquentCollection<int, RssFeed>
     */
    public function getAttentionFeedsProperty(): EloquentCollection
    {
        return new EloquentCollection(RssFeed::query()
            ->forAdminIndex()
            ->get()
            ->map(fn (RssFeed $feed): array => [
                'feed' => $feed,
                'score' => $this->getAttentionScore($feed),
            ])
            ->filter(fn (array $item): bool => $item['score'] > 0)
            ->sortByDesc('score')
            ->take(6)
            ->pluck('feed')
            ->all());
    }

    /**
     * @return EloquentCollection<int, RssParseLog>
     */
    public function getRecentLogsProperty(): EloquentCollection
    {
        return RssParseLog::query()
            ->forAdminIndex()
            ->latest('started_at')
            ->limit(6)
            ->get();
    }

    private function getAttentionScore(RssFeed $feed): int
    {
        $score = 0;

        if (filled($feed->last_error)) {
            $score += 1000;
        }

        if ($feed->is_active && ($feed->next_parse_at === null || $feed->next_parse_at->lte(now()))) {
            $score += 500;
        }

        if (! $feed->is_active) {
            $score += 150;
        }

        return $score + ($feed->consecutive_failures * 50);
    }
}
