<?php

it('renders the app shell with pwa meta tags', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('rel="manifest" href="/manifest.json"', false)
        ->assertSee('name="theme-color" content="#1D4ED8"', false)
        ->assertSee(
            'name="apple-mobile-web-app-capable" content="yes"',
            false,
        );
});

it('renders the offline fallback page', function () {
    $this->get('/offline.html')
        ->assertOk()
        ->assertSee('Нет подключения к интернету');
});

it('ships the manifest, service worker, and icons', function () {
    expect(public_path('manifest.json'))->toBeFile()
        ->and(public_path('sw.js'))->toBeFile()
        ->and(public_path('icons/icon-192.png'))->toBeFile()
        ->and(public_path('icons/icon-512.png'))->toBeFile();

    $manifest = json_decode(
        file_get_contents(public_path('manifest.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest)->toMatchArray([
        'name' => 'Новостной портал',
        'short_name' => 'Новости',
        'theme_color' => '#1D4ED8',
        'lang' => 'ru',
    ]);
});
