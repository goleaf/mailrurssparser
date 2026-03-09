<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class ReindexArticles extends Command
{
    protected $signature = 'rss:reindex {--chunk=500} {--category=}';

    protected $description = 'Rebuild the Scout search index for published articles';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $category = $this->option('category');

        if (config('scout.driver') === 'database') {
            $count = Article::query()
                ->published()
                ->when(is_string($category) && $category !== '', function (Builder $query) use ($category): void {
                    $query->byCategory($category);
                })
                ->count();

            $this->info("Scout database engine is active; {$count} articles are already searchable.");

            return SymfonyCommand::SUCCESS;
        }

        Artisan::call('scout:flush', ['model' => Article::class]);

        $query = Article::query()
            ->published()
            ->with('category')
            ->when(is_string($category) && $category !== '', function (Builder $query) use ($category): void {
                $query->byCategory($category);
            })
            ->orderBy('id');

        $count = (clone $query)->count();
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $indexed = 0;

        $query->chunkById($chunk, function ($articles) use (&$indexed, $bar): void {
            $articles->searchable();
            $indexed += $articles->count();
            $bar->advance($articles->count());
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Indexed {$indexed} articles");

        return SymfonyCommand::SUCCESS;
    }
}
