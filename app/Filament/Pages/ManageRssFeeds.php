<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\RssFeed;
use App\Services\RssParserService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class ManageRssFeeds extends Page
{
    protected static ?string $navigationLabel = 'RSS Менеджер';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rss';

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
     * @return array<int, string>
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

    protected function refreshFeeds(): void
    {
        $this->feeds = RssFeed::query()
            ->with('category')
            ->orderBy('category_id')
            ->orderBy('title')
            ->get()
            ->toArray();
    }
}
