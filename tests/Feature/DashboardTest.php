<?php

use Illuminate\Support\Facades\Route;

test('dashboard route name is removed from the public frontend', function () {
    expect(Route::has('dashboard'))->toBeFalse();
});

test('legacy dashboard url redirects to the filament admin panel', function () {
    $this->get('/dashboard')
        ->assertRedirect('/admin');
});
