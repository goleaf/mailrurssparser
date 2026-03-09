<?php

use Illuminate\Support\Facades\Route;

test('public frontend verification routes are not registered', function () {
    expect(Route::has('verification.notice'))->toBeFalse()
        ->and(Route::has('verification.verify'))->toBeFalse();
});

test('legacy verification pages redirect to the filament admin login', function () {
    $this->get('/email/verify')
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->get('/email/verify/1/test-hash')
        ->assertRedirect(route('filament.admin.auth.login'));
});
