<?php

use App\Models\Article;
use App\Models\Category;

it('defaults scout to the database engine outside sqlite environments', function () {
    $originalScoutDriverEnv = env('SCOUT_DRIVER');
    $originalDatabaseConnectionEnv = env('DB_CONNECTION');

    putenv('SCOUT_DRIVER=');
    putenv('DB_CONNECTION=mysql');
    $_ENV['SCOUT_DRIVER'] = '';
    $_SERVER['SCOUT_DRIVER'] = '';
    $_ENV['DB_CONNECTION'] = 'mysql';
    $_SERVER['DB_CONNECTION'] = 'mysql';

    try {
        $config = require config_path('scout.php');

        expect($config['driver'])->toBe('database');
    } finally {
        putenv('SCOUT_DRIVER'.($originalScoutDriverEnv !== null ? "={$originalScoutDriverEnv}" : ''));
        putenv('DB_CONNECTION'.($originalDatabaseConnectionEnv !== null ? "={$originalDatabaseConnectionEnv}" : ''));

        if ($originalScoutDriverEnv !== null) {
            $_ENV['SCOUT_DRIVER'] = $originalScoutDriverEnv;
            $_SERVER['SCOUT_DRIVER'] = $originalScoutDriverEnv;
        } else {
            unset($_ENV['SCOUT_DRIVER'], $_SERVER['SCOUT_DRIVER']);
        }

        if ($originalDatabaseConnectionEnv !== null) {
            $_ENV['DB_CONNECTION'] = $originalDatabaseConnectionEnv;
            $_SERVER['DB_CONNECTION'] = $originalDatabaseConnectionEnv;
        } else {
            unset($_ENV['DB_CONNECTION'], $_SERVER['DB_CONNECTION']);
        }
    }
});

it('marks only published past articles as searchable for scout-backed indexes', function () {
    $publishedArticle = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $draftArticle = Article::factory()->create([
        'status' => 'draft',
        'published_at' => now()->subHour(),
    ]);

    $futureArticle = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->addHour(),
    ]);

    expect($publishedArticle->shouldBeSearchable())->toBeTrue()
        ->and($draftArticle->shouldBeSearchable())->toBeFalse()
        ->and($futureArticle->shouldBeSearchable())->toBeFalse();
});

it('returns filtered latest search results from the public search api', function () {
    $mainCategory = Category::factory()->create(['slug' => 'main']);
    $otherCategory = Category::factory()->create(['slug' => 'other']);

    $olderMatch = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'title' => 'Energy morning briefing',
        'short_description' => 'First energy update',
        'status' => 'published',
        'published_at' => now()->subHours(3),
    ]);

    $newerMatch = Article::factory()->create([
        'category_id' => $mainCategory->id,
        'title' => 'Energy evening briefing',
        'short_description' => 'Latest energy update',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    Article::factory()->create([
        'category_id' => $otherCategory->id,
        'title' => 'Energy from another desk',
        'short_description' => 'Should be filtered out by category',
        'status' => 'published',
        'published_at' => now()->subMinutes(30),
    ]);

    $this->getJson('/api/v1/search?'.http_build_query([
        'q' => 'energy',
        'category' => 'main',
        'sort' => 'latest',
    ]))
        ->assertSuccessful()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $newerMatch->id)
        ->assertJsonPath('data.1.id', $olderMatch->id);
});
