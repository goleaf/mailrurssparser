<?php

use Illuminate\Support\Facades\Route;

test('public frontend two factor challenge route is not registered', function () {
    expect(Route::has('two-factor.login'))->toBeFalse();
});

test('legacy two factor challenge page redirects to the filament admin login', function () {
    $this->get('/two-factor-challenge')
        ->assertRedirect(route('filament.admin.auth.login'));
});
