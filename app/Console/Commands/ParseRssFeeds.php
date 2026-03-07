<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Services\RssParserService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class ParseRssFeeds extends Command
{
    protected $signature = 'rss:parse
        {--category= : Category slug, e.g. politics}
        {--feed= : Specific RssFeed ID}
        {--url= : Parse any arbitrary RSS URL (not saved to DB, output only)}
        {--due : Only parse feeds due for parsing (respects fetch_interval)}
        {--all : Parse all active feeds regardless of schedule}
        {--dry-run : Fetch feeds and count items, do not save to DB}
        {--json : Output results as JSON instead of table}
        {--force : Parse even feeds with consecutive_failures >= 10}
        {--reparse= : Re-parse last N articles from each feed (re-checks duplicates)}
        {--stat : Show detailed statistics after parsing}';

    protected $description = 'Parse RSS feeds from news.mail.ru — supports filtering, scheduling, dry runs';

    public function handle(RssParserService $parser): int
    {
        if (is_string($this->option('url')) && $this->option('url') !== '') {
            return $this->handleUrlPreview($parser, $this->option('url'));
        }

        $feeds = $this->buildFeedsQuery()->get();

        if ($feeds->isEmpty()) {
            $this->warn('No matching feeds found.');

            return SymfonyCommand::FAILURE;
        }

        $reparseLimit = $this->parseReparseLimit();

        if ($this->option('dry-run')) {
            return $this->handleDryRun($parser, $feeds, $reparseLimit);
        }

        if (! $this->option('json')) {
            $this->info('🔄 RSS Parser — '.now()->format('d.m.Y H:i:s'));
            $this->table(
                ['ID', 'Feed', 'Category', 'Last Parsed', 'Articles'],
                $feeds->map(function (RssFeed $feed): array {
                    return [
                        $feed->id,
                        $feed->title,
                        $feed->category?->name ?? '-',
                        $feed->last_parsed_at?->diffForHumans() ?? 'Never',
                        $feed->articles()->count(),
                    ];
                })->all(),
            );
        }

        $bar = $this->output->createProgressBar($feeds->count());
        $bar->start();

        $results = [];
        $totalNew = 0;
        $totalSkip = 0;
        $totalError = 0;
        $totalDuration = 0;

        $this->withItemsLimit($reparseLimit, function () use ($feeds, $parser, &$results, &$totalNew, &$totalSkip, &$totalError, &$totalDuration, $bar): void {
            foreach ($feeds as $feed) {
                $bar->setMessage("[{$feed->category?->name}] {$feed->title}");

                $result = $parser->parseFeed($feed, 'manual');
                $results[] = $result;
                $totalNew += (int) $result['new'];
                $totalSkip += (int) $result['skip'];
                $totalError += (int) $result['errors'];
                $totalDuration += (int) $result['duration_ms'];

                $bar->advance();

                if (! empty($result['error'])) {
                    $this->newLine();
                    $this->warn("  ⚠ {$feed->title}: {$result['error']}");
                }
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($this->option('json')) {
            $this->line((string) json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return collect($results)->contains(fn (array $result): bool => ! empty($result['error']))
                ? SymfonyCommand::FAILURE
                : SymfonyCommand::SUCCESS;
        }

        $this->table(
            ['Feed', 'New', 'Skip', 'Err', 'Time(ms)', 'Status'],
            collect($results)->map(function (array $result): array {
                return [
                    $result['feed'],
                    $result['new'],
                    $result['skip'],
                    $result['errors'],
                    $result['duration_ms'],
                    ! empty($result['error']) ? '❌ '.$result['error'] : '✅ OK',
                ];
            })->all(),
        );

        $this->info("Total: {$totalNew} new | {$totalSkip} skipped | {$totalError} errors | {$totalDuration}ms");

        if ($this->option('stat')) {
            $this->renderStats();
        }

        return collect($results)->contains(fn (array $result): bool => ! empty($result['error']))
            ? SymfonyCommand::FAILURE
            : SymfonyCommand::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\RssFeed>
     */
    private function buildFeedsQuery(): Builder
    {
        $query = RssFeed::query()->with('category');

        $feedId = $this->option('feed');
        if (is_string($feedId) && $feedId !== '') {
            $query->whereKey((int) $feedId);
        }

        $category = $this->option('category');
        if (is_string($category) && $category !== '') {
            $query->whereHas('category', function (Builder $query) use ($category): void {
                $query->where('slug', $category);
            });
        }

        if (! $this->option('force')) {
            $query->where('is_active', true);
        }

        if ($this->option('due')) {
            $query->where(function (Builder $query): void {
                $query->whereNull('next_parse_at')
                    ->orWhere('next_parse_at', '<=', now());
            });
        }

        return $query->orderBy('category_id')->orderBy('title');
    }

    private function parseReparseLimit(): ?int
    {
        $value = $this->option('reparse');

        if (! is_string($value) || $value === '') {
            return null;
        }

        $limit = (int) $value;

        return $limit > 0 ? $limit : null;
    }

    private function handleUrlPreview(RssParserService $parser, string $url): int
    {
        $items = $parser->previewFeed($url);

        if ($this->option('json')) {
            $this->line((string) json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return SymfonyCommand::SUCCESS;
        }

        $this->table(
            ['Title', 'Link', 'Published', 'Image'],
            collect($items)->map(function (array $item): array {
                return [
                    $item['title'],
                    $item['link'],
                    $item['pub_date'],
                    $item['image'] ? 'yes' : 'no',
                ];
            })->all(),
        );

        return SymfonyCommand::SUCCESS;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\RssFeed>  $feeds
     */
    private function handleDryRun(RssParserService $parser, $feeds, ?int $limit): int
    {
        $rows = [];

        $this->withItemsLimit($limit, function () use ($feeds, $parser, &$rows): void {
            foreach ($feeds as $feed) {
                $summary = $parser->inspectFeed($feed);
                $rows[] = [
                    $feed->title,
                    $summary['items'],
                    $summary['new'],
                    $summary['skip'],
                ];
            }
        });

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return SymfonyCommand::SUCCESS;
        }

        $this->table(['Feed', 'Items Found', 'Would Save', 'Would Skip'], $rows);

        return SymfonyCommand::SUCCESS;
    }

    private function renderStats(): void
    {
        $this->newLine();
        $this->info('Additional Statistics');

        $topFeeds = RssParseLog::query()
            ->selectRaw('rss_feed_id, SUM(new_count) as new_total')
            ->whereDate('started_at', today())
            ->groupBy('rss_feed_id')
            ->orderByDesc('new_total')
            ->with('rssFeed')
            ->limit(3)
            ->get();

        $this->table(
            ['Top Feed', 'New Today'],
            $topFeeds->map(fn (RssParseLog $log): array => [
                $log->rssFeed?->title ?? 'Unknown',
                (int) $log->new_total,
            ])->all(),
        );

        $categoryTotals = Category::query()
            ->withCount(['articles' => fn (Builder $query) => $query->published()])
            ->orderByDesc('articles_count')
            ->limit(10)
            ->get();

        $this->table(
            ['Category', 'Articles'],
            $categoryTotals->map(fn (Category $category): array => [
                $category->name,
                $category->articles_count,
            ])->all(),
        );

        $recentLogs = RssParseLog::query()
            ->with('rssFeed')
            ->latest('started_at')
            ->limit(5)
            ->get();

        $this->table(
            ['Feed', 'New', 'Skip', 'Err', 'Duration', 'Started'],
            $recentLogs->map(fn (RssParseLog $log): array => [
                $log->rssFeed?->title ?? 'Unknown',
                $log->new_count,
                $log->skip_count,
                $log->error_count,
                "{$log->duration_ms}ms",
                $log->started_at?->format('d.m H:i:s') ?? '-',
            ])->all(),
        );
    }

    private function withItemsLimit(?int $limit, callable $callback): mixed
    {
        if ($limit === null) {
            return $callback();
        }

        $original = config('rss.parser.max_items_per_feed');
        config(['rss.parser.max_items_per_feed' => $limit]);

        try {
            return $callback();
        } finally {
            config(['rss.parser.max_items_per_feed' => $original]);
        }
    }
}
