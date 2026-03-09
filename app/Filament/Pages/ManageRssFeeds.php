<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\RssFeed;
use App\Services\RssParserService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

class ManageRssFeeds extends Page
{
    protected static ?string $navigationLabel = 'RSS Менеджер';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Ingestion;

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
            if ($this->filterCategory === '') {
                return true;
            }

            return data_get($feed, 'category.slug') === $this->filterCategory;
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

        return [
            'total_feeds' => count($this->feeds),
            'filtered_feeds' => $filteredFeeds->count(),
            'active_feeds' => $filteredFeeds->filter(fn (array $feed): bool => (bool) ($feed['is_active'] ?? false))->count(),
            'due_feeds' => $filteredFeeds->filter(fn (array $feed): bool => $this->isFeedDue($feed))->count(),
            'failing_feeds' => $filteredFeeds->filter(fn (array $feed): bool => filled($feed['last_error'] ?? null))->count(),
            'categories' => count($this->groupedFeeds),
            'articles_parsed_total' => $filteredFeeds->sum(fn (array $feed): int => (int) ($feed['articles_parsed_total'] ?? 0)),
            'new_last_run_total' => $filteredFeeds->sum(fn (array $feed): int => (int) ($feed['last_run_new_count'] ?? 0)),
        ];
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
}
