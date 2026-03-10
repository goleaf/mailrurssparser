<?php

use App\Providers\Filament\AdminPanelProvider;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithCachedConfig;
use Illuminate\Foundation\Testing\WithCachedRoutes;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class, WithCachedConfig::class, WithCachedRoutes::class)
    ->in('Feature');

beforeEach(function () {
    $path = storage_path('tntsearch');

    if (! is_dir($path)) {
        mkdir($path, 0777, true);
    }
});

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to
| your project that you don't want to repeat in every file. Here you can also expose
| helpers as global functions to help you to reduce the number of lines of code in your
| test files.
|
*/

/**
 * Execute a callback while transient factories avoid expanding belongs-to parents.
 */
function withoutExpandedFactoryRelationships(callable $callback): mixed
{
    EloquentFactory::dontExpandRelationshipsByDefault();

    try {
        return $callback();
    } finally {
        EloquentFactory::expandRelationshipsByDefault();
    }
}

function filamentAdminUser(array $attributes = []): \App\Models\User
{
    if (! Filament::getCurrentPanel()) {
        Filament::setCurrentPanel((new AdminPanelProvider(app()))->panel(new Panel));
    }

    $guardName = Filament::getCurrentPanel()?->getAuthGuard()
        ?? Filament::getPanel('admin')?->getAuthGuard()
        ?? config('auth.defaults.guard', 'web');

    $permissions = collect([
        'article',
        'article_view',
        'bookmark',
        'category',
        'metric',
        'newsletter_subscriber',
        'rss_feed',
        'rss_parse_log',
        'sub_category',
        'tag',
    ])->flatMap(function (string $resource) use ($guardName): array {
        return collect([
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'force_delete',
            'force_delete_any',
            'restore',
            'restore_any',
        ])->map(function (string $prefix) use ($resource, $guardName): string {
            return Permission::findOrCreate("{$prefix}_{$resource}", $guardName)->name;
        })->all();
    })->all();

    Role::findOrCreate('admin', $guardName);
    Role::findOrCreate('super_admin', $guardName)->syncPermissions($permissions);

    $user = \App\Models\User::factory()->create($attributes);
    $user->assignRole('super_admin');

    return $user;
}
