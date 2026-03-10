<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Services\RssParserService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

class ManageRssFeeds extends Page
{
    protected static ?string $navigationLabel = 'RSS Менеджер';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Ingestion;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.manage-rss-feeds';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $feeds = [];

    /**
     * @var array<int|string, array<string, mixed>>
     */
    public array $results = [];

    public bool $isParsing = false;

    public ?int $parsingFeedId = null;

    public string $filterCategory = '';

    public string $search = '';

    public string $status = 'all';

    public function resetFilters(): void
    {
        $this->filterCategory = '';
        $this->search = '';
        $this->status = 'all';
    }

    public function resetFilterCategory(): void
    {
        $this->filterCategory = '';
    }

    public function mount(): void
    {
        $this->refreshFeeds();
    }

    public function parseAll(): void
    {
        $this->isParsing = true;
        $this->parsingFeedId = null;

        try {
            $allResults = app(RssParserService::class)->parseAllFeeds('filament');

            $this->results = $allResults;
            $this->refreshFeeds();

            $newArticles = collect($allResults)->sum(fn (array $result): int => (int) ($result['new'] ?? 0));

            Notification::make()
                ->title('Парсинг завершён')
                ->body("Добавлено новых статей: {$newArticles}")
                ->success()
                ->send();
        } finally {
            $this->isParsing = false;
        }
    }

    public function parseFeed(int $feedId): void
    {
        $this->isParsing = true;
        $this->parsingFeedId = $feedId;

        try {
            $feed = RssFeed::query()->findOrFail($feedId);
            $result = app(RssParserService::class)->parseFeed($feed, 'filament');

            $this->results[$feedId] = $result;
            $this->refreshFeeds();

            $notification = Notification::make()
                ->title(empty($result['error']) ? 'Лента обработана' : 'Не удалось обработать ленту')
                ->body(empty($result['error']) ? "Новые: {$result['new']}, Пропущено: {$result['skip']}, Ошибки: {$result['errors']}" : (string) $result['error']);

            if (empty($result['error'])) {
                $notification->success();
            } else {
                $notification->danger();
            }

            $notification->send();
        } finally {
            $this->isParsing = false;
            $this->parsingFeedId = null;
        }
    }

