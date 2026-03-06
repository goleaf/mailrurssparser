<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Parse all feeds every 15 minutes
        $schedule->command('rss:parse')
            ->everyFifteenMinutes()
            ->withoutOverlapping(10)
            ->runInBackground();

        // Parse breaking/main news more frequently (every 5 minutes, only main category)
        $schedule->command('rss:parse --category=main')
            ->everyFiveMinutes()
            ->withoutOverlapping(5)
            ->runInBackground();

        // Clean old soft-deleted articles every Sunday at 3am
        $schedule->command('rss:clean --days=90 --force')
            ->weeklyOn(0, '03:00');

        // Also add a log message for monitoring
        $schedule->command('rss:parse')
            ->everyFifteenMinutes()
            ->onSuccess(function (): void {
                \Illuminate\Support\Facades\Log::info('RSS parse completed successfully');
            })
            ->onFailure(function (): void {
                \Illuminate\Support\Facades\Log::error('RSS parse failed');
            });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
