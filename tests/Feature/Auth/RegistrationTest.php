<?php

use Illuminate\Support\Facades\Route;

test('public frontend registration routes are not registered', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();
});

test('legacy register url redirects to the filament admin login', function () {
    $this->get('/register')
        ->assertRedirect(route('filament.admin.auth.login'));
});
