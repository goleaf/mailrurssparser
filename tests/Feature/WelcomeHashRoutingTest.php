<?php

it('boots the welcome shell from the current hash route on the client', function () {
    $welcomePage = file_get_contents(resource_path('js/pages/Welcome.svelte'));
    $appBootstrap = file_get_contents(resource_path('js/app.ts'));

    expect($welcomePage)->not->toBeFalse()
        ->and($welcomePage)->toContain(
            "return parseRoute(window.location.hash || '#/');",
            'let currentRoute = $state<PublicRoute>(resolveInitialRoute());',
        )
        ->and($appBootstrap)->not->toBeFalse()
        ->and($appBootstrap)->toContain(
            'function shouldHydrate(el: HTMLElement): boolean {',
            "const currentHash = window.location.hash || '';",
            "return currentHash === '' || currentHash === '#' || currentHash === '#/';",
        );
});
