<?php

use Symfony\Component\Finder\Finder;

it('renders the public frontend from blade views and public blade components', function (): void {
    $viewFiles = [
        resource_path('views/layouts/app.blade.php'),
        resource_path('views/components/public/article-card.blade.php'),
        resource_path('views/components/public/bookmark-button.blade.php'),
        resource_path('views/public/home.blade.php'),
        resource_path('views/public/category.blade.php'),
        resource_path('views/public/tag.blade.php'),
        resource_path('views/public/article.blade.php'),
        resource_path('views/public/search.blade.php'),
        resource_path('views/public/bookmarks.blade.php'),
        resource_path('views/public/stats.blade.php'),
        resource_path('views/public/info.blade.php'),
        resource_path('views/public/not-found.blade.php'),
    ];

    foreach ($viewFiles as $viewFile) {
        expect($viewFile)->toBeFile();
    }

    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($layout)->not->toBeFalse()
        ->and($layout)->toContain(
            "@vite(['resources/css/app.css', 'resources/js/app.js'])",
            'x-mary-button',
            'data-theme-toggle',
        )
        ->not->toContain('@inertiaHead')
        ->not->toContain('@inertia');
});

it('removes legacy svelte public pages and entrypoints', function (): void {
    $legacyFiles = [
        resource_path('js/AppRoot.svelte'),
        resource_path('js/app.ts'),
        resource_path('js/ssr.ts'),
        resource_path('js/pages/Welcome.svelte'),
        resource_path('js/pages/HomePage.svelte'),
        resource_path('js/pages/CategoryPage.svelte'),
        resource_path('js/pages/TagPage.svelte'),
        resource_path('js/pages/ArticleDetailPage.svelte'),
        resource_path('js/pages/SearchPage.svelte'),
        resource_path('js/pages/BookmarksPage.svelte'),
        resource_path('js/pages/PublicInfoPage.svelte'),
        resource_path('js/pages/PublicNotFoundPage.svelte'),
        resource_path('js/pages/StatsPage.svelte'),
    ];

    foreach ($legacyFiles as $legacyFile) {
        expect($legacyFile)->not->toBeFile();
    }

    $legacyComponentFinder = (new Finder)
        ->files()
        ->in(resource_path('js'))
        ->name('*.svelte');

    expect(iterator_count($legacyComponentFinder))->toBe(0);
});

it('keeps the public JavaScript entry focused on theme and static assets', function (): void {
    $appBootstrap = file_get_contents(resource_path('js/app.js'));
    $themeHelpers = file_get_contents(resource_path('js/lib/theme.ts'));

    expect($appBootstrap)->not->toBeFalse()
        ->and($appBootstrap)->toContain(
            'resolvePortalTheme',
            'nextPortalTheme',
            'import.meta.glob([',
            "document.addEventListener('DOMContentLoaded'",
        )
        ->not->toContain('createInertiaApp')
        ->not->toContain('window.location.hash')
        ->and($themeHelpers)->not->toBeFalse()
        ->and($themeHelpers)->toContain(
            "export type PortalTheme = 'light' | 'dark';",
            'export function resolvePortalTheme(',
            'export function nextPortalTheme(',
        );
});
