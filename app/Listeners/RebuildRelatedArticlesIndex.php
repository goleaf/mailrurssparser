<?php

namespace App\Listeners;

use App\Events\ArticleContentChanged;
use App\Models\Article;
use App\Services\RelatedArticlesService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class RebuildRelatedArticlesIndex implements ShouldBeUnique, ShouldQueue
{
    public int $uniqueFor = 300;

    public function __construct(
        private readonly RelatedArticlesService $relatedArticles,
    ) {}

    public function handle(ArticleContentChanged $event): void
    {
        $article = Article::query()->with('tags')->find($event->articleId);

        if ($article === null) {
            return;
        }

        $this->relatedArticles->rebuildForArticle($article);
    }

    public function uniqueId(ArticleContentChanged $event): string
    {
        return 'article-related-sync:'.$event->articleId;
    }
}
