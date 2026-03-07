<?php

use App\Models\Article;
use App\Models\Category;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

beforeEach(function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }
});

it('reindexes only published articles in the requested category', function () {
    $politics = Category::factory()->create(['name' => 'Politics', 'slug' => 'politics']);
    $sport = Category::factory()->create(['name' => 'Sport', 'slug' => 'sport']);

    Article::factory()->for($politics)->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    Article::factory()->for($politics)->create([
        'status' => 'draft',
        'published_at' => now()->subHour(),
    ]);

    Article::factory()->for($sport)->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $this->artisan('rss:reindex --category=politics --chunk=1')
        ->expectsOutputToContain('Indexed 1 articles')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

it('reindexes zero articles when no published records match', function () {
    $category = Category::factory()->create(['name' => 'Politics', 'slug' => 'politics']);

    Article::factory()->for($category)->create([
        'status' => 'draft',
        'published_at' => now()->subHour(),
    ]);

    $this->artisan('rss:reindex --category=politics')
        ->expectsOutputToContain('Indexed 0 articles')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});
