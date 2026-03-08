<?php

use App\Models\Article;
use App\Models\RssFeed;
use App\Services\RssParserService;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

afterEach(function () {
    \Mockery::close();
});

it('returns failure when no matching articles exist', function () {
    $this->artisan('rss:enrich-articles')
        ->expectsOutputToContain('No matching articles found for enrichment.')
        ->assertExitCode(SymfonyCommand::FAILURE);
});

it('enriches only incomplete stored articles by default', function () {
    $feed = RssFeed::factory()->create();

    $incompleteArticle = Article::factory()->for($feed, 'rssFeed')->create([
        'source_url' => 'https://example.test/incomplete-article',
        'full_description' => null,
        'image_url' => null,
        'canonical_url' => null,
        'meta_description' => null,
        'author' => config('rss.article.default_author'),
        'source_name' => config('rss.source_name'),
        'structured_data' => null,
    ]);

    Article::factory()->for($feed, 'rssFeed')->create([
        'source_url' => 'https://example.test/complete-article',
        'full_description' => '<p>Stored article body.</p>',
        'image_url' => 'https://cdn.example.test/complete-image.jpg',
        'canonical_url' => 'https://example.test/articles/complete-article',
        'meta_description' => 'Stored article subtitle.',
        'author' => 'Stored Author',
        'source_name' => 'Stored Publisher',
        'structured_data' => [
            'headline' => 'Stored article title',
        ],
    ]);

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('enrichExistingArticle')
        ->once()
        ->with(\Mockery::on(fn (Article $article): bool => $article->is($incompleteArticle)), false)
        ->andReturn(true);
    app()->instance(RssParserService::class, $parser);

    $this->artisan('rss:enrich-articles --limit=10')
        ->expectsOutputToContain('Total: 1 updated | 0 skipped | 0 errors')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

it('supports all force mode for already complete articles', function () {
    $feed = RssFeed::factory()->create();
    $otherFeed = RssFeed::factory()->create();

    $article = Article::factory()->for($feed, 'rssFeed')->create([
        'source_url' => 'https://example.test/complete-article',
        'full_description' => '<p>Stored article body.</p>',
        'image_url' => 'https://cdn.example.test/complete-image.jpg',
        'canonical_url' => 'https://example.test/articles/complete-article',
        'meta_description' => 'Stored article subtitle.',
        'author' => 'Stored Author',
        'source_name' => 'Stored Publisher',
        'structured_data' => [
            'headline' => 'Stored article title',
        ],
    ]);

    Article::factory()->for($otherFeed, 'rssFeed')->create([
        'source_url' => 'https://example.test/other-feed-article',
        'full_description' => '<p>Other feed article body.</p>',
        'image_url' => 'https://cdn.example.test/other-image.jpg',
        'canonical_url' => 'https://example.test/articles/other-feed-article',
        'meta_description' => 'Other feed article subtitle.',
        'author' => 'Other Author',
        'source_name' => 'Other Publisher',
        'structured_data' => [
            'headline' => 'Other article title',
        ],
    ]);

    $parser = \Mockery::mock(RssParserService::class);
    $parser->shouldReceive('enrichExistingArticle')
        ->once()
        ->with(\Mockery::on(fn (Article $storedArticle): bool => $storedArticle->is($article)), true)
        ->andReturn(true);
    app()->instance(RssParserService::class, $parser);

    $this->artisan(sprintf('rss:enrich-articles --feed=%d --all --force --limit=10', $feed->id))
        ->expectsOutputToContain('Total: 1 updated | 0 skipped | 0 errors')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});
