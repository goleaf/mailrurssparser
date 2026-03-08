<?php

use App\Filament\Pages\ParseHistory;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

it('loads parse logs on the history page', function () {
    $this->actingAs(User::factory()->create());

    $feed = RssFeed::factory()->create(['title' => 'Politics feed']);
    $log = RssParseLog::factory()->create([
        'rss_feed_id' => $feed->id,
        'error_message' => 'Timeout',
        'item_errors' => [['title' => 'Item 1', 'error' => 'Bad XML']],
    ]);

    Livewire::test(ParseHistory::class)
        ->assertSee('Politics feed')
        ->call('toggleExpanded', $log->id)
        ->assertSee('Timeout');
});

it('filters parse logs by feed and status', function () {
    $this->actingAs(User::factory()->create());

    $successFeed = RssFeed::factory()->create(['title' => 'Main feed']);
    $failedFeed = RssFeed::factory()->create(['title' => 'Sport feed']);

    RssParseLog::factory()->create([
        'rss_feed_id' => $successFeed->id,
        'success' => true,
    ]);
    $failedLog = RssParseLog::factory()->create([
        'rss_feed_id' => $failedFeed->id,
        'success' => false,
        'error_message' => 'HTTP 500',
    ]);

    Livewire::test(ParseHistory::class)
        ->set('feed', (string) $failedFeed->id)
        ->set('status', 'failure')
        ->assertSee('Sport feed')
        ->call('toggleExpanded', $failedLog->id)
        ->assertSee('HTTP 500');
});

it('expands a parse log row to show item errors', function () {
    $this->actingAs(User::factory()->create());

    $feed = RssFeed::factory()->create();
    $log = RssParseLog::factory()->create([
        'rss_feed_id' => $feed->id,
        'item_errors' => [['title' => 'Bad item', 'error' => 'Malformed content']],
    ]);

    Livewire::test(ParseHistory::class)
        ->assertDontSee('Malformed content')
        ->call('toggleExpanded', $log->id)
        ->assertSee('Malformed content');
});

it('shows the running parse count in the summary cards', function () {
    Carbon::setTestNow('2026-03-08 12:00:00');

    $this->actingAs(User::factory()->create());

    RssParseLog::factory()->create([
        'started_at' => Carbon::parse('2026-03-08 11:50:00'),
        'finished_at' => null,
    ]);
    RssParseLog::factory()->create([
        'started_at' => Carbon::parse('2026-03-08 11:40:00'),
        'finished_at' => Carbon::parse('2026-03-08 12:10:00'),
    ]);
    RssParseLog::factory()->create([
        'started_at' => Carbon::parse('2026-03-08 10:00:00'),
        'finished_at' => Carbon::parse('2026-03-08 10:05:00'),
    ]);

    $component = Livewire::test(ParseHistory::class)
        ->assertSee('Running Now');

    expect($component->instance()->summary['runs_in_progress'])->toBe(2);

    Carbon::setTestNow();
});

it('includes logs that overlap the selected date range', function () {
    $this->actingAs(User::factory()->create());

    $spanningFeed = RssFeed::factory()->create(['title' => 'Spanning feed']);
    $outsideFeed = RssFeed::factory()->create(['title' => 'Outside feed']);

    RssParseLog::factory()->create([
        'rss_feed_id' => $spanningFeed->id,
        'started_at' => Carbon::parse('2026-03-07 23:55:00'),
        'finished_at' => Carbon::parse('2026-03-08 00:10:00'),
    ]);
    RssParseLog::factory()->create([
        'rss_feed_id' => $outsideFeed->id,
        'started_at' => Carbon::parse('2026-03-07 22:00:00'),
        'finished_at' => Carbon::parse('2026-03-07 22:20:00'),
    ]);

    $component = Livewire::test(ParseHistory::class)
        ->set('dateFrom', '2026-03-08')
        ->set('dateTo', '2026-03-08')
        ->assertSee('Spanning feed');

    expect($component->instance()->logs->pluck('rssFeed.title')->all())
        ->toBe(['Spanning feed']);
});
