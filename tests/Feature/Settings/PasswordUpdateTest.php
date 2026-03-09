<?php

use Illuminate\Support\Facades\Route;

test('public frontend password settings routes are not registered', function () {
    expect(Route::has('user-password.edit'))->toBeFalse()
        ->and(Route::has('user-password.update'))->toBeFalse();
});

test('legacy password settings page redirects to the filament admin panel', function () {
    $this->get('/settings/password')
        ->assertRedirect('/admin');
});
