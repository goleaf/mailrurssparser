<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CleanOldArticles extends Command
{
    protected $signature = 'rss:clean
        {--days=90}
        {--status=archived : which status to clean}
        {--dry-run}
        {--force}';

    protected $description = 'Permanently remove old soft-deleted or archived articles';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $status = (string) $this->option('status');
        $cutoff = now()->subDays($days);

        $query = Article::withTrashed()
            ->where(function (Builder $query) use ($cutoff, $status): void {
                $query->where(function (Builder $query) use ($cutoff): void {
                    $query->whereNotNull('deleted_at')
                        ->where('deleted_at', '<', $cutoff);
                })->orWhere(function (Builder $query) use ($cutoff, $status): void {
                    $query->where('status', $status)
                        ->where('published_at', '<', $cutoff);
                });
            });

        $count = (clone $query)->count();

        $this->info("Found {$count} articles to clean (older than {$days} days)");

        if ($this->option('dry-run')) {
            $preview = (clone $query)
                ->orderBy('published_at')
                ->limit(10)
                ->get(['id', 'title', 'status', 'published_at', 'deleted_at']);

            $this->table(
                ['ID', 'Title', 'Status', 'Published', 'Deleted'],
                $preview->map(function (Article $article): array {
                    return [
                        $article->id,
                        $article->title,
                        $article->status,
                        $article->published_at?->format('Y-m-d H:i'),
                        $article->deleted_at?->format('Y-m-d H:i'),
                    ];
                })->all(),
            );

            return SymfonyCommand::SUCCESS;
        }

        if ($count === 0) {
            return SymfonyCommand::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$count} articles permanently?")) {
            return SymfonyCommand::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        (clone $query)
            ->orderBy('id')
            ->chunkById(500, function (Collection $articles) use ($bar): void {
                $articles->each(function (Article $article) use ($bar): void {
                    $article->forceDelete();
                    $bar->advance();
                });
            });

        $bar->finish();
        $this->newLine(2);

        Artisan::call('scout:flush', ['model' => Article::class]);

        $this->info("✅ Deleted {$count} articles");

        return SymfonyCommand::SUCCESS;
    }
}
