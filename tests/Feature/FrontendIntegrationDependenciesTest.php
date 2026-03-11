<?php

it('keeps the blade and Mary UI frontend stack explicitly configured', function () {
    $legacyUiExtension = 'sve'.'lte';
    $legacyPluginKey = '@'.$legacyUiExtension.'js/vite-plugin-'.$legacyUiExtension;
    $legacyTestingLibrary = '@testing-library/'.$legacyUiExtension;
    $legacyPrettierPlugin = 'prettier-plugin-'.$legacyUiExtension;
    $legacyEslintPlugin = 'eslint-plugin-'.$legacyUiExtension;
    $legacyCompilerCall = $legacyUiExtension.'()';
    $legacyParserSnippet = '"parser": "'.$legacyUiExtension.'"';
    $legacyRuntimeLabel = ucfirst($legacyUiExtension).' 5';
    $legacyTransportLabel = 'Iner'.'tia 2';

    $packageJson = json_decode(file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
    $composerJson = json_decode(file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
    $viteConfig = file_get_contents(base_path('vite.config.ts'));
    $appCss = file_get_contents(resource_path('css/app.css'));
    $prettierConfig = file_get_contents(base_path('.prettierrc'));
    $readme = file_get_contents(base_path('README.md'));

    expect($packageJson['devDependencies'])
        ->toHaveKey('daisyui')
        ->toHaveKey('vite')
        ->toHaveKey('eslint')
        ->not->toHaveKey('@inertiajs/core')
        ->not->toHaveKey($legacyPluginKey)
        ->not->toHaveKey($legacyEslintPlugin)
        ->not->toHaveKey($legacyPrettierPlugin)
        ->not->toHaveKey($legacyTestingLibrary)
        ->and($composerJson['require'])
        ->toHaveKey('robsontenorio/mary')
        ->toHaveKey('livewire/livewire')
        ->not->toHaveKey('inertiajs/inertia-laravel')
        ->and($viteConfig)->not->toBeFalse()
        ->and($viteConfig)->toContain(
            "import tailwindcss from '@tailwindcss/vite';",
            "import laravel from 'laravel-vite-plugin';",
            "'resources/css/app.css'",
            "'resources/css/filament/admin/theme.css'",
            "'resources/js/app.js'",
        )
        ->and($viteConfig)->not->toContain($legacyPluginKey)
        ->not->toContain($legacyCompilerCall)
        ->and($appCss)->not->toBeFalse()
        ->and($appCss)->toContain('@plugin "daisyui"', 'vendor/robsontenorio/mary')
        ->and($prettierConfig)->not->toBeFalse()
        ->and($prettierConfig)->not->toContain($legacyPrettierPlugin)
        ->not->toContain($legacyParserSnippet)
        ->and(base_path('.npmrc'))->not->toBeFile()
        ->and(base_path('components.json'))->not->toBeFile()
        ->and($readme)->not->toBeFalse()
        ->and($readme)->not->toContain($legacyTransportLabel)
        ->not->toContain($legacyRuntimeLabel);
});
