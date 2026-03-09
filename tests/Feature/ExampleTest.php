<?php

use Inertia\Testing\AssertableInertia as Assert;

test('home page can be rendered', function () {
    $response = $this->get(route('home'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->where('name', config('app.name'))
        ->where('publicRoute.name', 'home')
        ->missing('auth')
        ->missing('canRegister')
        ->missing('sidebarOpen'),
    );
});

test('public category page is rendered through inertia', function () {
    $response = $this->get('/category/world');

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->where('publicRoute.name', 'category')
        ->where('publicRoute.slug', 'world'),
    );
});

test('unknown public path returns the welcome shell as not found', function () {
    $response = $this->get('/missing-public-page');

    $response->assertNotFound();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->where('publicRoute.name', 'not-found'),
    );
});
