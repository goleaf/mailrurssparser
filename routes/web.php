<?php

use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\RssParseController;
use App\Http\Controllers\SeoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::get('/', [PublicSiteController::class, 'home'])->name('home');
Route::get('category/{slug}', [PublicSiteController::class, 'category'])->name('category.show');
Route::get('tag/{slug}', [PublicSiteController::class, 'tag'])->name('tag.show');
Route::get('articles/{slug}', [PublicSiteController::class, 'article'])->name('articles.show');
Route::get('search', [PublicSiteController::class, 'search'])->name('search');
Route::get('bookmarks', [PublicSiteController::class, 'bookmarks'])->name('bookmarks');
Route::post('bookmarks/{article}', [PublicSiteController::class, 'toggleBookmark'])->name('bookmarks.toggle');
Route::get('stats', [PublicSiteController::class, 'stats'])->name('stats');
Route::get('about', static fn (Request $request, PublicSiteController $controller) => $controller->info($request, 'about'))->name('about');
Route::get('contact', static fn (Request $request, PublicSiteController $controller) => $controller->info($request, 'contact'))->name('contact');
Route::get('privacy', static fn (Request $request, PublicSiteController $controller) => $controller->info($request, 'privacy'))->name('privacy');

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

Route::fallback([PublicSiteController::class, 'notFound'])->name('spa');
