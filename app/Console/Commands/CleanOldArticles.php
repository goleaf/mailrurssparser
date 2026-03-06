<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CleanOldArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rss:clean {--days=90 : Delete archived articles older than N days} {--dry-run : Show count without deleting} {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove old archived articles from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $query = Article::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->orWhere(function (Builder $query) use ($cutoff): void {
                $query->whereNull('deleted_at')
                    ->where('status', 'archived')
                    ->where('published_at', '<', $cutoff);
            });

        $count = $query->count();

        $this->info("Found {$count} articles to clean (older than {$days} days)");

        if ($this->option('dry-run')) {
            return SymfonyCommand::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm("Delete {$count} articles permanently?")) {
                return SymfonyCommand::SUCCESS;
            }
        }

        $query->orderBy('id')->chunkById(100, function ($articles): void {
            $articles->each->forceDelete();
        });

        $this->info("✅ Deleted {$count} articles");

        return SymfonyCommand::SUCCESS;
    }
}
