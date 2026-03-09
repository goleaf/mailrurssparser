<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\ArticleIndexRequest;
use App\Http\Requests\Public\SearchPageRequest;
use App\Models\Article;
use App\Models\Bookmark;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\Tag;
use App\Services\ArticleCacheService;
use App\Services\ArticleSearchService;
use App\Services\MetricTracker;
use App\Services\RelatedArticlesService;
use App\Services\RequestLocationService;
use App\Services\TrackedMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Illuminate\View\View;

class PublicSiteController extends Controller
{
    public function __construct(
        private readonly ArticleCacheService $articleCache,
        private readonly ArticleSearchService $articleSearch,
        private readonly RelatedArticlesService $relatedArticles,
        private readonly RequestLocationService $requestLocation,
    ) {}

    public function home(ArticleIndexRequest $request): View
    {
        $validated = $request->validated();
        $articlesQuery = Article::query()
            ->published()
            ->with(['category', 'subCategory', 'tags']);

        $this->applyArticleIndexFilters($articlesQuery, $validated);
        $this->applyArticleSort($articlesQuery, $validated['sort'] ?? 'latest');

        $latestArticles = $articlesQuery
            ->paginate((int) ($validated['per_page'] ?? 12))
            ->withQueryString();

        $featuredArticles = $this->articleCache->getFeaturedArticles()->take(4);
        $breakingArticles = $this->articleCache->getBreakingNews()->take(6);
        $editorsChoice = Article::query()
            ->published()
            ->editorsChoice()
            ->with(['category', 'subCategory', 'tags'])
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();
        $statsOverview = $this->articleCache->getStatsOverview();

        return view('public.home', $this->sharedViewData($request, 'home', [
            'featuredArticles' => $featuredArticles,
            'breakingArticles' => $breakingArticles,
            'editorsChoice' => $editorsChoice,
            'latestArticles' => $latestArticles,
            'metaTitle' => config('app.name', 'Новостной Портал').' - Главная',
            'metaDescription' => 'Главная страница новостного портала с главными, срочными и редакционными материалами.',
            'statsOverview' => $statsOverview,
        ]));
    }

    public function category(ArticleIndexRequest $request, string $slug): View
    {
        $category = Category::query()
            ->active()
            ->with('activeSubCategories')
            ->where('slug', $slug)
            ->firstOrFail();

        $validated = $request->validated();
        $articlesQuery = Article::query()
            ->published()
            ->with(['category', 'subCategory', 'tags'])
            ->inCategory($category);

        $this->applyArticleIndexFilters($articlesQuery, array_merge($validated, [
            'category' => $slug,
        ]));
        $this->applyArticleSort($articlesQuery, $validated['sort'] ?? 'latest');

        $articles = $articlesQuery
            ->paginate((int) ($validated['per_page'] ?? 12))
            ->withQueryString();

        $pinnedArticles = Article::query()
            ->published()
            ->with(['category', 'subCategory', 'tags'])
            ->inCategory($category)
            ->pinned()
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('public.category', $this->sharedViewData($request, 'category', [
            'articles' => $articles,
            'category' => $category,
            'metaTitle' => ($category->meta_title ?: $category->name).' - '.config('app.name'),
            'metaDescription' => $category->meta_description ?: Str::limit((string) $category->description, 160),
            'pinnedArticles' => $pinnedArticles,
        ]));
    }

    public function tag(ArticleIndexRequest $request, string $slug): View
    {
        $tag = Tag::query()->where('slug', $slug)->firstOrFail();
        $validated = $request->validated();

        $articlesQuery = Article::query()
            ->published()
            ->with(['category', 'subCategory', 'tags'])
            ->byTag($slug);

        $this->applyArticleIndexFilters($articlesQuery, array_merge($validated, [
            'tag' => $slug,
        ]));
        $this->applyArticleSort($articlesQuery, $validated['sort'] ?? 'latest');

        $articles = $articlesQuery
            ->paginate((int) ($validated['per_page'] ?? 12))
            ->withQueryString();

        $relatedTags = Tag::query()
            ->popular()
            ->whereKeyNot($tag->getKey())
            ->limit(12)
            ->get();

        return view('public.tag', $this->sharedViewData($request, 'tag', [
            'articles' => $articles,
            'metaTitle' => '#'.$tag->name.' - '.config('app.name'),
            'metaDescription' => $tag->description ?: 'Подборка материалов по тегу '.$tag->name.'.',
            'relatedTags' => $relatedTags,
            'tag' => $tag,
        ]));
    }

