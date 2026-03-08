<?php

namespace App\Providers;

use App\Events\ArticleContentChanged;
use App\Listeners\RebuildRelatedArticlesIndex;
use App\Models\Article;
use App\Observers\ArticleObserver;
use App\Services\ApiResponseMeta;
use App\Services\PincrypFactory;
use Attla\Pincryp\Config as PincrypConfig;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\Events\CacheFailedOver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\QueueFailedOver;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

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
        $this->configureApiRateLimiters();
        $this->configureCarbonDurationHelpers();
        $this->configureFailoverTelemetry();
        $this->configureHttpClientResponseMacros();

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

    protected function configureHttpClientResponseMacros(): void
    {
        if (! HttpClientResponse::hasMacro('redirectLocation')) {
            HttpClientResponse::macro('redirectLocation', function (): ?string {
                $location = trim((string) $this->header('Location'));

                return $location !== '' ? $location : null;
            });
        }

        if (! HttpClientResponse::hasMacro('isRedirectStatus')) {
            HttpClientResponse::macro('isRedirectStatus', function (): bool {
                return in_array($this->status(), [301, 302], true);
            });
        }
    }

    protected function configureCarbonDurationHelpers(): void
    {
        foreach ([Carbon::class, CarbonImmutable::class] as $carbonClass) {
            if (! $carbonClass::hasMacro('plus')) {
                $carbonClass::macro('plus', function (
                    int $years = 0,
                    int $months = 0,
                    int $weeks = 0,
                    int $days = 0,
                    int $hours = 0,
                    int $minutes = 0,
                    int $seconds = 0,
                    int $microseconds = 0,
                ) {
                    return $this->add("
                        $years years $months months $weeks weeks $days days
                        $hours hours $minutes minutes $seconds seconds $microseconds microseconds
                    ");
                });
            }

            if (! $carbonClass::hasMacro('minus')) {
                $carbonClass::macro('minus', function (
                    int $years = 0,
                    int $months = 0,
                    int $weeks = 0,
                    int $days = 0,
                    int $hours = 0,
                    int $minutes = 0,
                    int $seconds = 0,
                    int $microseconds = 0,
                ) {
                    return $this->sub("
                        $years years $months months $weeks weeks $days days
                        $hours hours $minutes minutes $seconds seconds $microseconds microseconds
                    ");
                });
            }
        }
    }

    protected function configureFailoverTelemetry(): void
    {
        Event::listen(CacheFailedOver::class, function (CacheFailedOver $event): void {
            Log::warning('Cache store failed over.', [
                'store' => $event->storeName,
                'exception' => $event->exception::class,
                'message' => $event->exception->getMessage(),
            ]);
        });

        Event::listen(QueueFailedOver::class, function (QueueFailedOver $event): void {
            Log::warning('Queue connection failed over.', [
                'connection' => $event->connectionName,
                'command' => is_object($event->command) ? $event->command::class : get_debug_type($event->command),
                'exception' => $event->exception::class,
                'message' => $event->exception->getMessage(),
            ]);
        });
    }

    protected function configureApiRateLimiters(): void
    {
        RateLimiter::for('api', function (Request $request): array {
            return [
                $this->apiLimit('api', 120, $request, 'Too many API requests. Please retry in a minute.'),
                $this->apiLimit('api-missing', 20, $request, 'Too many missing-resource requests. Please retry shortly.')
                    ->after(fn (Response $response): bool => $response->getStatusCode() === 404),
            ];
        });

        RateLimiter::for('api-search', function (Request $request): array {
            return [
                $this->apiLimit('api-search', 30, $request, 'Search rate limit exceeded. Please retry shortly.'),
                $this->apiLimit('api-search-invalid', 10, $request, 'Too many invalid search requests. Please refine the query and retry later.')
                    ->after(fn (Response $response): bool => $response->getStatusCode() === 422),
            ];
        });

        RateLimiter::for(
            'api-search-suggest',
            fn (Request $request): Limit => $this->apiLimit(
                'api-search-suggest',
                60,
                $request,
                'Search suggestion rate limit exceeded. Please retry shortly.',
            ),
        );

        RateLimiter::for('api-rss', function (Request $request): array {
            return [
                $this->apiLimit('api-rss', 10, $request, 'RSS API rate limit exceeded. Please retry shortly.'),
                $this->apiLimit('api-rss-failures', 5, $request, 'Too many failing RSS API requests. Please retry later.')
                    ->after(fn (Response $response): bool => $response->isClientError() || $response->isServerError()),
            ];
        });
    }

    protected function apiLimit(string $prefix, int $perMinute, Request $request, string $message): Limit
    {
        return Limit::perMinute($perMinute)
            ->by($prefix.':'.$this->apiRateLimiterKey($request))
            ->response(fn (Request $request, array $headers): JsonResponse => $this->rateLimitedResponse($request, $headers, $message));
    }

    protected function apiRateLimiterKey(Request $request): string
    {
        if ($request->user() !== null) {
            return 'user:'.$request->user()->getAuthIdentifier();
        }

        return 'ip:'.($request->ip() ?? 'unknown');
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function rateLimitedResponse(Request $request, array $headers, string $message): JsonResponse
    {
        $requestId = ApiResponseMeta::requestIdFor($request) ?? (string) Str::uuid();
        $version = ApiResponseMeta::versionFor($request);

        $request->attributes->set(ApiResponseMeta::REQUEST_ID_ATTRIBUTE, $requestId);
        $request->attributes->set(ApiResponseMeta::VERSION_ATTRIBUTE, $version);

        return response()->json([
            'error' => 'rate_limited',
            'message' => $message,
            'retry_after' => isset($headers['Retry-After']) ? (int) $headers['Retry-After'] : null,
            'meta' => ApiResponseMeta::fromRequest($request),
        ], 429, array_merge($headers, [
            'X-Request-Id' => $requestId,
            'X-Api-Version' => $version,
        ]));
    }

    protected function preferFileBackedStoresForLocalSqlite(): void
    {
        if (! $this->app->isLocal() || config('database.default') !== 'sqlite') {
            return;
        }

        if (config('cache.default') === 'database') {
            config(['cache.default' => 'file']);
        }

        if (config('cache.default') === 'failover') {
            $stores = config('cache.stores.failover.stores');

            if (is_array($stores)) {
                config([
                    'cache.stores.failover.stores' => array_values(array_unique(array_map(
                        fn (mixed $store): mixed => $store === 'database' ? 'file' : $store,
                        $stores,
                    ))),
                ]);
            }
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
