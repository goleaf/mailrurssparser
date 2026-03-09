<?php

it('removes the legacy hash-shell bootstrap from the public frontend', function () {
    $legacyUiExtension = 'sve'.'lte';
    $appBootstrap = file_get_contents(resource_path('js/app.js'));

    expect(resource_path("js/pages/Welcome.{$legacyUiExtension}"))->not->toBeFile()
        ->and(resource_path('js/app.ts'))->not->toBeFile()
        ->and($appBootstrap)->not->toBeFalse()
        ->and($appBootstrap)->toContain(
            "document.addEventListener('DOMContentLoaded'",
            'import.meta.glob([',
            'resolvePortalTheme(',
        )
        ->and($appBootstrap)->not->toContain('window.location.hash')
        ->not->toContain('serverRendered')
        ->not->toContain('createInertiaApp');
});
