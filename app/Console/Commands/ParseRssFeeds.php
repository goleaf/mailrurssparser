<?php

namespace App\Console\Commands;

use App\Models\RssFeed;
use App\Services\RssParserService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class ParseRssFeeds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rss:parse {--category= : Category slug to parse (e.g. politics, sport)} {--feed= : Specific feed ID to parse} {--all : Force parse all even if recently parsed} {--dry-run : Preview only, do not save to database} {--verbose-stats : Show detailed per-feed statistics table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse RSS feeds from news.mail.ru and save new articles to database';

    /**
     * Execute the console command.
     */
    public function handle(RssParserService $parser): int
    {
        $this->info('🔄 RSS Parser — '.now()->format('d.m.Y H:i:s'));

        $feedsQuery = RssFeed::active()->with('category');

        $categorySlug = $this->option('category');
        if (is_string($categorySlug) && $categorySlug !== '') {
            $feedsQuery->whereHas('category', function ($query) use ($categorySlug): void {
                $query->where('slug', $categorySlug);
            });
        }

        $feedId = $this->option('feed');
        if ($feedId !== null && $feedId !== '') {
            $feedsQuery->where('id', $feedId);
        }

        $feeds = $feedsQuery->get();

        if ($feeds->isEmpty()) {
            $this->warn('No matching active feeds found.');

            return SymfonyCommand::FAILURE;
        }

        $this->line('Found '.$feeds->count().' feed(s) to process...');

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Title', 'URL', 'Category'],
                $feeds->map(function (RssFeed $feed): array {
                    return [
                        $feed->id,
                        $feed->title,
                        $feed->url,
                        $feed->category?->name ?? '-',
                    ];
                })->all(),
            );

            return SymfonyCommand::SUCCESS;
        }

        $bar = $this->output->createProgressBar($feeds->count());

        $totalNew = 0;
        $totalSkipped = 0;
        $totalErrors = 0;
        $results = [];

        foreach ($feeds as $feed) {
            $bar->setMessage($feed->title);

            $result = $parser->parseFeed($feed);
            $results[] = $result;

            $totalNew += $result['new'];
            $totalSkipped += $result['skipped'];
            $totalErrors += $result['errors'];

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Feed', 'New', 'Skipped', 'Errors', 'Status'],
            collect($results)->map(function (array $result): array {
                $status = $result['error_message']
                    ? '❌ '.$result['error_message']
                    : '✅ OK';

                return [
                    $result['feed_title'],
                    $result['new'],
                    $result['skipped'],
                    $result['errors'],
                    $status,
                ];
            })->all(),
        );

        $this->info("Total: {$totalNew} new, {$totalSkipped} skipped, {$totalErrors} errors");

        $hasErrors = collect($results)->contains(function (array $result): bool {
            return $result['error_message'] !== null && $result['error_message'] !== '';
        });

        return $hasErrors ? SymfonyCommand::FAILURE : SymfonyCommand::SUCCESS;
    }
}
