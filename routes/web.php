<?php

use App\Http\Controllers\RssParseController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::prefix('admin/rss')
    ->name('rss.')
    ->middleware('auth')
    ->group(function (): void {
        Route::get('/', [RssParseController::class, 'index'])->name('index');
        Route::post('/parse-all', [RssParseController::class, 'parseAll'])->name('parse-all');
        Route::post('/parse/{feedId}', [RssParseController::class, 'parseFeed'])->name('parse-feed');
        Route::post('/parse-category/{slug}', [RssParseController::class, 'parseCategory'])->name('parse-category');
    });

require __DIR__.'/settings.php';
