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
        ->toContain("import { onMount } from 'svelte';")
        ->toContain('onMount(() => {');

    expect($breakingTicker)
        ->toContain("import { onMount } from 'svelte';")
        ->toContain('onMount(() => {');

    expect($searchModal)
        ->toContain('onMount(() => {')
        ->toContain('onDestroy(() => {');

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
        ->toContain("import('@/features/search/components/SearchModal.svelte')")
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
    $searchHero = file_get_contents(resource_path('js/features/search/components/page/SearchHeroPanel.svelte'));
    $searchContainer = file_get_contents(resource_path('js/features/search/containers/SearchPageContainer.svelte'));
    $searchModal = file_get_contents(resource_path('js/features/search/components/SearchModal.svelte'));
    $header = file_get_contents(resource_path('js/features/portal/components/Header.svelte'));

    expect($searchHero)
        ->toContain('type Props = {')
        ->toContain('/** Current search query shown in the input. */')
        ->toContain('selectedCategory')
        ->toContain('selectedContentType')
        ->toContain('selectedDateFrom')
        ->toContain('selectedDateTo')
        ->toContain('selectedSort')
        ->not->toContain('$bindable(')
        ->not->toContain('searchFilters:');

    expect($searchContainer)
        ->toContain('selectedCategory={searchFilters.category}')
        ->not->toContain('bind:query');

    expect($searchModal)
        ->toContain('type Props = {')
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
