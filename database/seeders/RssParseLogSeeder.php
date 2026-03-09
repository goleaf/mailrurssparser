<?php

namespace Database\Seeders;

use App\Models\RssFeed;
use App\Models\RssParseLog;
use Illuminate\Database\Seeder;

class RssParseLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $feeds = RssFeed::query()->limit(20)->get();

        if ($feeds->isEmpty()) {
            return;
        }

        $perFeed = max(1, (int) ceil(20 / $feeds->count()));

        $feeds->each(function (RssFeed $feed) use ($perFeed): void {
            RssParseLog::factory()
                ->count($perFeed)
                ->forFeed($feed)
                ->create();

            RssParseLog::factory()
                ->count(1)
                ->forFeed($feed)
                ->failed()
                ->create();

            $latestLog = $feed->parseLogs()->latest('started_at')->first();

            if ($latestLog === null) {
                return;
            }

            $feed->forceFill([
                'last_parsed_at' => $latestLog->started_at,
                'next_parse_at' => $latestLog->started_at?->copy()->addMinutes($feed->fetch_interval ?: 15),
                'articles_parsed_total' => $feed->articles()->count(),
                'last_run_new_count' => $latestLog->new_count,
                'last_run_skip_count' => $latestLog->skip_count,
                'last_run_error_count' => $latestLog->error_count,
                'consecutive_failures' => $latestLog->success ? 0 : 1,
                'last_error' => $latestLog->error_message,
            ])->save();
        });
    }
}