    public function article(Request $request, string $slug): View
    {
        $article = Article::query()
            ->published()
            ->with(['category', 'subCategory', 'tags', 'rssFeed'])
            ->where('slug', $slug)
            ->firstOrFail();

        $this->trackArticleView($request, $article);

        $relatedArticles = $this->relatedArticles->getRelated($article, 4);
        $similarArticles = $this->relatedArticles->getSimilar($article, 4, $relatedArticles->modelKeys());
        $excludedIds = array_values(array_unique([
            $article->getKey(),
            ...$relatedArticles->modelKeys(),
            ...$similarArticles->modelKeys(),
        ]));
        $moreFromCategory = $this->relatedArticles->getMoreFromCategory($article, 4, $excludedIds);
        $popularArticles = Article::query()
            ->published()
            ->with(['category', 'subCategory', 'tags'])
            ->popular()
            ->limit(5)
            ->get();
        $seoData = $article->getSeoData();

        return view('public.article', $this->sharedViewData($request, 'article', [
            'article' => $article,
            'canonicalUrl' => $seoData['canonical_url'] ?? route('articles.show', ['slug' => $article->slug]),
            'metaDescription' => $seoData['meta_description'] ?? $article->meta_description,
            'metaTitle' => $seoData['meta_title'] ?? $article->meta_title,
            'moreFromCategory' => $moreFromCategory,
            'popularArticles' => $popularArticles,
            'relatedArticles' => $relatedArticles,
            'similarArticles' => $similarArticles,
            'structuredData' => $seoData['structured_data'] ?? null,
        ]));
    }

    public function search(SearchPageRequest $request): View
    {
        $validated = $request->validated();
        $query = trim((string) ($validated['q'] ?? ''));
        $results = null;
        $suggestedCategories = collect();
        $suggestedTags = collect();

        if ($query !== '') {
            $results = $this->articleSearch->search(
                $query,
                $validated,
                $validated['sort'] ?? 'relevance',
                (int) ($validated['per_page'] ?? 12),
            )->withQueryString();

            if ($results->total() === 0) {
                $suggestedCategories = Category::query()
                    ->active()
                    ->search($query)
                    ->limit(6)
                    ->get();

                $suggestedTags = Tag::query()
                    ->popular()
                    ->search($query)
                    ->limit(8)
                    ->get();
            }
        }

        return view('public.search', $this->sharedViewData($request, 'search', [
            'metaTitle' => $query === ''
                ? 'Поиск - '.config('app.name')
                : 'Поиск: '.$query.' - '.config('app.name'),
            'metaDescription' => $query === ''
                ? 'Поиск новостей по темам, рубрикам и тегам.'
                : 'Результаты поиска по запросу '.$query.'.',
            'query' => $query,
            'results' => $results,
            'suggestedCategories' => $suggestedCategories,
            'suggestedTags' => $suggestedTags,
        ]));
    }

    public function bookmarks(Request $request): View
    {
        $bookmarks = Bookmark::query()
            ->where('session_hash', $this->sessionHash($request))
            ->whereHas('article', fn (Builder $query): Builder => $query->published())
            ->with(['article.category', 'article.subCategory', 'article.tags'])
            ->latest('created_at')
            ->get()
            ->pluck('article')
            ->filter()
            ->values();

        return view('public.bookmarks', $this->sharedViewData($request, 'bookmarks', [
            'articles' => $bookmarks,
            'metaTitle' => 'Закладки - '.config('app.name'),
            'metaDescription' => 'Сохранённые статьи для текущего браузера и устройства.',
        ]));
    }

    public function toggleBookmark(Request $request, Article $article): RedirectResponse
    {
        abort_unless(
            Article::query()->published()->whereKey($article->getKey())->exists(),
            Response::HTTP_NOT_FOUND,
        );

        $sessionHash = $this->sessionHash($request);
        $existingBookmark = Bookmark::query()
            ->where('session_hash', $sessionHash)
            ->where('article_id', $article->getKey())
            ->first();

        if ($existingBookmark !== null) {
            $existingBookmark->delete();
            $article->decrement('bookmarks_count');
            app(MetricTracker::class)->record(TrackedMetric::BookmarkRemoved, measurable: $article);

            return back(303)->with('status', 'Статья удалена из закладок.');
        }

        Bookmark::query()->create([
            'article_id' => $article->getKey(),
            'session_hash' => $sessionHash,
        ]);

        $article->increment('bookmarks_count');
        app(MetricTracker::class)->record(TrackedMetric::BookmarkAdded, measurable: $article);

        return back(303)->with('status', 'Статья сохранена в закладки.');
    }