    public function toggleFeed(int $feedId): void
    {
        $feed = RssFeed::query()->findOrFail($feedId);

        $feed->update([
            'is_active' => ! $feed->is_active,
        ]);

        $this->refreshFeeds();

        Notification::make()
            ->title($feed->fresh()->is_active ? 'Лента включена' : 'Лента отключена')
            ->success()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    public function getCategoryOptionsProperty(): array
    {
        return collect($this->feeds)
            ->mapWithKeys(function (array $feed): array {
                $slug = data_get($feed, 'category.slug');
                $name = data_get($feed, 'category.name');

                if (! is_string($slug) || $slug === '' || ! is_string($name) || $name === '') {
                    return [];
                }

                return [$slug => $name];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredFeedsProperty(): array
    {
        return array_values(array_filter($this->feeds, function (array $feed): bool {
            return $this->matchesCategoryFilter($feed)
                && $this->matchesSearchFilter($feed)
                && $this->matchesStatusFilter($feed);
        }));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getGroupedFeedsProperty(): array
    {
        return collect($this->filteredFeeds)
            ->groupBy(fn (array $feed): string => (string) (data_get($feed, 'category.name') ?: 'Без категории'))
            ->map(fn (Collection $feeds): array => $feeds->all())
            ->all();
    }

    /**
     * @return array{
     *     total_feeds: int,
     *     filtered_feeds: int,
     *     active_feeds: int,
     *     due_feeds: int,
     *     failing_feeds: int,
     *     categories: int,
     *     articles_parsed_total: int,
     *     new_last_run_total: int
     * }
     */
    public function getSummaryProperty(): array
    {
        $filteredFeeds = collect($this->filteredFeeds);
        $activeFeeds = $filteredFeeds->filter(fn (array $feed): bool => (bool) ($feed['is_active'] ?? false));
        $dueFeeds = $filteredFeeds->filter(fn (array $feed): bool => $this->isFeedDue($feed));
        $failingFeeds = $filteredFeeds->filter(fn (array $feed): bool => filled($feed['last_error'] ?? null));
        $inactiveFeeds = $filteredFeeds->reject(fn (array $feed): bool => (bool) ($feed['is_active'] ?? false));

        return [
            'total_feeds' => count($this->feeds),
            'filtered_feeds' => $filteredFeeds->count(),
            'active_feeds' => $activeFeeds->count(),
            'due_feeds' => $dueFeeds->count(),
            'failing_feeds' => $failingFeeds->count(),
            'inactive_feeds' => $inactiveFeeds->count(),
            'healthy_feeds' => $activeFeeds
                ->reject(fn (array $feed): bool => filled($feed['last_error'] ?? null))
                ->reject(fn (array $feed): bool => $this->isFeedDue($feed))
                ->count(),
            'categories' => count($this->groupedFeeds),
            'articles_parsed_total' => $filteredFeeds->sum(fn (array $feed): int => (int) ($feed['articles_parsed_total'] ?? 0)),
            'new_last_run_total' => $filteredFeeds->sum(fn (array $feed): int => (int) ($feed['last_run_new_count'] ?? 0)),
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function getActiveFiltersProperty(): array
    {
        $filters = [];

        if ($this->search !== '') {
            $filters[] = [
                'label' => 'Поиск',
                'value' => $this->search,
            ];
        }

        if ($this->filterCategory !== '') {
            $filters[] = [
                'label' => 'Рубрика',
                'value' => $this->categoryOptions[$this->filterCategory] ?? 'Неизвестно',
            ];
        }

        if ($this->status !== 'all') {
            $filters[] = [
                'label' => 'Статус',
                'value' => $this->statusLabel($this->status),
            ];
        }

        return $filters;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPriorityFeedsProperty(): array
    {
        return collect($this->filteredFeeds)
            ->map(fn (array $feed): array => [
                'feed' => $feed,
                'score' => $this->priorityScore($feed),
            ])
            ->filter(fn (array $item): bool => $item['score'] > 0)
            ->sortByDesc('score')
            ->take(6)
            ->map(fn (array $item): array => $item['feed'])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLatestRunsProperty(): array
    {
        return RssParseLog::query()
            ->forAdminIndex()
            ->latest('started_at')
            ->limit(6)
            ->get()
            ->map(function (RssParseLog $log): array {
                return [
                    'id' => $log->id,
                    'feed_title' => $log->rssFeed?->title ?? 'Неизвестная лента',
                    'category_name' => $log->rssFeed?->category?->name,
                    'category_color' => $log->rssFeed?->category?->color ?? '#94A3B8',
                    'success' => $log->success,
                    'triggered_by' => $this->triggeredByLabel($log->triggered_by),
                    'started_at_label' => $log->started_at?->format('d.m.Y H:i') ?? '—',
                    'started_at_human' => $log->started_at?->diffForHumans() ?? '—',
                    'duration_ms' => (int) ($log->duration_ms ?? 0),
                    'new_count' => (int) ($log->new_count ?? 0),
                    'skip_count' => (int) ($log->skip_count ?? 0),
                    'error_count' => (int) ($log->error_count ?? 0),
                    'error_message' => $log->error_message,
                ];
            })
            ->all();
    }

    /**
     * @return array{runs: int, successful_runs: int, failed_runs: int, new: int, skip: int, errors: int}
     */
    public function getResultSummaryProperty(): array
    {
        $results = collect($this->results);
        $failedRuns = $results->filter(fn (array $result): bool => filled($result['error'] ?? null))->count();

        return [
            'runs' => $results->count(),
            'successful_runs' => $results->count() - $failedRuns,
            'failed_runs' => $failedRuns,
            'new' => $results->sum(fn (array $result): int => (int) ($result['new'] ?? 0)),
            'skip' => $results->sum(fn (array $result): int => (int) ($result['skip'] ?? 0)),
            'errors' => $results->sum(fn (array $result): int => (int) ($result['errors'] ?? 0)),
        ];
    }

    protected function refreshFeeds(): void
    {
        $this->feeds = RssFeed::query()
            ->with('category')
            ->orderBy('category_id')
            ->orderBy('title')
            ->get()
            ->toArray();
    }

    private function isFeedDue(array $feed): bool
    {
        if (! ($feed['is_active'] ?? false)) {
            return false;
        }

        $nextParseAt = $feed['next_parse_at'] ?? null;

        if (! filled($nextParseAt)) {
            return true;
        }

        return Carbon::parse($nextParseAt)->lte(now());
    }

    private function matchesCategoryFilter(array $feed): bool
    {
        if ($this->filterCategory === '') {
            return true;
        }

        return data_get($feed, 'category.slug') === $this->filterCategory;
    }

    private function matchesSearchFilter(array $feed): bool
    {
        $search = trim(mb_strtolower($this->search));

        if ($search === '') {
            return true;
        }

        $haystack = implode(' ', array_filter([
            (string) ($feed['title'] ?? ''),
            (string) ($feed['url'] ?? ''),
            (string) ($feed['source_name'] ?? ''),
            (string) ($feed['last_error'] ?? ''),
            (string) data_get($feed, 'category.name', ''),
        ]));

        return str_contains(mb_strtolower($haystack), $search);
    }

    private function matchesStatusFilter(array $feed): bool
    {
        return match ($this->status) {
            'active' => (bool) ($feed['is_active'] ?? false),
            'inactive' => ! (bool) ($feed['is_active'] ?? false),
            'due' => $this->isFeedDue($feed),
            'failing' => filled($feed['last_error'] ?? null),
            'healthy' => (bool) ($feed['is_active'] ?? false)
                && ! filled($feed['last_error'] ?? null)
                && ! $this->isFeedDue($feed),
            default => true,
        };
    }

    private function priorityScore(array $feed): int
    {
        $score = 0;

        if (filled($feed['last_error'] ?? null)) {
            $score += 1000;
        }

        if ($this->isFeedDue($feed)) {
            $score += 500;
        }

        if (! ($feed['is_active'] ?? false)) {
            $score += 150;
        }

        return $score + ((int) ($feed['consecutive_failures'] ?? 0) * 50);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Активные',
            'inactive' => 'Отключённые',
            'due' => 'Ждут запуска',
            'failing' => 'С ошибкой',
            'healthy' => 'Стабильные',
            default => 'Все',
        };
    }

    private function triggeredByLabel(?string $triggeredBy): string
    {
        return match ((string) $triggeredBy) {
            'scheduler' => 'Планировщик',
            'manual' => 'Вручную',
            'api' => 'API',
            'filament' => 'Filament',
            default => 'Неизвестно',
        };
    }
}
