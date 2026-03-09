<?php

it('routes public pages through feature entrypoints', function (): void {
    $pageFiles = [
        'js/pages/Welcome.svelte',
        'js/pages/HomePage.svelte',
        'js/pages/CategoryPage.svelte',
        'js/pages/TagPage.svelte',
        'js/pages/ArticleDetailPage.svelte',
        'js/pages/SearchPage.svelte',
        'js/pages/BookmarksPage.svelte',
        'js/pages/PublicInfoPage.svelte',
        'js/pages/PublicNotFoundPage.svelte',
        'js/pages/StatsPage.svelte',
        'js/AppRoot.svelte',
        'js/app.ts',
    ];

    foreach ($pageFiles as $relativePath) {
        $contents = file_get_contents(resource_path($relativePath));

        expect($contents)
            ->toContain('@/features/')
            ->not->toContain('@/lib/api')
            ->not->toContain('@/lib/publicRoutes')
            ->not->toContain('@/stores/app.svelte.js')
            ->not->toContain('@/stores/articles.svelte.js')
            ->not->toContain('@/stores/bookmarks.svelte.js');
    }
});

it('defines feature barrel files for the public frontend', function (): void {
    $barrelFiles = [
        resource_path('js/features/home/index.ts'),
        resource_path('js/features/portal/index.ts'),
        resource_path('js/features/articles/index.ts'),
        resource_path('js/features/search/index.ts'),
        resource_path('js/features/bookmarks/index.ts'),
        resource_path('js/features/stats/index.ts'),
    ];

    foreach ($barrelFiles as $barrelFile) {
        expect($barrelFile)
            ->toBeFile()
            ->and(file_get_contents($barrelFile))
            ->toContain('export');
    }
});

