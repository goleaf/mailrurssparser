<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArticleListResource;
use App\Models\Article;
use App\Services\ArticleCacheService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ArticleCacheService $articleCacheService): Response
    {
        return Inertia::render('Dashboard', [
            'overview' => $this->overviewPayload($articleCacheService->getStatsOverview()),
            'articles' => Inertia::scroll(fn () => ArticleListResource::collection(
                Article::query()
                    ->published()
                    ->with(['category', 'tags'])
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->cursorPaginate(perPage: 12, cursorName: 'articles')
            )),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overview
     * @return array{
     *     published: int,
     *     today: int,
     *     weekly_views: int,
     *     active_feeds: int,
     *     top_countries: list<array{country_code: string, view_count: int}>,
     *     top_timezones: list<array{timezone: string, view_count: int}>,
     *     top_categories: list<array{
     *         id: int|string|null,
     *         name: string,
     *         slug: string,
     *         color: string|null,
     *         icon: string|null,
     *         article_count: int
     *     }>,
     *     last_parse: string|null
     * }
     */
    private function overviewPayload(array $overview): array
    {
        /** @var array<int, array<string, mixed>> $topCountries */
        $topCountries = array_filter(
            data_get($overview, 'top_countries', []),
            fn (mixed $country): bool => is_array($country),
        );

        /** @var array<int, array<string, mixed>> $topCategories */
        $topCategories = array_filter(
            data_get($overview, 'top_categories', []),
            fn (mixed $category): bool => is_array($category),
        );
        /** @var array<int, array<string, mixed>> $topTimezones */
        $topTimezones = array_filter(
            data_get($overview, 'top_timezones', []),
            fn (mixed $timezone): bool => is_array($timezone),
        );

        $lastParse = data_get($overview, 'last_parse');

        return [
            'published' => (int) data_get($overview, 'articles.total', 0),
            'today' => (int) data_get($overview, 'articles.today', 0),
            'weekly_views' => (int) data_get($overview, 'views.this_week', 0),
            'active_feeds' => (int) data_get($overview, 'feeds.active', 0),
            'top_countries' => array_values(array_map(
                fn (array $country): array => [
                    'country_code' => strtoupper((string) ($country['country_code'] ?? '')),
                    'view_count' => (int) ($country['view_count'] ?? 0),
                ],
                array_slice($topCountries, 0, 5),
            )),
            'top_timezones' => array_values(array_map(
                fn (array $timezone): array => [
                    'timezone' => (string) ($timezone['timezone'] ?? ''),
                    'view_count' => (int) ($timezone['view_count'] ?? 0),
                ],
                array_slice($topTimezones, 0, 5),
            )),
            'top_categories' => array_values(array_map(
                fn (array $category): array => [
                    'id' => $category['id'] ?? null,
                    'name' => (string) ($category['name'] ?? ''),
                    'slug' => (string) ($category['slug'] ?? ''),
                    'color' => isset($category['color']) ? (string) $category['color'] : null,
                    'icon' => isset($category['icon']) ? (string) $category['icon'] : null,
                    'article_count' => (int) ($category['article_count'] ?? 0),
                ],
                array_slice($topCategories, 0, 5),
            )),
            'last_parse' => is_string($lastParse) && $lastParse !== '' ? $lastParse : null,
        ];
    }
}
