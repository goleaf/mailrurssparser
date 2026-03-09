<?php

use App\Http\Controllers\RssParseController;
use App\Http\Controllers\SeoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

$renderPublicPage = static fn (array $publicRoute): Response => Inertia::render('Welcome', [
    'publicRoute' => $publicRoute,
]);

Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('rss.xml', [SeoController::class, 'rss'])->name('rss-feed');
Route::get('/robots.txt', fn () => response(
    "User-agent: *\nAllow: /\nSitemap: ".url('sitemap.xml'),
    200,
    ['Content-Type' => 'text/plain'],
))->name('robots');
Route::view('offline.html', 'offline')->name('offline');
Route::get('scheduler/pulse', fn () => response()->noContent()->header('Cache-Control', 'no-store, no-cache, must-revalidate'))
    ->name('scheduler.pulse');

Route::get('/', static fn () => $renderPublicPage([
    'name' => 'home',
]))->name('home');
Route::get('category/{slug}', static fn (string $slug) => $renderPublicPage([
    'name' => 'category',
    'slug' => $slug,
]))->name('category.show');
Route::get('tag/{slug}', static fn (string $slug) => $renderPublicPage([
    'name' => 'tag',
    'slug' => $slug,
]))->name('tag.show');
Route::get('articles/{slug}', static fn (string $slug) => $renderPublicPage([
    'name' => 'article',
    'slug' => $slug,
]))->name('articles.show');
Route::get('search', static fn () => $renderPublicPage([
    'name' => 'search',
]))->name('search');
Route::get('bookmarks', static fn () => $renderPublicPage([
    'name' => 'bookmarks',
]))->name('bookmarks');
Route::get('stats', static fn () => $renderPublicPage([
    'name' => 'stats',
]))->name('stats');
Route::get('about', static fn () => $renderPublicPage([
    'name' => 'info',
    'variant' => 'about',
]))->name('about');
Route::get('contact', static fn () => $renderPublicPage([
    'name' => 'info',
    'variant' => 'contact',
]))->name('contact');
Route::get('privacy', static fn () => $renderPublicPage([
    'name' => 'info',
    'variant' => 'privacy',
]))->name('privacy');

Route::get('dashboard', static fn () => redirect('/admin'));
Route::get('login', static fn () => redirect()->route('filament.admin.auth.login'));
Route::get('register', static fn () => redirect()->route('filament.admin.auth.login'));
Route::get('forgot-password', static fn () => redirect()->route('filament.admin.auth.login'));
Route::get('reset-password/{token}', static fn () => redirect()->route('filament.admin.auth.login'))
    ->where('token', '.*');
Route::get('email/verify', static fn () => redirect()->route('filament.admin.auth.login'));
Route::get('email/verify/{id}/{hash}', static fn () => redirect()->route('filament.admin.auth.login'))
    ->whereNumber('id');
Route::get('user/confirm-password', static fn () => redirect()->route('filament.admin.auth.login'));
Route::get('two-factor-challenge', static fn () => redirect()->route('filament.admin.auth.login'));
Route::get('settings/{path?}', static fn () => redirect('/admin'))
    ->where('path', '.*');

Route::prefix('admin/rss')
    ->name('rss.')
    ->middleware('auth')
    ->group(function (): void {
        Route::get('/', [RssParseController::class, 'index'])->name('index');
        Route::post('/parse-all', [RssParseController::class, 'parseAll'])->name('parse-all');
        Route::post('/parse/{feedId}', [RssParseController::class, 'parseFeed'])->name('parse-feed');
        Route::post('/parse-category/{slug}', [RssParseController::class, 'parseCategory'])->name('parse-category');
    });

Route::fallback(static function (Request $request) use ($renderPublicPage) {
    return $renderPublicPage([
        'name' => 'not-found',
    ])->toResponse($request)->setStatusCode(404);
})->name('spa');
