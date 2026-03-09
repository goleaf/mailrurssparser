<?php

use Illuminate\Support\Facades\Route;

test('public frontend password confirmation route is not registered', function () {
    expect(Route::has('password.confirm'))->toBeFalse();
});

test('legacy password confirmation page redirects to the filament admin login', function () {
    $this->get('/user/confirm-password')
        ->assertRedirect(route('filament.admin.auth.login'));
});
