<?php

it('keeps a legacy hash redirect in the welcome shell', function () {
    $welcomePage = file_get_contents(resource_path('js/pages/Welcome.svelte'));
    $appBootstrap = file_get_contents(resource_path('js/app.ts'));

    expect($welcomePage)->not->toBeFalse()
        ->and($welcomePage)->toContain(
            'function legacyHashToPath(hash: string): string | null {',
            "const legacyPath = legacyHashToPath(window.location.hash || '');",
            'replacePublic(legacyPath);',
        )
        ->and($appBootstrap)->not->toBeFalse()
        ->and($appBootstrap)->toContain(
            'function shouldHydrate(el: HTMLElement): boolean {',
            "return el.dataset.serverRendered === 'true';",
        );
});