    public function stats(Request $request): View
    {
        $overview = $this->articleCache->getStatsOverview();
        $popularArticles = Article::query()
            ->published()
            ->with(['category', 'subCategory', 'tags'])
            ->orderByDesc('views_count')
            ->limit(10)
            ->get();
        $topCategories = Category::query()
            ->active()
            ->whereHas('articles', fn (Builder $query): Builder => $query->published())
            ->withCount(['articles as published_count' => fn (Builder $query): Builder => $query->published()])
            ->orderByDesc('published_count')
            ->limit(8)
            ->get();
        $feedPerformance = RssFeed::query()
            ->with('category')
            ->withCount([
                'articles',
                'articles as today_articles_count' => fn (Builder $query): Builder => $query->publishedBetween(today()->startOfDay(), today()->endOfDay()),
            ])
            ->orderByDesc('last_parsed_at')
            ->limit(10)
            ->get();

        return view('public.stats', $this->sharedViewData($request, 'stats', [
            'feedPerformance' => $feedPerformance,
            'metaTitle' => 'Статистика - '.config('app.name'),
            'metaDescription' => 'Сводка по публикациям, чтению и работе RSS-источников.',
            'overview' => $overview,
            'popularArticles' => $popularArticles,
            'topCategories' => $topCategories,
        ]));
    }

    public function info(Request $request, string $page): View
    {
        $pages = $this->infoPages();
        abort_unless(array_key_exists($page, $pages), Response::HTTP_NOT_FOUND);

        return view('public.info', $this->sharedViewData($request, $page, [
            'metaTitle' => $pages[$page]['title'].' - '.config('app.name'),
            'metaDescription' => $pages[$page]['subtitle'],
            'page' => $pages[$page],
        ]));
    }

    public function notFound(Request $request): Response
    {
        return response()->view('public.not-found', $this->sharedViewData($request, 'not-found', [
            'metaTitle' => 'Страница не найдена - '.config('app.name'),
            'metaDescription' => 'Запрошенная страница недоступна. Вернитесь на главную страницу портала.',
        ]), Response::HTTP_NOT_FOUND);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sharedViewData(Request $request, string $activeNav, array $payload = []): array
    {
        $bookmarkedArticleIds = $this->bookmarkedArticleIds($request);

        return array_merge([
            'activeNav' => $activeNav,
            'bookmarkCount' => count($bookmarkedArticleIds),
            'bookmarkedArticleIds' => $bookmarkedArticleIds,
            'canonicalUrl' => url()->current(),
            'metaDescription' => 'Лента новостей, аналитики и тематических подборок.',
            'metaTitle' => config('app.name', 'Новостной Портал'),
            'navigationCategories' => $this->articleCache->getCategories()->take(8),
            'navigationTags' => $this->articleCache->getTrendingTags(10),
            'structuredData' => null,
        ], $payload);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyArticleIndexFilters(Builder $query, array $validated): void
    {
        $query
            ->when($validated['category'] ?? null, fn (Builder $query, string $slug) => $query->byCategory($slug))
            ->when($validated['sub'] ?? null, fn (Builder $query, string $slug) => $query->bySubCategory($slug))
            ->when($validated['tag'] ?? null, fn (Builder $query, string $slug) => $query->byTag($slug))
            ->when($validated['tags'] ?? null, fn (Builder $query, array $slugs) => $query->byTag($slugs))
            ->when($validated['content_type'] ?? null, fn (Builder $query, string $type) => $query->byContentType($type))
            ->when(($validated['date_from'] ?? null) && ($validated['date_to'] ?? null), fn (Builder $query) => $query->byDateRange($validated['date_from'], $validated['date_to']))
            ->when($validated['date'] ?? null, fn (Builder $query, string $date) => $query->byDate($date))
            ->when($validated['search'] ?? null, fn (Builder $query, string $term) => $this->articleSearch->applyTerm($query, $term))
            ->when($validated['featured'] ?? null, fn (Builder $query) => $query->featured())
            ->when($validated['breaking'] ?? null, fn (Builder $query) => $query->breaking())
            ->when($validated['pinned'] ?? null, fn (Builder $query) => $query->pinned())
            ->when($validated['editors_choice'] ?? null, fn (Builder $query) => $query->editorsChoice())
            ->when($validated['importance_min'] ?? null, fn (Builder $query, int $minimum) => $query->important($minimum));
    }

    private function applyArticleSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'trending' => $query->trending(24),
            'popular' => $query->popular(),
            'importance' => $query->orderByDesc('importance')->orderByDesc('published_at'),
            'oldest' => $query->orderBy('published_at'),
            default => $query->orderByDesc('is_breaking')->orderByDesc('is_pinned')->orderByDesc('published_at'),
        };
    }

