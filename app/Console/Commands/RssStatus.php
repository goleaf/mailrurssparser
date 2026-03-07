<?php

namespace App\Console\Commands;

use App\Models\RssFeed;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class RssStatus extends Command
{
    protected $signature = 'rss:status {--category=} {--json} {--watch : refresh every 5 seconds}';

    protected $description = 'Show current status of all RSS feeds';

    public function handle(): int
    {
        if ($this->option('watch')) {
            while (true) {
                $this->output->write("\033[2J\033[H");
                $exitCode = $this->renderStatus();

                if ($exitCode !== SymfonyCommand::SUCCESS) {
                    return $exitCode;
                }

                sleep(5);
            }
        }

        return $this->renderStatus();
    }

    private function renderStatus(): int
    {
        $feeds = RssFeed::query()
            ->with('category')
            ->withCount('articles')
            ->when(
                is_string($this->option('category')) && $this->option('category') !== '',
                function ($query): void {
                    $query->whereHas('category', function ($query): void {
                        $query->where('slug', $this->option('category'));
                    });
                },
            )
            ->orderBy('category_id')
            ->orderBy('title')
            ->get();

        if ($feeds->isEmpty()) {
            $this->warn('No feeds found.');

            return SymfonyCommand::FAILURE;
        }

        $rows = $feeds->map(function (RssFeed $feed): array {
            return [
                $feed->id,
                $feed->category?->name ?? '-',
                $feed->title,
                $feed->is_active ? 'Yes' : 'No',
                $feed->last_parsed_at?->format('Y-m-d H:i:s') ?? 'Never',
                $feed->next_parse_at?->format('Y-m-d H:i:s') ?? 'Now',
                $feed->last_run_new_count,
                $feed->articles_count,
                $this->statusLabel($feed),
            ];
        })->all();

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['ID', 'Category', 'Feed Title', 'Active', 'Last Parsed', 'Next Parse', 'New(last)', 'Total Articles', 'Status'],
                $rows,
            );
        }

        $active = $feeds->where('is_active', true)->count();
        $due = $feeds->filter(function (RssFeed $feed): bool {
            return $feed->is_active && ($feed->next_parse_at === null || $feed->next_parse_at->lte(now()));
        })->count();
        $totalArticles = $feeds->sum('articles_count');

        $this->line("Total: {$feeds->count()} feeds, {$active} active, {$due} due, {$totalArticles} total articles");

        return SymfonyCommand::SUCCESS;
    }

    private function statusLabel(RssFeed $feed): string
    {
        if (! $feed->is_active) {
            return '❌ Disabled('.$feed->consecutive_failures.' failures)';
        }

        if ($feed->last_error !== null && $feed->last_error !== '') {
            return '⚠ Errors:'.max(1, (int) $feed->last_run_error_count);
        }

        if ($feed->next_parse_at === null || $feed->next_parse_at->lte(now())) {
            return '⏰ Due Now';
        }

        $minutesUntilNextRun = max(1, (int) ceil(now()->diffInSeconds($feed->next_parse_at) / 60));

        return "⏳ in {$minutesUntilNextRun}m";
    }
}
