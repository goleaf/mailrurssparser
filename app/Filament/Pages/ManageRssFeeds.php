<?php

namespace App\Filament\Pages;

use App\Models\RssFeed;
use App\Services\RssParserService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class ManageRssFeeds extends Page
{
    protected static ?string $navigationLabel = 'RSS Parser';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rss';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.manage-rss-feeds';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $feeds = [];

    /**
     * @var array<int|string, array<string, mixed>>
     */
    public array $parseResults = [];

    public bool $isParsing = false;

    public ?int $selectedFeedId = null;

    public function mount(): void
    {
        $this->refreshFeeds();
    }

    public function parseAll(): void
    {
        $this->isParsing = true;
        $this->selectedFeedId = null;

        try {
            $results = app(RssParserService::class)->parseAllFeeds();
            $this->parseResults = $results;
            $this->refreshFeeds();

            $newCount = collect($results)->sum(fn (array $result): int => (int) ($result['new'] ?? 0));

            Notification::make()
                ->title('Parse Complete')
                ->body("Total new articles: {$newCount}")
                ->success()
                ->send();
        } finally {
            $this->isParsing = false;
        }
    }

    public function parseSingleFeed(int $feedId): void
    {
        $this->isParsing = true;
        $this->selectedFeedId = $feedId;

        try {
            $feed = RssFeed::query()->findOrFail($feedId);
            $result = app(RssParserService::class)->parseFeed($feed);
            $this->parseResults = [$feedId => $result];
            $this->refreshFeeds();

            $notification = Notification::make()
                ->title(empty($result['error_message']) ? 'Feed Parsed' : 'Feed Parse Failed')
                ->body(empty($result['error_message']) ? "New: {$result['new']}, Skipped: {$result['skipped']}" : (string) $result['error_message']);

            if (empty($result['error_message'])) {
                $notification->success();
            } else {
                $notification->danger();
            }

            $notification->send();
        } finally {
            $this->isParsing = false;
            $this->selectedFeedId = null;
        }
    }

    protected function refreshFeeds(): void
    {
        $this->feeds = RssFeed::query()
            ->with('category')
            ->orderBy('title')
            ->get()
            ->map(function (RssFeed $feed): array {
                return [
                    'id' => $feed->id,
                    'title' => $feed->title,
                    'category_name' => $feed->category?->name ?? 'Uncategorized',
                    'last_parsed_at' => $feed->last_parsed_at?->diffForHumans() ?? 'Never',
                    'status' => $feed->is_active ? 'Active' : 'Inactive',
                    'new_count' => $feed->last_run_new_count,
                    'error' => $feed->last_error,
                ];
            })
            ->all();
    }
}
