<?php

it('keeps the svelte inertia vite stack explicitly configured', function () {
    $packageJson = file_get_contents(base_path('package.json'));
    $viteConfig = file_get_contents(base_path('vite.config.ts'));

    expect($packageJson)->not->toBeFalse()
        ->and($packageJson)->toContain(
            '"@inertiajs/core": "^3.0.0-beta.2"',
            '"@sveltejs/vite-plugin-svelte":',
            '"laravel-vite-plugin":',
            '"vite":',
        )
        ->and($viteConfig)->not->toBeFalse()
        ->and($viteConfig)->toContain(
            "import { svelte } from '@sveltejs/vite-plugin-svelte';",
            "import laravel from 'laravel-vite-plugin';",
            'laravel({',
            'svelte(),',
        );
});
