<?php

use Illuminate\Support\Facades\Route;

test('public frontend profile routes are not registered', function () {
    expect(Route::has('profile.edit'))->toBeFalse()
        ->and(Route::has('profile.update'))->toBeFalse()
        ->and(Route::has('profile.destroy'))->toBeFalse();
});

test('legacy profile settings page redirects to the filament admin panel', function () {
    $this->get('/settings/profile')
        ->assertRedirect('/admin');
});
