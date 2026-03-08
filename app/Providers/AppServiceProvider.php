<?php

namespace App\Providers;

use App\Events\ArticleContentChanged;
use App\Listeners\RebuildRelatedArticlesIndex;
use App\Models\Article;
use App\Observers\ArticleObserver;
use App\Services\PincrypFactory;
use Attla\Pincryp\Config as PincrypConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->preferFileBackedStoresForLocalSqlite();
        $this->ensureTntSearchStoragePathExists();

        $this->app->singleton('pincryp', function ($app): PincrypFactory {
            $config = $app['config']->get('pincryp', []);

            return new PincrypFactory(new PincrypConfig(is_array($config) ? $config : []));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Article::observe(ArticleObserver::class);
        Event::listen(ArticleContentChanged::class, RebuildRelatedArticlesIndex::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function preferFileBackedStoresForLocalSqlite(): void
    {
        if (! $this->app->isLocal() || config('database.default') !== 'sqlite') {
            return;
        }

        if (config('cache.default') === 'database') {
            config(['cache.default' => 'file']);
        }

        if (config('session.driver') === 'database') {
            config(['session.driver' => 'file']);
        }
    }

    protected function ensureTntSearchStoragePathExists(): void
    {
        if (config('scout.driver') !== 'tntsearch') {
            return;
        }

        $storagePath = config('scout.tntsearch.storage');

        if (! is_string($storagePath) || $storagePath === '') {
            return;
        }

        File::ensureDirectoryExists($storagePath);
    }
}