    /**
     * @return array<string, array{title: string, subtitle: string, sections: array<int, array{title: string, body: string}>}>
     */
    private function infoPages(): array
    {
        return [
            'about' => [
                'title' => 'О проекте',
                'subtitle' => 'Независимая витрина ленты, срочных сообщений и тематических подборок из подключённых RSS-источников.',
                'sections' => [
                    [
                        'title' => 'Как работает редакционная витрина',
                        'body' => 'Портал собирает публикации из RSS-источников, нормализует рубрики, считает вовлечённость и выводит материалы в простой и быстрой браузерной витрине.',
                    ],
                    [
                        'title' => 'Что видно на публичной части',
                        'body' => 'Главная страница выделяет срочные и редакционные материалы, рубрики и теги ведут к фильтрованным подборкам, а страница статистики показывает сводку по публикациям и источникам.',
                    ],
                    [
                        'title' => 'Для кого это сделано',
                        'body' => 'Для читателей, которым нужна понятная лента без лишней сложности, и для редакторов, которым важно видеть, какие материалы и категории реально работают.',
                    ],
                ],
            ],
            'contact' => [
                'title' => 'Контакты',
                'subtitle' => 'Свяжитесь с командой, если нужно уточнить источник данных, прислать правку или обсудить интеграцию.',
                'sections' => [
                    [
                        'title' => 'Редакционные вопросы',
                        'body' => 'По вопросам публикаций, карточек материалов и категорий используйте основной адрес редакции или внутренний канал команды.',
                    ],
                    [
                        'title' => 'Техническая поддержка',
                        'body' => 'Если вы заметили проблемы с RSS, страницами категорий, статистикой или закладками, опишите шаги воспроизведения и ссылку на страницу.',
                    ],
                    [
                        'title' => 'Партнёрства',
                        'body' => 'Для новых источников, синдикации или white-label интеграций подготовьте RSS-ленту, частоту обновления и контакт ответственного владельца.',
                    ],
                ],
            ],
            'privacy' => [
                'title' => 'Политика приватности',
                'subtitle' => 'Краткое описание того, какие данные нужны публичной части сайта для чтения материалов, статистики и закладок.',
                'sections' => [
                    [
                        'title' => 'Чтение материалов и аналитика',
                        'body' => 'При открытии статьи система может учитывать технические данные запроса, чтобы посчитать просмотры, примерную географию и каналы перехода без отдельного пользовательского кабинета.',
                    ],
                    [
                        'title' => 'Как работают закладки',
                        'body' => 'Закладки привязаны к текущему браузеру и устройству через технический session hash, поэтому они доступны без авторизации, но не синхронизируются между устройствами.',
                    ],
                    [
                        'title' => 'Поиск и настройки темы',
                        'body' => 'Поисковые запросы передаются серверу только для выдачи результатов, а выбранная тема интерфейса сохраняется локально в браузере.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<int>
     */
    private function bookmarkedArticleIds(Request $request): array
    {
        return Bookmark::query()
            ->where('session_hash', $this->sessionHash($request))
            ->pluck('article_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function sessionHash(Request $request): string
    {
        return hash('sha256', (string) $request->ip().($request->userAgent() ?? ''));
    }

    private function trackArticleView(Request $request, Article $article): void
    {
        $location = $this->requestLocation->resolve($request);

        $article->incrementViews(
            hash('sha256', (string) $request->ip()),
            hash('sha256', ($request->hasSession() ? $request->session()->getId() : 'stateless').($request->userAgent() ?? '')),
            array_merge($location, [
                'device_type' => $this->detectDeviceType($request),
                'ip_address' => $request->ip(),
                'referrer_domain' => $this->extractUriHost($request->headers->get('referer')),
                'referrer_type' => $this->detectReferrerType($request),
                'referer' => $request->headers->get('referer'),
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'user_agent' => (string) $request->userAgent(),
            ]),
        );
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
