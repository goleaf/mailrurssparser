<?php

use Illuminate\Support\Facades\Route;

test('public frontend authentication routes are not registered', function () {
    expect(Route::has('login'))->toBeFalse()
        ->and(Route::has('login.store'))->toBeFalse()
        ->and(Route::has('logout'))->toBeFalse();
});

test('legacy login url redirects to the filament admin login', function () {
    $this->get('/login')
        ->assertRedirect(route('filament.admin.auth.login'));
});
