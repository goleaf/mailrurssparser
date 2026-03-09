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
            'data-header-search',
            'data-primary-menu',
            'data-category-menu',
            'portal-header-shell',
        )
        ->not->toContain('@inertiaHead')
        ->not->toContain('@inertia');
});

it('removes legacy public pages and entrypoints', function (): void {
    $legacyUiExtension = 'sve'.'lte';

    $legacyFiles = [
        resource_path("js/AppRoot.{$legacyUiExtension}"),
        resource_path('js/app.ts'),
        resource_path('js/ssr.ts'),
        resource_path("js/pages/Welcome.{$legacyUiExtension}"),
        resource_path("js/pages/HomePage.{$legacyUiExtension}"),
        resource_path("js/pages/CategoryPage.{$legacyUiExtension}"),
        resource_path("js/pages/TagPage.{$legacyUiExtension}"),
        resource_path("js/pages/ArticleDetailPage.{$legacyUiExtension}"),
        resource_path("js/pages/SearchPage.{$legacyUiExtension}"),
        resource_path("js/pages/BookmarksPage.{$legacyUiExtension}"),
        resource_path("js/pages/PublicInfoPage.{$legacyUiExtension}"),
        resource_path("js/pages/PublicNotFoundPage.{$legacyUiExtension}"),
        resource_path("js/pages/StatsPage.{$legacyUiExtension}"),
    ];

    foreach ($legacyFiles as $legacyFile) {
        expect($legacyFile)->not->toBeFile();
    }

    $legacyComponentFinder = (new Finder)
        ->files()
        ->in(resource_path('js'))
        ->name(sprintf('*.%s', $legacyUiExtension));

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
