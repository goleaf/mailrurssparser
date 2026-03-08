<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\RssFeed;
use App\Services\MetricTracker;
use App\Services\TrackedMetric;
use Illuminate\Database\Seeder;

class MetricSeeder extends Seeder
{
    public function run(): void
    {
        $tracker = app(MetricTracker::class);

        Article::query()
            ->latest('published_at')
            ->limit(5)
            ->get()
            ->each(function (Article $article, int $index) use ($tracker): void {
                $tracker->record(TrackedMetric::ArticleView, max(1, 20 - ($index * 3)), $article, now()->subHours($index + 1));
                $tracker->record(TrackedMetric::BookmarkAdded, max(1, 6 - $index), $article, now()->subHours($index + 1));
            });

        RssFeed::query()
            ->latest('last_parsed_at')
            ->limit(3)
            ->get()
            ->each(function (RssFeed $feed, int $index) use ($tracker): void {
                $tracker->record(TrackedMetric::RssParseRun, 1, $feed, now()->subHours($index + 1));
                $tracker->record(TrackedMetric::RssArticleImported, max(1, 8 - ($index * 2)), $feed, now()->subHours($index + 1));
            });

        $tracker->record(TrackedMetric::NewsletterSubscription, 12, recordedAt: now()->subDays(1));
        $tracker->record(TrackedMetric::NewsletterConfirmation, 9, recordedAt: now()->subDays(1));
        $tracker->record(TrackedMetric::NewsletterUnsubscription, 2, recordedAt: now()->subDays(1));
    }
}
