<?php

it('keeps the blade and Mary UI frontend stack explicitly configured', function () {
    $packageJson = json_decode(file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
    $composerJson = json_decode(file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
    $viteConfig = file_get_contents(base_path('vite.config.ts'));
    $appCss = file_get_contents(resource_path('css/app.css'));

    expect($packageJson['devDependencies'])
        ->toHaveKey('daisyui')
        ->toHaveKey('vite')
        ->toHaveKey('eslint')
        ->not->toHaveKey('@inertiajs/core')
        ->not->toHaveKey('@sveltejs/vite-plugin-svelte')
        ->not->toHaveKey('eslint-plugin-svelte')
        ->not->toHaveKey('prettier-plugin-svelte')
        ->not->toHaveKey('@testing-library/svelte')
        ->and($composerJson['require'])
        ->toHaveKey('robsontenorio/mary')
        ->toHaveKey('livewire/livewire')
        ->not->toHaveKey('inertiajs/inertia-laravel')
        ->and($viteConfig)->not->toBeFalse()
        ->and($viteConfig)->toContain(
            "import tailwindcss from '@tailwindcss/vite';",
            "import laravel from 'laravel-vite-plugin';",
            "input: ['resources/css/app.css', 'resources/js/app.js']",
        )
        ->and($viteConfig)->not->toContain('@sveltejs/vite-plugin-svelte')
        ->not->toContain('svelte()')
        ->and($appCss)->not->toBeFalse()
        ->and($appCss)->toContain('@plugin "daisyui"', 'vendor/robsontenorio/mary');
});
