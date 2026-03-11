<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ArticleIndexRequest;
use App\Http\Resources\ArticleCollection;
use App\Http\Resources\ArticleListResource;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\Category;
use App\Services\ArticleSearchService;
use App\Services\RelatedArticlesService;
use App\Services\RequestLocationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ArticleSearchService $articleSearch,
        private readonly RelatedArticlesService $relatedArticles,
        private readonly RequestLocationService $requestLocation,
    ) {}

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

    public function show(Request $request, string $slug): JsonResponse
    {
        $article = $this->resolvePublishedArticle($slug, ['category', 'tags', 'subCategory', 'rssFeed', 'seo']);

        if ($request->boolean('track', true)) {
            $location = $this->requestLocation->resolve($request);

            $article->incrementViews(
                $this->hashIp($request),
                $this->hashSession($request),
                [
                    ...$location,
                    'device_type' => $this->detectDeviceType($request),
                    'referrer_type' => $this->detectReferrerType($request),
                    'referrer_domain' => $this->extractUriHost($request->headers->get('referer')),
                    'ip_address' => $request->ip(),
                    'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                    'user_agent' => (string) $request->userAgent(),
                    'referer' => $request->headers->get('referer'),
                ],
            );
        }

        $related = $this->relatedArticles->getRelated($article, 8);
        $primaryRelated = $related
            ->filter(fn (Article $item): bool => (bool) ($item->pivot?->same_category || $item->pivot?->same_sub_category))
            ->take(4)
            ->values();

        if ($primaryRelated->isEmpty()) {
            $primaryRelated = $related->take(4)->values();
        }

        $similar = $this->relatedArticles->getSimilar($article, 5, $primaryRelated->modelKeys());
        $excludedIds = array_values(array_unique([
            $article->getKey(),
            ...$primaryRelated->modelKeys(),
            ...$similar->modelKeys(),
        ]));
        $moreFromCategory = $this->relatedArticles->getMoreFromCategory($article, 3, $excludedIds);

        $article->setAttribute(
            'related_ids',
            $related->modelKeys(),
        );
        $article->setAttribute('related_articles', ArticleListResource::collection($primaryRelated)->resolve());
        $article->setAttribute('similar_articles', ArticleListResource::collection($similar)->resolve());
        $article->setAttribute('more_from_category', ArticleListResource::collection($moreFromCategory)->resolve());

        return ArticleResource::make($article)
            ->response()
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function featured(): ArticleCollection
    {
        return new ArticleCollection(
            Article::query()
                ->published()
                ->featured()
                ->with(['category', 'subCategory', 'tags'])
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
                ->with(['category', 'subCategory'])
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
                ->inCategory($category)
                ->pinned()
                ->with(['category', 'subCategory', 'tags'])
                ->orderByDesc('published_at')
                ->get(),
        );
    }

    public function related(string $slug): ArticleCollection
    {
        $article = $this->resolvePublishedArticle($slug);

        return new ArticleCollection($this->relatedArticles->getRelated($article, 6));
    }

    public function trending(): ArticleCollection
    {
        return new ArticleCollection(
            Article::query()
                ->published()
                ->trending(48)
                ->with(['category', 'subCategory'])
                ->limit(20)
                ->get(),
        );
    }

    public function similar(string $slug): ArticleCollection
    {
        $article = $this->resolvePublishedArticle($slug);

        return new ArticleCollection($this->relatedArticles->getSimilar($article, 5));
    }

    /**
     * @param  array<int, string>  $relations
     */
    private function resolvePublishedArticle(string $identifier, array $relations = []): Article
    {
        return Article::query()
            ->published()
            ->with($relations)
            ->where(function (Builder $query) use ($identifier): void {
                $query->where('slug', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere((new Article)->getQualifiedKeyName(), (int) $identifier);
                }
            })
            ->firstOrFail();
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
        $this->articleSearch->applyTerm($query, $term);
    }

    private function hashIp(Request $request): string
    {
        return hash('sha256', (string) $request->ip());
    }

    private function hashSession(Request $request): string
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : 'stateless';

        return hash('sha256', $sessionId.($request->userAgent() ?? ''));
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
        $referer = str((string) $request->headers->get('referer'))->trim()->lower();
        $refererAuthority = Str::lower($this->extractUriAuthority((string) $referer) ?? '');
        $appAuthority = Str::lower($this->extractUriAuthority((string) config('app.url')) ?? '');

        if ($referer->isEmpty()) {
            return 'direct';
        }

        if ($referer->contains(['google.', 'yandex.', 'bing.'])) {
            return 'search';
        }

        if ($referer->contains(['vk.com', 't.me', 'telegram', 'facebook.com', 'twitter.com', 'x.com'])) {
            return 'social';
        }

        if ($referer->doesntContain(['http://', 'https://'])) {
            return 'other';
        }

        if ($refererAuthority !== '' && $appAuthority !== '' && $refererAuthority === $appAuthority) {
            return 'internal';
        }

        return 'other';
    }

    private function extractUriAuthority(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Uri::of($value)->authority();
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractUriHost(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Uri::of($value)->host();
        } catch (\Throwable) {
            return null;
        }
    }
}
