<?php

use App\Models\Article;
use App\Models\Category;

it('adds trace headers and meta to api responses', function () {
    $category = Category::factory()->create(['slug' => 'main']);
    Article::factory()->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $response = $this->getJson('/api/v1/articles');
    $requestId = $response->headers->get('X-Request-Id');

    $response->assertSuccessful()
        ->assertHeader('X-Api-Version', 'v1');

    expect($requestId)->toBeString()
        ->not->toBe('')
        ->and($response->json('meta.request_id'))->toBe($requestId)
        ->and($response->json('meta.api_version'))->toBe('v1')
        ->and($response->json('meta.generated_at'))->not->toBeNull();
});

it('only rate limits repeated missing-resource lookups after the not-found threshold', function () {
    $article = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $request = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41']);

    for ($attempt = 0; $attempt < 15; $attempt++) {
        $request->getJson('/api/v1/articles/'.$article->slug)->assertSuccessful();
    }

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $request->getJson('/api/v1/articles/missing-resource')->assertNotFound();
    }

    $response = $request->getJson('/api/v1/articles/missing-resource');

    $response->assertTooManyRequests()
        ->assertHeader('X-Api-Version', 'v1')
        ->assertJson([
            'error' => 'rate_limited',
            'message' => 'Too many missing-resource requests. Please retry shortly.',
        ])
        ->assertJsonPath('meta.api_version', 'v1');

    expect($response->headers->get('X-Request-Id'))->toBe($response->json('meta.request_id'));
});

it('rate limits repeated invalid search requests after validation failures', function () {
    $request = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42']);

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $request->getJson('/api/v1/search')->assertUnprocessable();
    }

    $request->getJson('/api/v1/search')
        ->assertTooManyRequests()
        ->assertJson([
            'error' => 'rate_limited',
            'message' => 'Too many invalid search requests. Please refine the query and retry later.',
        ])
        ->assertJsonPath('meta.api_version', 'v1');
});
