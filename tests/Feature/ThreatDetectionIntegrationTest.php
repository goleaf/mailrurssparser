<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('threat-detection.enabled_environments', ['testing']);
});

function threatDetectionRouteByUri(string $uri): ?\Illuminate\Routing\Route
{
    return collect(Route::getRoutes()->getRoutes())
        ->first(fn (\Illuminate\Routing\Route $route): bool => $route->uri() === ltrim($uri, '/'));
}

test('threat detection routes are registered behind the admin area', function () {
    $dashboardRoute = Route::getRoutes()->getByName('threat-detection.dashboard');
    $statsRoute = threatDetectionRouteByUri(config('threat-detection.api.prefix').'/stats');

    expect($dashboardRoute)->not->toBeNull()
        ->and($dashboardRoute?->uri())->toBe('admin/threat-detection')
        ->and($statsRoute)->not->toBeNull()
        ->and($statsRoute?->uri())->toBe('admin/threat-detection/api/stats');
});

test('guests cannot access the threat detection dashboard or api', function () {
    $this->get('/admin/threat-detection')
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->get('/admin/threat-detection/api/stats')
        ->assertRedirect(route('filament.admin.auth.login'));
});

test('authenticated users can access the threat detection dashboard and stats api', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/threat-detection')
        ->assertOk()
        ->assertSee('Threat Timeline (7 Days)');

    $this->getJson('/admin/threat-detection/api/stats')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_threats', 0);
});

test('threat detection logs suspicious web and api requests', function () {
    Cache::flush();

    $table = config('threat-detection.table_name', 'threat_logs');

    expect(DB::table($table)->count())->toBe(0);

    $webProbe = '%3Cscript%3Ealert(1)%3C/script%3E';

    $this->get('/?probe='.$webProbe)
        ->assertOk();

    $afterWebProbe = DB::table($table)->count();

    expect($afterWebProbe)->toBeGreaterThan(0)
        ->and(DB::table($table)->where('type', 'like', '%XSS%')->exists())->toBeTrue();

    $this->get('/api/v1/stats/overview?probe='.urlencode("' UNION SELECT * FROM users--"))
        ->assertOk();

    expect(DB::table($table)->count())->toBeGreaterThan($afterWebProbe)
        ->and(DB::table($table)->where('type', 'like', '%SQL%')->exists())->toBeTrue();
});
