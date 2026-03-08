<?php

use App\Models\Article;
use App\Models\RssFeed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function sqlitePlanDetails(string $query, array $bindings = []): array
{
    return collect(DB::select('EXPLAIN QUERY PLAN '.$query, $bindings))
        ->map(fn (object $row): string => (string) ($row->detail ?? ''))
        ->all();
}

it('adds the composite indexes used by the hottest article view, bookmark, and rss parse queries', function () {
    expect(Schema::hasIndex('article_views', ['article_id', 'ip_hash', 'viewed_at']))->toBeTrue()
        ->and(Schema::hasIndex('article_views', ['article_id', 'session_hash', 'viewed_at']))->toBeTrue()
        ->and(Schema::hasIndex('bookmarks', ['session_hash', 'created_at']))->toBeTrue()
        ->and(Schema::hasIndex('rss_parse_logs', ['started_at']))->toBeTrue()
        ->and(Schema::hasIndex('rss_parse_logs', ['rss_feed_id', 'started_at']))->toBeTrue()
        ->and(Schema::hasIndex('rss_parse_logs', ['success', 'started_at']))->toBeTrue();
});

it('uses the duplicate-check article view index in sqlite query plans', function () {
    $article = Article::factory()->create();

    $details = sqlitePlanDetails(
        'SELECT 1 FROM article_views WHERE article_id = ? AND ip_hash = ? AND viewed_at >= ? LIMIT 1',
        [$article->id, hash('sha256', '127.0.0.1'), now()->subHour()->toDateTimeString()],
    );

    expect(collect($details)->contains(
        fn (string $detail): bool => str_contains($detail, 'article_views_article_id_ip_hash_viewed_at_index'),
    ))->toBeTrue();
});

it('uses the bookmark listing index in sqlite query plans', function () {
    $details = sqlitePlanDetails(
        'SELECT id FROM bookmarks WHERE session_hash = ? ORDER BY created_at DESC LIMIT 10',
        [hash('sha256', '127.0.0.1Pest Browser')],
    );

    expect(collect($details)->contains(
        fn (string $detail): bool => str_contains($detail, 'bookmarks_session_hash_created_at_index'),
    ))->toBeTrue();
});

it('uses the rss parse history index in sqlite query plans', function () {
    $feed = RssFeed::factory()->create();

    $details = sqlitePlanDetails(
        'SELECT rss_feed_id, started_at FROM rss_parse_logs WHERE rss_feed_id = ? ORDER BY started_at DESC LIMIT 5',
        [$feed->id],
    );

    expect(collect($details)->contains(
        fn (string $detail): bool => str_contains($detail, 'rss_parse_logs_rss_feed_id_started_at_index'),
    ))->toBeTrue();
});
