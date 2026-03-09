<?php

use Illuminate\Support\Facades\Route;

test('public frontend verification resend route is not registered', function () {
    expect(Route::has('verification.send'))->toBeFalse();
});
