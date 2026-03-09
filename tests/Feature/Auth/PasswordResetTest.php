<?php

use Illuminate\Support\Facades\Route;

test('public frontend password reset routes are not registered', function () {
    expect(Route::has('password.request'))->toBeFalse()
        ->and(Route::has('password.email'))->toBeFalse()
        ->and(Route::has('password.update'))->toBeFalse()
        ->and(Route::has('password.reset'))->toBeFalse();
});

test('legacy password reset pages redirect to the filament admin login', function () {
    $this->get('/forgot-password')
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->get('/reset-password/test-token')
        ->assertRedirect(route('filament.admin.auth.login'));
});
