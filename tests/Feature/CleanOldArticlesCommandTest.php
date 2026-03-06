<?php

use App\Models\Article;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

beforeEach(function () {
    if (! trait_exists(Laravel\Scout\Searchable::class)) {
        $this->markTestSkipped('Laravel Scout is not installed.');
    }
});

it('shows count and exits on dry run', function () {
    $cutoff = now()->subDays(90);

    $archived = Article::factory()->create([
        'status' => 'archived',
        'published_at' => $cutoff->copy()->subDay(),
    ]);

    $trashed = Article::factory()->create();
    $trashed->delete();
    Article::withTrashed()->whereKey($trashed->id)->update([
        'deleted_at' => $cutoff->copy()->subDay(),
    ]);

    $this->artisan('rss:clean --days=90 --dry-run')
        ->expectsOutputToContain('Found 2 articles to clean')
        ->assertExitCode(SymfonyCommand::SUCCESS);

    expect(Article::withTrashed()->whereKey([$archived->id, $trashed->id])->count())->toBe(2);
});

it('deletes archived and trashed articles when forced', function () {
    $cutoff = now()->subDays(30);

    $archived = Article::factory()->create([
        'status' => 'archived',
        'published_at' => $cutoff->copy()->subDay(),
    ]);

    $trashed = Article::factory()->create();
    $trashed->delete();
    Article::withTrashed()->whereKey($trashed->id)->update([
        'deleted_at' => $cutoff->copy()->subDay(),
    ]);

    Article::factory()->create([
        'status' => 'archived',
        'published_at' => now()->subDays(10),
    ]);

    $this->artisan('rss:clean --days=30 --force')
        ->expectsOutputToContain('✅ Deleted 2 articles')
        ->assertExitCode(SymfonyCommand::SUCCESS);

    expect(Article::withTrashed()->whereKey([$archived->id, $trashed->id])->count())->toBe(0);
});
