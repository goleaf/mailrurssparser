<?php

use Inertia\Testing\AssertableInertia as Assert;

test('home page can be rendered', function () {
    $response = $this->get(route('home'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->has('canRegister'),
    );
});

test('spa catch all page can be rendered', function () {
    $response = $this->get('/category/world');

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Welcome'),
    );
});
