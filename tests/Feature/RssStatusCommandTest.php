<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

afterEach(function () {
    Carbon::setTestNow();
});

it('shows feed statuses and totals', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-07 12:00:00'));

    $category = Category::factory()->create(['name' => 'Politics', 'slug' => 'politics']);

    $disabledFeed = RssFeed::factory()->for($category)->create([
        'title' => 'Disabled feed',
        'is_active' => false,
        'consecutive_failures' => 10,
    ]);

    $errorFeed = RssFeed::factory()->for($category)->create([
        'title' => 'Error feed',
        'last_error' => 'Timeout',
        'last_run_error_count' => 3,
        'next_parse_at' => now()->addMinutes(15),
    ]);

    $dueFeed = RssFeed::factory()->for($category)->create([
        'title' => 'Due feed',
        'next_parse_at' => now()->subMinute(),
        'last_run_new_count' => 4,
    ]);

    $scheduledFeed = RssFeed::factory()->for($category)->create([
        'title' => 'Scheduled feed',
        'next_parse_at' => now()->addMinutes(10),
        'last_parsed_at' => now()->subMinutes(5),
        'last_run_new_count' => 2,
    ]);

    Article::factory()->for($scheduledFeed, 'rssFeed')->count(2)->create([
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $exitCode = Artisan::call('rss:status');
    $output = Artisan::output();

    expect($exitCode)->toBe(SymfonyCommand::SUCCESS);
    expect($output)
        ->toContain('Disabled feed')
        ->toContain('❌ Disabled(10 failures)')
        ->toContain('Due feed')
        ->toContain('⏰ Due Now')
        ->toContain('Error feed')
        ->toContain('⚠ Errors:3')
        ->toContain('Scheduled feed')
        ->toContain('⏳ in 10m')
        ->toContain('Total: 4 feeds, 3 active, 1 due, 2 total articles');
});

it('filters feeds by category and renders json output', function () {
    $politics = Category::factory()->create(['name' => 'Politics', 'slug' => 'politics']);
    $sport = Category::factory()->create(['name' => 'Sport', 'slug' => 'sport']);

    $feed = RssFeed::factory()->for($politics)->create([
        'title' => 'Politics feed',
        'next_parse_at' => now()->addMinutes(5),
    ]);

    RssFeed::factory()->for($sport)->create([
        'title' => 'Sport feed',
    ]);

    $exitCode = Artisan::call('rss:status', [
        '--category' => 'politics',
        '--json' => true,
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(SymfonyCommand::SUCCESS);
    expect($output)
        ->toContain('Politics feed')
        ->toContain((string) $feed->id)
        ->toContain('Politics')
        ->toContain('Total: 1 feeds, 1 active, 0 due, 0 total articles');
});

it('returns failure when no feeds match the requested category', function () {
    Category::factory()->create(['name' => 'Politics', 'slug' => 'politics']);

    $this->artisan('rss:status --category=sport')
        ->expectsOutputToContain('No feeds found.')
        ->assertExitCode(SymfonyCommand::FAILURE);
});
