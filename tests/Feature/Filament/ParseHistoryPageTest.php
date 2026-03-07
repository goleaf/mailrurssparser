<?php

use App\Filament\Pages\ParseHistory;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\User;
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
        ->assertDontSee('Main feed')
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
