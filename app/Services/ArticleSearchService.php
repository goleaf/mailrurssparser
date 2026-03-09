<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ArticleSearchService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(
        string $term,
        array $filters = [],
        string $sort = 'relevance',
        int $perPage = 20,
    ): LengthAwarePaginator {
        if ($this->canUseScoutEngine($filters, $sort)) {
            try {
                $articles = Article::search($term)
                    ->query(function (Builder $query): void {
                        $query->published()->with(['category', 'subCategory', 'tags']);
                    })
                    ->paginate($perPage);

                if ($articles->total() > 0) {
                    return $articles;
                }
            } catch (\Throwable) {
            }
        }

        $query = Article::query()
            ->published()
            ->with(['category', 'subCategory', 'tags']);

        $this->applyTerm($query, $term);
        $this->applyFilters($query, $filters);
        $this->applySort($query, $sort, $term);

        return $query->paginate($perPage);
    }

    public function applyTerm(Builder $query, string $term): Builder
    {
        return $query->search($term);
    }

    public function suggestArticles(string $term, int $limit = 12): Collection
    {
        try {
            $articles = Article::search($term)
                ->query(function (Builder $query): void {
                    $query->published()->select('id', 'title', 'slug', 'published_at');
                })
                ->take($limit)
                ->get();

            if ($articles->isNotEmpty()) {
                return $articles;
            }
        } catch (\Throwable) {
        }

        return Article::query()
            ->published()
            ->select('id', 'title', 'slug', 'published_at')
            ->search($term)
            ->latest('published_at')
            ->limit(max($limit * 2, 24))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function canUseScoutEngine(array $filters, string $sort = 'relevance'): bool
    {
        if ($sort !== 'relevance') {
            return false;
        }

        foreach (['category', 'tag', 'content_type', 'date_from', 'date_to'] as $key) {
            if (filled($filters[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['category'] ?? null, fn (Builder $query, string $slug) => $query->byCategory($slug))
            ->when($filters['tag'] ?? null, fn (Builder $query, string $slug) => $query->byTag($slug))
            ->when($filters['content_type'] ?? null, fn (Builder $query, string $type) => $query->byContentType($type))
            ->when(($filters['date_from'] ?? null) && ($filters['date_to'] ?? null), fn (Builder $query) => $query->byDateRange($filters['date_from'], $filters['date_to']));
    }

    public function applySort(Builder $query, string $sort, string $term): void
    {
        if ($sort === 'latest') {
            $query->orderByDesc('published_at');

            return;
        }

        if ($sort === 'popular') {
            $query->popular();

            return;
        }

        $escaped = $this->escapeLikeTerm($term);
        $likeOperator = $query->getConnection()->getDriverName() === 'pgsql'
            ? 'ILIKE'
            : 'LIKE';

        $query->orderByRaw(
            "CASE
                WHEN title = ? THEN 100
                WHEN title {$likeOperator} ? THEN 75
                WHEN title {$likeOperator} ? THEN 50
                WHEN short_description {$likeOperator} ? THEN 30
                WHEN full_description {$likeOperator} ? THEN 20
                WHEN author {$likeOperator} ? THEN 15
                WHEN source_name {$likeOperator} ? THEN 12
                ELSE 10
            END DESC",
            [
                $term,
                $term.'%',
                '%'.$escaped.'%',
                '%'.$escaped.'%',
                '%'.$escaped.'%',
                '%'.$escaped.'%',
                '%'.$escaped.'%',
            ],
        )->orderByDesc('published_at');
    }

    private function escapeLikeTerm(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }
}
