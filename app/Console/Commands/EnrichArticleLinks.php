<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\RssParserService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class EnrichArticleLinks extends Command
{
    protected $signature = 'rss:enrich-articles
        {--article= : Specific article ID}
        {--feed= : Only enrich articles from a specific RSS feed ID}
        {--limit=100 : Maximum number of articles to process}
        {--all : Include articles that already have rich fields}
        {--force : Overwrite existing article fields with parsed source-page data}';

    protected $description = 'Enrich stored article records by parsing their saved source links';

    /**
     * Execute the console command.
     */
    public function handle(RssParserService $parser): int
    {
        $articles = $this->buildArticlesQuery()->get();

        if ($articles->isEmpty()) {
            $this->warn('No matching articles found for enrichment.');

            return SymfonyCommand::FAILURE;
        }

        $this->info('Article source enrichment - '.now()->format('d.m.Y H:i:s'));

        $bar = $this->output->createProgressBar($articles->count());
        $bar->start();

        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $force = (bool) $this->option('force');

        foreach ($articles as $article) {
            $bar->setMessage("#{$article->id} {$article->title}");

            try {
                if ($parser->enrichExistingArticle($article, $force)) {
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $exception) {
                $errors++;
                $this->newLine();
                $this->warn("Article {$article->id}: {$exception->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->table(
            ['Processed', 'Updated', 'Skipped', 'Errors', 'Force', 'Mode'],
            [[
                $articles->count(),
                $updated,
                $skipped,
                $errors,
                $force ? 'yes' : 'no',
                $this->option('all') ? 'all' : 'missing-only',
            ]],
        );

        $this->info("Total: {$updated} updated | {$skipped} skipped | {$errors} errors");

        return $errors > 0 ? SymfonyCommand::FAILURE : SymfonyCommand::SUCCESS;
    }

    /**
     * @return Builder<Article>
     */
    private function buildArticlesQuery(): Builder
    {
        $query = Article::query()
            ->with('rssFeed')
            ->whereNotNull('source_url')
            ->where('source_url', '!=', '');

        $articleId = $this->option('article');

        if (is_string($articleId) && $articleId !== '') {
            $query->whereKey((int) $articleId);
        }

        $feedId = $this->option('feed');

        if (is_string($feedId) && $feedId !== '') {
            $query->where('rss_feed_id', (int) $feedId);
        }

        if (! $this->option('all')) {
            $query->where(function (Builder $query): void {
                $query
                    ->whereNull('full_description')
                    ->orWhere('full_description', '')
                    ->orWhereNull('image_url')
                    ->orWhere('image_url', '')
                    ->orWhereNull('canonical_url')
                    ->orWhere('canonical_url', '')
                    ->orWhereNull('meta_description')
                    ->orWhere('meta_description', '')
                    ->orWhereNull('author')
                    ->orWhere('author', '')
                    ->orWhereNull('source_name')
                    ->orWhere('source_name', '')
                    ->orWhereNull('structured_data')
                    ->orWhere('structured_data', '')
                    ->orWhere('structured_data', '[]')
                    ->orWhere('structured_data', '{}');
            });
        }

        $limit = max(1, (int) $this->option('limit'));

        return $query
            ->orderBy('id')
            ->limit($limit);
    }
}
