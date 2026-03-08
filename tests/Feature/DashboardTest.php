<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\User;
use App\Services\ArticleStatus;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    Article::factory()
        ->count(15)
        ->sequence(
            fn ($sequence): array => [
                'status' => ArticleStatus::Published->value,
                'published_at' => now()->subMinutes($sequence->index),
            ],
        )
        ->create();
    Article::factory()->create([
        'status' => ArticleStatus::Draft->value,
        'published_at' => now(),
    ]);
    $article = Article::query()->published()->firstOrFail();

    ArticleView::factory()->count(2)->create([
        'article_id' => $article->id,
        'country_code' => 'DE',
        'timezone' => 'Europe/Berlin',
        'viewed_at' => now()->subHour(),
    ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.published', 15)
            ->where('overview.today', 15)
            ->where('overview.active_feeds', 0)
            ->where('overview.top_countries.0.country_code', 'DE')
            ->where('overview.top_countries.0.view_count', 2)
            ->where('overview.top_timezones.0.timezone', 'Europe/Berlin')
            ->where('overview.top_timezones.0.view_count', 2)
            ->has('overview.top_categories')
            ->has('articles.data', 12)
            ->where('articles.data.0.status', ArticleStatus::Published->value),
        );
});

test('dashboard feed is configured for inertia infinite scroll', function () {
    Article::factory()
        ->count(13)
        ->sequence(
            fn ($sequence): array => [
                'status' => ArticleStatus::Published->value,
                'published_at' => now()->subMinutes($sequence->index),
            ],
        )
        ->create();

    $user = User::factory()->create();
    $this->actingAs($user);

    $version = app(HandleInertiaRequests::class)->version(Request::create('/dashboard'));

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertHeader('Vary', 'X-Inertia')
        ->assertJsonPath('component', 'Dashboard')
        ->assertJsonPath('mergeProps.0', 'articles.data')
        ->assertJsonPath('scrollProps.articles.pageName', 'articles')
        ->assertJsonCount(12, 'props.articles.data');
});
