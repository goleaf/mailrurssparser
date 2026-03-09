<?php

it('installs debugbar 4 as a development dependency', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require-dev'])
        ->toHaveKey('fruitcake/laravel-debugbar', '^4.0')
        ->and(app()->bound(\Fruitcake\LaravelDebugbar\LaravelDebugbar::class))
        ->toBeTrue();
});

it('configures debugbar for the blade and livewire frontend stack', function () {
    expect(config('debugbar.collectors.livewire'))
        ->toBeTrue()
        ->and(config('debugbar.collectors.inertia'))
        ->toBeFalse()
        ->and(config('debugbar.except'))
        ->toContain('_boost/browser-logs')
        ->toContain('livewire-*/livewire.js')
        ->and(config('debugbar.options.views.exclude_paths'))
        ->toContain('vendor/filament');
});

it('documents the local debugbar overrides in the environment example', function () {
    expect(file_get_contents(base_path('.env.example')))
        ->toContain('# DEBUGBAR_ENABLED=true')
        ->toContain('# DEBUGBAR_EDITOR=phpstorm');
});
