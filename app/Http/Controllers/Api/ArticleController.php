<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ArticleIndexRequest;
use App\Http\Resources\ArticleCollection;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function index(ArticleIndexRequest $request): ArticleCollection
    {
        $validated = $request->validated();
        $query = Article::query()->published()->with(['category', 'tags', 'subCategory']);

        $this->applyFilters($query, $validated, $request);
        $this->applySorting($query, $validated['sort'] ?? 'latest');

        $articles = $query
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->appends($request->except('page'));

        return new ArticleCollection($articles);
    }

    public function show(Request $request, string $slug): ArticleResource
    {
        $article = Article::query()
            ->published()
            ->with(['category', 'tags', 'subCategory', 'rssFeed'])
            ->where('slug', $slug)
            ->firstOrFail();

        $article->incrementViews(
            $this->hashIp($request),
            $this->hashSession($request),
            [
                'device_type' => $this->detectDeviceType($request),
                'referrer_type' => $this->detectReferrerType($request),
                'referrer_domain' => parse_url((string) $request->headers->get('referer'), PHP_URL_HOST),
                'ip_address' => $request->ip(),
                'session_id' => $request->session()->getId(),
                'user_agent' => (string) $request->userAgent(),
                'referer' => $request->headers->get('referer'),
            ],
        );

        $article->setAttribute(
            'related_ids',
            Article::query()
                ->published()
                ->relatedTo($article, 6)
                ->pluck('id')
                ->all(),
        );

        return new ArticleResource($article);
    }

    public function featured(): ArticleCollection
    {
        return new ArticleCollection(
            Article::query()
                ->published()
                ->featured()
                ->with(['category', 'tags'])
                ->orderByDesc('published_at')
                ->limit(10)
                ->get(),
        );
    }

    public function breaking(): ArticleCollection
    {
        return new ArticleCollection(
            Article::query()
                ->published()
                ->breaking()
                ->with(['category'])
                ->orderByDesc('published_at')
                ->limit(5)
                ->get(),
        );
    }

    public function pinned(string $categorySlug): ArticleCollection
    {
        $category = Category::query()->where('slug', $categorySlug)->firstOrFail();

        return new ArticleCollection(
            Article::query()
                ->published()
                ->where('category_id', $category->id)
                ->pinned()
                ->with(['category', 'tags'])
                ->orderByDesc('published_at')
                ->get(),
        );
    }

    public function related(string $slug): ArticleCollection
    {
        $article = Article::query()->published()->where('slug', $slug)->firstOrFail();

        return new ArticleCollection(
            Article::query()
                ->published()
                ->relatedTo($article, 6)
                ->with(['category', 'tags'])
                ->get(),
        );
    }

    public function trending(): ArticleCollection
    {
        return new ArticleCollection(
            Article::query()
                ->published()
                ->trending(48)
                ->with(['category'])
                ->limit(20)
                ->get(),
        );
    }

    public function similar(string $slug): ArticleCollection
    {
        $article = Article::query()->published()->with('tags')->where('slug', $slug)->firstOrFail();
        $tagIds = $article->tags->pluck('id');

        $query = Article::query()
            ->published()
            ->with(['category', 'tags'])
            ->whereKeyNot($article->id)
            ->when($tagIds->isNotEmpty(), function (Builder $query) use ($tagIds): void {
                $query->whereHas('tags', function (Builder $query) use ($tagIds): void {
                    $query->whereIn('tags.id', $tagIds);
                });
            })
            ->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$article->category_id])
            ->orderByDesc('published_at')
            ->limit(5);

        return new ArticleCollection($query->get());
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyFilters(Builder $query, array $validated, Request $request): void
    {
        $query
            ->when($validated['category'] ?? null, fn (Builder $query, string $slug) => $query->byCategory($slug))
            ->when($validated['sub'] ?? null, fn (Builder $query, string $slug) => $query->bySubCategory($slug))
            ->when($validated['tag'] ?? null, fn (Builder $query, string $slug) => $query->byTag($slug))
            ->when($validated['tags'] ?? null, fn (Builder $query, array $slugs) => $query->byTag($slugs))
            ->when($validated['content_type'] ?? null, fn (Builder $query, string $type) => $query->byContentType($type))
            ->when($request->boolean('featured'), fn (Builder $query) => $query->featured())
            ->when($request->boolean('breaking'), fn (Builder $query) => $query->breaking())
            ->when($request->boolean('pinned'), fn (Builder $query) => $query->pinned())
            ->when($request->boolean('editors_choice'), fn (Builder $query) => $query->editorsChoice())
            ->when($validated['importance_min'] ?? null, fn (Builder $query, int $min) => $query->important($min))
            ->when(($validated['date_from'] ?? null) && ($validated['date_to'] ?? null), fn (Builder $query) => $query->byDateRange($validated['date_from'], $validated['date_to']))
            ->when($validated['date'] ?? null, fn (Builder $query, string $date) => $query->byDate($date))
            ->when($validated['exclude_ids'] ?? null, fn (Builder $query, array $ids) => $query->whereNotIn('id', $ids));

        if (($validated['search'] ?? null) !== null) {
            $this->applySearch($query, (string) $validated['search']);
        }
    }

    private function applySorting(Builder $query, string $sort): void
    {
        match ($sort) {
            'trending' => $query->trending(24),
            'popular' => $query->popular(),
            'importance' => $query->orderByDesc('importance')->orderByDesc('published_at'),
            'oldest' => $query->orderBy('published_at'),
            default => $query->orderByDesc('is_breaking')->orderByDesc('is_pinned')->orderByDesc('published_at'),
        };
    }

    private function applySearch(Builder $query, string $term): void
    {
        try {
            $ids = Article::search($term)
                ->query(fn (Builder $searchQuery) => $searchQuery->published())
                ->get()
                ->modelKeys();

            if ($ids !== []) {
                $query->whereIn('id', $ids);

                return;
            }
        } catch (\Throwable) {
        }

        $query->search($term);
    }

    private function hashIp(Request $request): string
    {
        return hash('sha256', (string) $request->ip());
    }

    private function hashSession(Request $request): string
    {
        return hash('sha256', $request->session()->getId().($request->userAgent() ?? ''));
    }

    private function detectDeviceType(Request $request): string
    {
        $agent = Str::lower((string) $request->userAgent());

        return match (true) {
            Str::contains($agent, ['ipad', 'tablet']) => 'tablet',
            Str::contains($agent, ['mobile', 'iphone', 'android']) => 'mobile',
            default => 'desktop',
        };
    }

    private function detectReferrerType(Request $request): string
    {
        $referer = Str::lower((string) $request->headers->get('referer'));

        if ($referer === '') {
            return 'direct';
        }

        if (Str::contains($referer, ['google.', 'yandex.', 'bing.'])) {
            return 'search';
        }

        if (Str::contains($referer, ['vk.com', 't.me', 'telegram', 'facebook.com', 'twitter.com', 'x.com'])) {
            return 'social';
        }

        if (Str::contains($referer, parse_url((string) config('app.url'), PHP_URL_HOST) ?: '')) {
            return 'internal';
        }

        return 'other';
    }
}
