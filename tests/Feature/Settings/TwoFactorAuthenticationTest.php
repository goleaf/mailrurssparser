<?php

use Illuminate\Support\Facades\Route;

test('public frontend two factor settings route is not registered', function () {
    expect(Route::has('two-factor.show'))->toBeFalse();
});

test('legacy two factor settings page redirects to the filament admin panel', function () {
    $this->get('/settings/two-factor')
        ->assertRedirect('/admin');
});