it('keeps frontend tooling and file naming conventions explicit', function (): void {
    $packageJson = json_decode(file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
    $scripts = $packageJson['scripts'] ?? [];
    $devDependencies = $packageJson['devDependencies'] ?? [];

    expect($scripts)
        ->toHaveKey('lint:check')
        ->toHaveKey('format:check')
        ->toHaveKey('test')
        ->toHaveKey('test:watch');

    expect($scripts['lint:check'])->toBe('eslint resources/js');
    expect($scripts['format:check'])->toBe('prettier --check resources/');
    expect($scripts['test'])->toBe('vitest run');
    expect($scripts['test:watch'])->toBe('vitest');

    expect($devDependencies)
        ->toHaveKey('eslint-plugin-svelte')
        ->toHaveKey('prettier-plugin-svelte')
        ->toHaveKey('vitest')
        ->toHaveKey('@testing-library/svelte')
        ->toHaveKey('@testing-library/jest-dom');

    $pageFinder = (new Symfony\Component\Finder\Finder)
        ->files()
        ->in(resource_path('js/pages'))
        ->name('*.svelte');

    foreach ($pageFinder as $pageFile) {
        expect($pageFile->getFilename())->toMatch('/^[A-Z][A-Za-z0-9]*\\.svelte$/');
    }

    $featureComponentFinder = (new Symfony\Component\Finder\Finder)
        ->files()
        ->in(resource_path('js/features'))
        ->name('*.svelte')
        ->filter(function (SplFileInfo $file): bool {
            $pathname = str_replace('\\', '/', $file->getPathname());

            return str_contains($pathname, '/components/')
                || str_contains($pathname, '/containers/');
        });

    foreach ($featureComponentFinder as $componentFile) {
        expect($componentFile->getFilename())->toMatch('/^[A-Z][A-Za-z0-9]*\\.svelte$/');
    }

    expect(basename(resource_path('js/AppRoot.svelte')))->toMatch('/^[A-Z][A-Za-z0-9]*\\.svelte$/');

    $storeFinder = (new Symfony\Component\Finder\Finder)
        ->files()
        ->in(resource_path('js/features'))
        ->name('*.svelte.js')
        ->filter(function (SplFileInfo $file): bool {
            return str_contains(str_replace('\\', '/', $file->getPathname()), '/state/');
        });

    foreach ($storeFinder as $storeFile) {
        expect($storeFile->getFilename())->toMatch('/^[a-z][A-Za-z0-9]*\\.svelte\\.js$/');
    }

    $testFinder = (new Symfony\Component\Finder\Finder)
        ->files()
        ->in(resource_path('js'))
        ->name('*.test.ts');

    foreach ($testFinder as $testFile) {
        expect($testFile->getFilename())->toMatch('/^[A-Za-z][A-Za-z0-9]*\\.test\\.ts$/');
    }
});

it('keeps large Inertia page files as thin wrappers around feature containers', function (): void {
    $wrappers = [
        'js/pages/HomePage.svelte' => 'HomePageContainer',
        'js/pages/SearchPage.svelte' => 'SearchPageContainer',
        'js/pages/ArticleDetailPage.svelte' => 'ArticleDetailPageContainer',
        'js/pages/StatsPage.svelte' => 'StatsPageContainer',
    ];

    foreach ($wrappers as $relativePath => $containerName) {
        $contents = file_get_contents(resource_path($relativePath));

        expect($contents)
            ->toContain($containerName)
            ->not->toContain('fetch(')
            ->not->toContain('Promise.allSettled')
            ->not->toContain('window.addEventListener');
    }
});

it('keeps the stats page split into a data container and focused presentational panels', function (): void {
    $container = file_get_contents(resource_path('js/features/stats/containers/StatsPageContainer.svelte'));

    expect($container)
        ->toContain('StatsHeroPanel')
        ->toContain('StatsOverviewGrid')
        ->toContain('StatsViewsChartPanel')
        ->toContain('StatsCategoryBreakdownPanel')
        ->toContain('StatsArticlesChartPanel')
        ->toContain('StatsTrendingTagsPanel')
        ->toContain('StatsPopularTable')
        ->toContain('StatsFeedStatusTable')
        ->not->toContain('Пульс редакции и поведение аудитории')
        ->not->toContain('Самые читаемые статьи')
        ->not->toContain('Статус парсинга');

    $panelFiles = [
        resource_path('js/features/stats/components/StatsHeroPanel.svelte'),
        resource_path('js/features/stats/components/StatsOverviewGrid.svelte'),
        resource_path('js/features/stats/components/StatsViewsChartPanel.svelte'),
        resource_path('js/features/stats/components/StatsCategoryBreakdownPanel.svelte'),
        resource_path('js/features/stats/components/StatsArticlesChartPanel.svelte'),
        resource_path('js/features/stats/components/StatsTrendingTagsPanel.svelte'),
        resource_path('js/features/stats/components/StatsPopularTable.svelte'),
        resource_path('js/features/stats/components/StatsFeedStatusTable.svelte'),
    ];

    foreach ($panelFiles as $panelFile) {
        expect(file_get_contents($panelFile))
            ->not->toContain("import * as api from '@/features/portal'")
            ->not->toContain('await api.');
    }

    expect(file_get_contents(resource_path('js/features/stats/components/StatsViewsChartPanel.svelte')))
        ->toContain('createEventDispatcher')
        ->toContain("dispatch('periodchange'");

    expect(file_get_contents(resource_path('js/features/stats/components/StatsTrendingTagsPanel.svelte')))
        ->toContain('createEventDispatcher')
        ->toContain("dispatch('tagselect'");

    expect(file_get_contents(resource_path('js/features/stats/components/StatsPopularTable.svelte')))
        ->toContain('createEventDispatcher')
        ->toContain("dispatch('periodchange'");
});

it('updates shared frontend state without direct array mutation helpers', function (): void {
    $stateFiles = [
        resource_path('js/features/articles/state/articles.svelte.js'),
        resource_path('js/features/bookmarks/state/bookmarks.svelte.js'),
        resource_path('js/features/portal/state/app.svelte.js'),
    ];

    foreach ($stateFiles as $stateFile) {
        $contents = file_get_contents($stateFile);

        expect($contents)
            ->not->toContain('.push(')
            ->not->toContain('.splice(')
            ->not->toContain('Object.assign(');
    }
});

it('defines feature-scoped writable and derived stores for shared frontend state', function (): void {
    $stateFiles = [
        resource_path('js/features/articles/state/articles.svelte.js'),
        resource_path('js/features/bookmarks/state/bookmarks.svelte.js'),
        resource_path('js/features/portal/state/app.svelte.js'),
    ];

    foreach ($stateFiles as $stateFile) {
        $contents = file_get_contents($stateFile);

        expect($contents)
            ->toContain("from 'svelte/store'")
            ->toContain('writable(')
            ->toContain('readonly(')
            ->toContain('derived(')
            ->not->toContain('$state(');
    }
});

it('uses explicit derived request snapshots instead of manual reactive sentinel reads', function (): void {
    $pageFiles = [
        resource_path('js/features/home/containers/HomePageContainer.svelte'),
        resource_path('js/pages/CategoryPage.svelte'),
        resource_path('js/pages/TagPage.svelte'),
    ];

    foreach ($pageFiles as $pageFile) {
        $contents = file_get_contents($pageFile);

        expect($contents)
            ->not->toContain('void current')
            ->not->toContain('void active')
            ->not->toContain('void pageNumber')
            ->not->toContain('void perPage');
    }
});

it('consumes shared state through focused store exports instead of monolithic store objects', function (): void {
    $consumerFiles = [
        resource_path('js/features/home/containers/HomePageContainer.svelte'),
        resource_path('js/features/articles/components/FilterBar.svelte'),
        resource_path('js/features/articles/components/sidebar/SidebarCategoryTree.svelte'),
        resource_path('js/features/articles/components/sidebar/SidebarTagCloud.svelte'),
        resource_path('js/features/portal/components/BreakingNewsTicker.svelte'),
        resource_path('js/features/portal/components/Footer.svelte'),
        resource_path('js/features/portal/components/Header.svelte'),
        resource_path('js/pages/TagPage.svelte'),
    ];

    foreach ($consumerFiles as $consumerFile) {
        expect(file_get_contents($consumerFile))
            ->not->toContain('appState.')
            ->not->toContain('bookmarkIds.length')
            ->not->toContain('activeFiltersCount()');
    }
});

it('uses lifecycle hooks for browser-only setup and async cleanup', function (): void {
    $header = file_get_contents(resource_path('js/features/portal/components/Header.svelte'));
    $breakingTicker = file_get_contents(resource_path('js/features/portal/components/BreakingNewsTicker.svelte'));
    $searchModal = file_get_contents(resource_path('js/features/search/components/SearchModal.svelte'));
    $searchPage = file_get_contents(resource_path('js/features/search/containers/SearchPageContainer.svelte'));
    $polling = file_get_contents(resource_path('js/composables/usePolling.svelte.js'));

    expect($header)
        ->toContain('onMount')
        ->toContain('onMount(() => {');

    expect($breakingTicker)
        ->toContain("import { onMount } from 'svelte';")
        ->toContain('onMount(() => {');

    expect($searchModal)
        ->toContain('onDestroy(() => {')
        ->toContain('tick().then(() => {');

    expect($searchPage)
        ->toContain('onDestroy(() => {');

    expect($polling)
        ->toContain("from 'svelte';")
        ->toContain('onMount(() => {')
        ->toContain('onDestroy(() => {');
});

it('lazy loads heavy search ui and avoids inline handlers in repeated list components', function (): void {
    $header = file_get_contents(resource_path('js/features/portal/components/Header.svelte'));
    $autocompletePanel = file_get_contents(resource_path('js/features/search/components/SearchAutocompletePanel.svelte'));
    $resultsSection = file_get_contents(resource_path('js/features/search/components/page/SearchResultsSection.svelte'));
    $sidebar = file_get_contents(resource_path('js/features/search/components/page/SearchSidebar.svelte'));
    $pagination = file_get_contents(resource_path('js/features/articles/components/Pagination.svelte'));
    $bookmarksPage = file_get_contents(resource_path('js/pages/BookmarksPage.svelte'));
    $homeContainer = file_get_contents(resource_path('js/features/home/containers/HomePageContainer.svelte'));

    expect($header)
        ->toContain("SearchModal.svelte'")
        ->not->toContain("import SearchModal from '@/features/search/components/SearchModal.svelte'");

    expect($autocompletePanel)
        ->toContain('const autocompleteSections = $derived.by(() => {')
        ->toContain('function handleItemClick(event: Event): void {')
        ->toContain('onclick={handleItemClick}');

    expect($resultsSection)
        ->toContain('function handleEmptySuggestionClick(event: Event): void {')
        ->toContain('onChange={handlePageChange}');

    expect($sidebar)
        ->toContain('function handleCategorySuggestionClick(event: Event): void {')
        ->toContain('function handleTagSuggestionClick(event: Event): void {');

    expect($pagination)
        ->toContain('function handlePageClick(event: Event): void {')
        ->not->toContain('onclick={() => {');

    expect($bookmarksPage)
        ->not->toContain('on:click={() => {');

    expect($homeContainer)
        ->not->toContain('const briefingDate = $derived(');
});

it('keeps search component props one-way, typed, and flattened', function (): void {
    $autocompletePanel = file_get_contents(resource_path('js/features/search/components/SearchAutocompletePanel.svelte'));
    $searchHero = file_get_contents(resource_path('js/features/search/components/page/SearchHeroPanel.svelte'));
    $searchContainer = file_get_contents(resource_path('js/features/search/containers/SearchPageContainer.svelte'));
    $searchModal = file_get_contents(resource_path('js/features/search/components/SearchModal.svelte'));
    $header = file_get_contents(resource_path('js/features/portal/components/Header.svelte'));

    expect($searchHero)
        ->toContain('interface Props {')
        ->toContain('/** Current search query shown in the input. */')
        ->toContain('selectedCategory')
        ->toContain('selectedContentType')
        ->toContain('selectedDateFrom')
        ->toContain('selectedDateTo')
        ->toContain('selectedSort')
        ->not->toContain('$bindable(')
        ->not->toContain('searchFilters:');

    expect($autocompletePanel)
        ->toContain('interface Props {')
        ->not->toContain('type Props = {');

    expect($searchContainer)
        ->toContain('selectedCategory={searchFilters.category}')
        ->not->toContain('bind:query');

    expect($searchModal)
        ->toContain('interface Props {')
        ->not->toContain('$bindable(');

    expect($header)
        ->toContain('onClose={closeSearch}')
        ->not->toContain('bind:open');
});

it('uses custom component events instead of callback props for presentational communication', function (): void {
    $eventComponents = [
        resource_path('js/features/home/components/HomeHeroPanel.svelte') => [
            'createEventDispatcher',
            "dispatch('clear'",
        ],
        resource_path('js/features/home/components/HomeFeedSection.svelte') => [
            'createEventDispatcher',
            "dispatch('pagechange'",
        ],
        resource_path('js/features/search/components/page/SearchHeroPanel.svelte') => [
            'createEventDispatcher',
            'queryinput: string;',
        ],
        resource_path('js/features/search/components/page/SearchResultsSection.svelte') => [
            'createEventDispatcher',
            "dispatch('categoryselect'",
        ],
        resource_path('js/features/search/components/page/SearchSidebar.svelte') => [
            'createEventDispatcher',
            "dispatch('tagselect'",
        ],
        resource_path('js/features/articles/components/article-detail/ArticleEngagementPanel.svelte') => [
            'createEventDispatcher',
            "dispatch('share'",
        ],
        resource_path('js/features/articles/components/ArticleCard.svelte') => [
            'createEventDispatcher',
            "dispatch('bookmarktoggled'",
        ],
    ];

    foreach ($eventComponents as $componentPath => $expectedFragments) {
        $contents = file_get_contents($componentPath);

        expect($contents)->toContain($expectedFragments[0])->toContain($expectedFragments[1]);
    }

    $consumerFiles = [
        resource_path('js/features/home/containers/HomePageContainer.svelte'),
        resource_path('js/features/search/containers/SearchPageContainer.svelte'),
        resource_path('js/features/articles/containers/ArticleDetailPageContainer.svelte'),
        resource_path('js/pages/BookmarksPage.svelte'),
    ];

    foreach ($consumerFiles as $consumerFile) {
        expect(file_get_contents($consumerFile))
            ->not->toContain('onQueryInput=')
            ->not->toContain('onClearFilters=')
            ->not->toContain('onToggleShareMenu=')
            ->not->toContain('onBookmarkToggle=');
    }
});

it('centralizes reduced-motion handling for purposeful Svelte transitions', function (): void {
    $motionHelper = file_get_contents(resource_path('js/lib/motion.ts'));
    $appCss = file_get_contents(resource_path('css/app.css'));
    $toast = file_get_contents(resource_path('js/components/ui/Toast.svelte'));
    $bookmarksPage = file_get_contents(resource_path('js/pages/BookmarksPage.svelte'));
    $ticker = file_get_contents(resource_path('js/features/portal/components/BreakingNewsTicker.svelte'));
    $filterBar = file_get_contents(resource_path('js/features/articles/components/FilterBar.svelte'));
    $searchModal = file_get_contents(resource_path('js/features/search/components/SearchModal.svelte'));

    expect($motionHelper)
        ->toContain('prefersReducedMotion')
        ->toContain('resolveFadeTransition')
        ->toContain('resolveFlyTransition')
        ->toContain('resolveFlipAnimation')
        ->toContain('resolveSlideTransition');

    expect($appCss)
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->toContain('transition-duration: 1ms !important;');

    expect($toast)
        ->toContain('resolveFlyTransition($prefersReducedMotion')
        ->toContain('resolveFadeTransition($prefersReducedMotion');

    expect($bookmarksPage)
        ->toContain('animate:flip={bookmarkListFlip}')
        ->toContain('resolveFlipAnimation($prefersReducedMotion');

    expect($ticker)
        ->toContain('in:slide={tickerTransition}')
        ->toContain('showStaticHeadlines = $derived(paused || !canToggleTicker)');

    expect($filterBar)
        ->toContain('transition:slide={advancedFiltersTransition}');

    expect($searchModal)
        ->toContain('in:fade={modalBackdropTransition}')
        ->toContain('in:fly={modalPanelTransition}');
});

it('uses semantic dialogs and accessible state for public overlay interactions', function (): void {
    $focusHelper = file_get_contents(resource_path('js/lib/focus.ts'));
    $header = file_get_contents(resource_path('js/features/portal/components/Header.svelte'));
    $searchModal = file_get_contents(resource_path('js/features/search/components/SearchModal.svelte'));
    $autocompletePanel = file_get_contents(resource_path('js/features/search/components/SearchAutocompletePanel.svelte'));
    $toast = file_get_contents(resource_path('js/components/ui/Toast.svelte'));
    $articleCard = file_get_contents(resource_path('js/features/articles/components/ArticleCard.svelte'));
    $articleCardFeatured = file_get_contents(resource_path('js/features/articles/components/ArticleCardFeatured.svelte'));

    expect($focusHelper)
        ->toContain('trapFocusWithin')
        ->toContain('getFocusableElements');

    expect($searchModal)
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('aria-labelledby={SEARCH_DIALOG_TITLE_ID}')
        ->toContain('aria-controls={SEARCH_AUTOCOMPLETE_LISTBOX_ID}')
        ->toContain('aria-activedescendant={activeSuggestionId}')
        ->toContain('trapFocusWithin(event, modalContainer)');

    expect($autocompletePanel)
        ->toContain('id={listboxId}')
        ->toContain('id={getOptionId(');

    expect($header)
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('bind:this={mobileMenuCloseButton}')
        ->not->toContain('role="button"')
        ->not->toContain('tabindex="0"');

    expect($toast)
        ->toContain('aria-live="polite"')
        ->toContain('role="status"');

    expect($articleCard)
        ->toContain('aria-pressed={bookmarkActive}')
        ->toContain('bookmarkLabel');

    expect($articleCardFeatured)
        ->toContain('aria-pressed={bookmarkActive}')
        ->toContain('bookmarkLabel');
});

it('avoids any at frontend type boundaries and prefers interface-based prop contracts', function (): void {
    $typedFiles = [
        resource_path('js/AppRoot.svelte'),
        resource_path('js/features/home/components/HomeHeroPanel.svelte'),
        resource_path('js/features/search/components/SearchAutocompletePanel.svelte'),
        resource_path('js/features/search/components/SearchModal.svelte'),
        resource_path('js/features/search/components/page/SearchHeroPanel.svelte'),
        resource_path('js/types/navigation.ts'),
        resource_path('js/features/articles/state/articles.svelte.js'),
        resource_path('js/components/ui/button/Button.svelte'),
        resource_path('js/components/ui/label/Label.svelte'),
        resource_path('js/components/ui/sidebar/SidebarMenuButton.svelte'),
        resource_path('js/components/ui/dropdown-menu/DropdownMenuTrigger.svelte'),
        resource_path('js/components/ui/dropdown-menu/DropdownMenuItem.svelte'),
    ];

    foreach ($typedFiles as $typedFile) {
        expect(file_get_contents($typedFile))
            ->not->toContain(': any')
            ->not->toContain(' any;')
            ->not->toContain('[key: string]: any');
    }

    expect(file_get_contents(resource_path('js/AppRoot.svelte')))
        ->toContain('interface AppRootProps')
        ->toContain('Component<AppPageProps>');

    expect(file_get_contents(resource_path('js/features/home/components/HomeHeroPanel.svelte')))
        ->toContain('interface HomeHeroPanelProps')
        ->toContain(
            'icon: ComponentType<SvelteComponent<{ class?: string }>>;'
        );

    expect(file_get_contents(resource_path('js/components/ui/label/Label.svelte')))
        ->toContain('interface Props extends HTMLLabelAttributes')
        ->not->toContain('type Props =');

    expect(file_get_contents(resource_path('js/features/articles/state/articles.svelte.js')))
        ->toContain('ApiArticleListItem[]')
        ->toContain('ApiPaginationMeta | null');
});
