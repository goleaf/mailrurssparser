<?php

use App\Http\Middleware\ApplyApiRequestContext;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\TriggerSchedulerFromWebRequests;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JayAnta\ThreatDetection\Http\Middleware\ThreatDetectionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->redirectGuestsTo(fn (Request $request): string => route('filament.admin.auth.login'));
        $middleware->redirectUsersTo('/admin');
        $middleware->alias([
            'api.context' => ApplyApiRequestContext::class,
        ]);

        $middleware->api(append: [
            ThreatDetectionMiddleware::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            AddLinkHeadersForPreloadedAssets::class,
            ThreatDetectionMiddleware::class,
            TriggerSchedulerFromWebRequests::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Parse all feeds every minute, no overlap.
        $schedule->command('rss:parse --all')
            ->everyMinute()
            ->withoutOverlapping(1)
            ->runInBackground();

        // Backfill missing article fields from saved source links every hour.
        $schedule->command('rss:enrich-articles --limit=100')
            ->hourlyAt(10)
            ->withoutOverlapping(50)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // Clean old articles every Sunday at 3am.
        $schedule->command('rss:clean --days=90 --force')
            ->weeklyOn(0, '03:00')
            ->runInBackground();

        // Rebuild search index every night at 2am.
        $schedule->command('rss:reindex')
            ->dailyAt('02:00')
            ->runInBackground();

        // Recalculate engagement scores hourly.
        $schedule->call(function (): void {
            Article::query()
                ->published()
                ->where('published_at', '>=', now()->subDays(30))
                ->each(function (Article $article): void {
                    $article->recalculateEngagementScore();
                });
        })->hourly()->name('recalculate-engagement')->withoutOverlapping();

        // Update tag usage counts daily.
        $schedule->call(function (): void {
            DB::statement('
                UPDATE tags SET usage_count = (
                    SELECT COUNT(*) FROM article_tag WHERE tag_id = tags.id
                )
            ');
        })->daily()->name('update-tag-counts');

        // Update cached category article counts every 30 minutes.
        $schedule->call(function (): void {
            Category::query()->get()->each(function (Category $category): void {
                $category->update([
                    'articles_count_cache' => $category->articles()->published()->count(),
                ]);
            });
        })->everyThirtyMinutes()->name('update-category-counts');

        // Expire breaking news after 24 hours.
        $schedule->call(function (): void {
            Article::query()
                ->where('is_breaking', true)
                ->where('published_at', '<', now()->subHours(24))
                ->update(['is_breaking' => false]);
        })->hourly()->name('expire-breaking-news');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->throttle(function (\Throwable $exception) {
            if (
                $exception instanceof \RuntimeException
                && Str::startsWith($exception->getMessage(), ['Feed unreachable', 'Feed gone', 'Invalid RSS'])
            ) {
                return Limit::perMinute(1)->by('rss-parser:'.$exception->getMessage());
            }

            return Limit::none();
        });
    })->create();
