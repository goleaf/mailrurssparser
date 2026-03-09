<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import {
        onDestroy,
        onMount,
    } from 'svelte';
    import { setSeoMeta } from '@/composables/useSeo.js';
    import {
        filters,
        setCategory,
        setContentType,
        setDateBoundary,
        setPage,
        setSort,
        searchArticleContentTypeOptions,
    } from '@/features/articles';
    import {
        absolutePublicUrl,
        AppHead,
        appCategories,
        articleUrl,
        categoryUrl,
        initApp,
        replacePublic,
        searchQueryFromUrl,
        searchUrl,
        tagUrl,
        visitPublic,
    } from '@/features/portal';
    import * as api from '@/features/portal';
    import {
        buildSearchAutocompleteItems,
        emptySearchSuggestions,
        SearchHeroPanel,
        SearchResultsSection,
        SearchSidebar,
    } from '@/features/search';
    import type {
        SearchAutocompleteItem,
        SearchSuggestionCategory,
        SearchSuggestionTag,
        SearchSuggestions,
    } from '@/features/search';

    type Category = SearchSuggestionCategory;
    type Tag = SearchSuggestionTag;

    type Article = {
        id: number | string;
        title: string;
        slug: string;
        short_description?: string | null;
        image_url?: string | null;
        content_type?: string | null;
        is_breaking?: boolean;
        is_recent?: boolean;
        views_count?: number | null;
        reading_time?: number | null;
        published_at?: string | null;
        category: Category;
        tags?: Tag[];
    };

    type SearchSuggestionItem = {
        type: 'category' | 'tag';
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
    };

    type PaginationMeta = {
        current_page?: number;
        last_page?: number;
        total?: number;
        total_results?: number;
    };

    type HighlightItem = {
        articleId: number | string;
        title: string;
        slug: string;
        segments: Array<{
            text: string;
            highlighted: boolean;
        }>;
    };

    type SearchFilters = {
        category: string | null;
        content_type: string | null;
        date_from: string | null;
        date_to: string | null;
        sort: 'relevance' | 'latest' | 'popular';
        page: number;
        per_page: number;
    };

    const RECENT_SEARCHES_KEY = 'news-portal-recent-searches';

    const sortTabs = [
        { key: 'relevance', label: 'Релевантность' },
        { key: 'latest', label: 'Новые' },
        { key: 'popular', label: 'Популярные' },
    ] as const;

    const contentTypeOptions = searchArticleContentTypeOptions;

    let query = $state('');
    let activeQuery = $state('');
    let results = $state<Article[]>([]);
    let pagination = $state<PaginationMeta | null>(null);
    let loading = $state(false);
    let suggestionsLoading = $state(false);
    let suggestions = $state<SearchSuggestions>({ ...emptySearchSuggestions });
    let emptyStateSuggestions = $state<SearchSuggestionItem[]>([]);
    let highlights = $state<HighlightItem[]>([]);
    let activeSuggestionIndex = $state(-1);
    let autocompleteAbortController: AbortController | null = null;
    let autocompleteRequestVersion = 0;
    let autocompleteTimer: ReturnType<typeof setTimeout> | null = null;
    const searchFilters = $derived($filters as SearchFilters);

    const categories = $derived(($appCategories ?? []) as Category[]);
    const currentPageUrl = $derived(page.url ?? searchUrl());
    const totalResults = $derived(
        Number(
            pagination?.total ?? pagination?.total_results ?? results.length,
        ),
    );
    const currentPage = $derived(Number(pagination?.current_page ?? 1));
    const lastPage = $derived(Number(pagination?.last_page ?? 1));
    const searchSnapshots = $derived([
        {
            label: 'Рубрик в навигации',
            value: categories.length,
            caption: 'доступно для фильтрации',
        },
        {
            label: 'Подсказок',
            value:
                suggestions.articles.length +
                suggestions.categories.length +
                suggestions.tags.length,
            caption: activeQuery ? 'по текущему запросу' : 'до запуска поиска',
        },
        {
            label: 'Результатов',
            value: activeQuery ? totalResults : '—',
            caption: activeQuery ? 'в выдаче сейчас' : 'появятся после запроса',
        },
    ]);

    function readRecentSearches(): string[] {
        if (typeof localStorage === 'undefined') {
            return [];
        }

        try {
            const stored = localStorage.getItem(RECENT_SEARCHES_KEY);

            if (!stored) {
                return [];
            }

            const parsed = JSON.parse(stored);

            return Array.isArray(parsed) ? parsed.slice(0, 5) : [];
        } catch {
            return [];
        }
    }

    function rememberSearch(value: string): void {
        const normalized = value.trim();

        if (!normalized || typeof localStorage === 'undefined') {
            return;
        }

        const next = [
            normalized,
            ...readRecentSearches().filter(
                (item) => item.toLowerCase() !== normalized.toLowerCase(),
            ),
        ].slice(0, 5);

        localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(next));
    }

    function updateSearchUrl(value: string): void {
        replacePublic(searchUrl(value));
    }

    function clearResults(): void {
        activeQuery = '';
        results = [];
        pagination = null;
        highlights = [];
        emptyStateSuggestions = [];
        activeSuggestionIndex = -1;
    }

    function buildHighlightSegments(
        excerpt: string,
    ): Array<{ text: string; highlighted: boolean }> {
        const segments: Array<{ text: string; highlighted: boolean }> = [];
        const pattern = /<mark>(.*?)<\/mark>/giu;
        let lastIndex = 0;

        for (const match of excerpt.matchAll(pattern)) {
            const [fullMatch, highlightedText = ''] = match;
            const index = match.index ?? 0;

            if (index > lastIndex) {
                segments.push({
                    text: excerpt.slice(lastIndex, index),
                    highlighted: false,
                });
            }

            segments.push({
                text: highlightedText,
                highlighted: true,
            });

            lastIndex = index + fullMatch.length;
        }

        if (lastIndex < excerpt.length) {
            segments.push({
                text: excerpt.slice(lastIndex),
                highlighted: false,
            });
        }

        return segments.length > 0
            ? segments
            : [{ text: excerpt, highlighted: false }];
    }

    async function loadHighlights(
        term: string,
        articles: Article[],
    ): Promise<void> {
        const topMatches = articles.slice(0, 3);

        if (term.trim().length < 2 || topMatches.length === 0) {
            highlights = [];

            return;
        }

        const settled = await Promise.allSettled(
            topMatches.map((article) =>
                api.searchHighlights(term, Number(article.id)),
            ),
        );

        highlights = settled.flatMap((result, index) => {
            if (result.status !== 'fulfilled') {
                return [];
            }

            const article = topMatches[index];
            const excerpt = result.value.data?.excerpt;

            if (!excerpt) {
                return [];
            }

            return [
                {
                    articleId: article.id,
                    title: article.title,
                    slug: article.slug,
                    segments: buildHighlightSegments(excerpt),
                },
            ];
        });
    }

    async function doSearch(
        nextQuery: string,
        page = 1,
        syncUrl = true,
    ): Promise<void> {
        const normalized = nextQuery.trim();

        if (normalized.length < 2) {
            clearResults();
            if (syncUrl) {
                updateSearchUrl('');
            }
            loading = false;
            suggestionsLoading = false;
            suggestions = { ...emptySearchSuggestions };

            return;
        }

        loading = true;
        setPage(page);

        try {
            const response = await api.search(normalized, {
                page,
                category: searchFilters.category,
                content_type: searchFilters.content_type,
                date_from: searchFilters.date_from,
                date_to: searchFilters.date_to,
                sort: searchFilters.sort,
                per_page: searchFilters.per_page,
            });

            results = response.data;
            pagination = response.meta ?? null;
            emptyStateSuggestions = response.meta?.suggestions ?? [];
            activeQuery = normalized;
            query = normalized;
            rememberSearch(normalized);
            if (syncUrl) {
                updateSearchUrl(normalized);
            }
            suggestions = { ...emptySearchSuggestions };
            activeSuggestionIndex = -1;
            await loadHighlights(normalized, results);
        } catch (error) {
            clearResults();

            if (
                error instanceof Error &&
                'status' in error &&
                Number(error.status) === 422
            ) {
                return;
            }
        } finally {
            loading = false;
        }
    }

    function submitSearch(nextQuery = query): void {
        void doSearch(nextQuery, 1);
    }

    function clearSearch(): void {
        clearAutocompleteTimer();
        cancelAutocompleteRequest();
        query = '';
        suggestionsLoading = false;
        suggestions = { ...emptySearchSuggestions };
        clearResults();
        updateSearchUrl('');
    }

    function handleQueryInput(nextQuery: string): void {
        query = nextQuery;
        activeSuggestionIndex = -1;
    }

    function handleCategoryChange(category: string | null): void {
        setCategory(category);

        if (activeQuery) {
            void doSearch(activeQuery, 1);
        }
    }

    function handleContentTypeChange(contentType: string | null): void {
        setContentType(contentType);

        if (activeQuery) {
            void doSearch(activeQuery, 1);
        }
    }

    function handleDateChange(
        key: 'date_from' | 'date_to',
        value: string | null,
    ): void {
        setDateBoundary(key, value);

        if (activeQuery) {
            void doSearch(activeQuery, 1);
        }
    }

    function handleSortChange(sort: (typeof sortTabs)[number]['key']): void {
        setSort(sort);

        if (activeQuery) {
            void doSearch(activeQuery, 1);
        }
    }

    function changePage(page: number): void {
        if (
            !activeQuery ||
            page === currentPage ||
            page < 1 ||
            page > lastPage
        ) {
            return;
        }

        setPage(page);
        void doSearch(activeQuery, page);

        if (typeof window !== 'undefined') {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function navigateToCategory(slug: string): void {
        visitPublic(categoryUrl(slug));
    }

    function navigateToTag(slug: string): void {
        visitPublic(tagUrl(slug));
    }

    function openArticle(slug: string): void {
        visitPublic(articleUrl(slug));
    }

    function selectAutocompleteItem(item: SearchAutocompleteItem): void {
        switch (item.kind) {
            case 'search':
                submitSearch(item.query);

                return;
            case 'article':
                openArticle(item.article.slug);

                return;
            case 'category':
                navigateToCategory(item.category.slug);

                return;
            case 'tag':
                navigateToTag(item.tag.slug);

                return;
        }
    }

    function handleQueryKeydown(event: KeyboardEvent): void {
        const items = buildSearchAutocompleteItems(query, suggestions);

        if (event.key === 'ArrowDown') {
            if (items.length === 0) {
                return;
            }

            event.preventDefault();
            activeSuggestionIndex =
                (activeSuggestionIndex + 1 + items.length) % items.length;

            return;
        }

        if (event.key === 'ArrowUp') {
            if (items.length === 0) {
                return;
            }

            event.preventDefault();
            activeSuggestionIndex =
                activeSuggestionIndex <= 0
                    ? items.length - 1
                    : activeSuggestionIndex - 1;

            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();

            if (activeSuggestionIndex >= 0 && items[activeSuggestionIndex]) {
                selectAutocompleteItem(items[activeSuggestionIndex]);

                return;
            }

            submitSearch();
        }
    }

    function clearAutocompleteTimer(): void {
        if (autocompleteTimer !== null && typeof window !== 'undefined') {
            window.clearTimeout(autocompleteTimer);
            autocompleteTimer = null;
        }
    }

    function cancelAutocompleteRequest(): void {
        autocompleteAbortController?.abort();
        autocompleteAbortController = null;
    }

    onDestroy(() => {
        clearAutocompleteTimer();
        cancelAutocompleteRequest();
    });

    $effect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const normalized = query.trim();

        if (normalized.length < 2 || normalized === activeQuery) {
            cancelAutocompleteRequest();
            suggestionsLoading = false;
            suggestions = { ...emptySearchSuggestions };
            activeSuggestionIndex = -1;

            return;
        }

        suggestionsLoading = true;
        const requestVersion = ++autocompleteRequestVersion;
        clearAutocompleteTimer();
        autocompleteTimer = window.setTimeout(async () => {
            cancelAutocompleteRequest();
            autocompleteAbortController = new AbortController();

            try {
                const response = await api.suggestSearch(normalized, {
                    signal: autocompleteAbortController.signal,
                });

                if (requestVersion !== autocompleteRequestVersion) {
                    return;
                }

                suggestions = response.data;
                activeSuggestionIndex = -1;
            } catch (error) {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }

                if (requestVersion !== autocompleteRequestVersion) {
                    return;
                }

                suggestions = { ...emptySearchSuggestions };
                activeSuggestionIndex = -1;
            } finally {
                if (requestVersion === autocompleteRequestVersion) {
                    suggestionsLoading = false;
                }
            }
        }, 500);

        return () => {
            clearAutocompleteTimer();
        };
    });

    onMount(() => {
        void initApp();
    });

    $effect(() => {
        const nextQuery = searchQueryFromUrl(currentPageUrl);

        if (!nextQuery) {
            if (query === '' && activeQuery === '' && results.length === 0) {
                return;
            }

            query = '';
            clearResults();

            return;
        }

        if (nextQuery === activeQuery && nextQuery === query) {
            return;
        }

        query = nextQuery;
        void doSearch(nextQuery, 1, false);
    });

    $effect(() => {
        setSeoMeta({
            title: activeQuery ? `Поиск: ${activeQuery}` : 'Поиск',
            description: activeQuery
                ? `Результаты поиска по запросу «${activeQuery}» в новостном портале.`
                : 'Поиск по материалам новостного портала.',
            type: 'website',
            url: absolutePublicUrl(searchUrl(activeQuery)),
            tags: [activeQuery].filter(Boolean),
        });
    });
</script>

<AppHead title={activeQuery ? `Поиск: ${activeQuery}` : 'Поиск'} />

<div
    class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.12),_transparent_35%),linear-gradient(to_bottom,_#f8fbff,_#f1f5f9)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.12),_transparent_35%),linear-gradient(to_bottom,_#020617,_#111827)] sm:px-6 lg:px-8"
>
    <div class="mx-auto max-w-7xl">
        <SearchHeroPanel
            {query}
            {categories}
            selectedCategory={searchFilters.category}
            selectedContentType={searchFilters.content_type}
            selectedDateFrom={searchFilters.date_from}
            selectedDateTo={searchFilters.date_to}
            selectedSort={searchFilters.sort}
            {contentTypeOptions}
            {sortTabs}
            {searchSnapshots}
            {suggestions}
            {suggestionsLoading}
            {activeSuggestionIndex}
            on:queryinput={(event) => {
                handleQueryInput(event.detail);
            }}
            on:querykeydown={(event) => {
                handleQueryKeydown(event.detail);
            }}
            on:clear={clearSearch}
            on:search={(event) => {
                submitSearch(event.detail);
            }}
            on:categorychange={(event) => {
                handleCategoryChange(event.detail);
            }}
            on:contenttypechange={(event) => {
                handleContentTypeChange(event.detail);
            }}
            on:datechange={(event) => {
                handleDateChange(event.detail.field, event.detail.value);
            }}
            on:sortchange={(event) => {
                handleSortChange(event.detail);
            }}
            on:articleselect={(event) => {
                openArticle(event.detail);
            }}
            on:categoryselect={(event) => {
                navigateToCategory(event.detail);
            }}
            on:tagselect={(event) => {
                navigateToTag(event.detail);
            }}
        />

        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <SearchResultsSection
                {activeQuery}
                {totalResults}
                {loading}
                {results}
                {emptyStateSuggestions}
                {currentPage}
                {lastPage}
                on:pagechange={(event) => {
                    changePage(event.detail);
                }}
                on:categoryselect={(event) => {
                    navigateToCategory(event.detail);
                }}
                on:tagselect={(event) => {
                    navigateToTag(event.detail);
                }}
            />

            <SearchSidebar
                {highlights}
                {suggestions}
                articleHref={articleUrl}
                on:categoryselect={(event) => {
                    navigateToCategory(event.detail);
                }}
                on:tagselect={(event) => {
                    navigateToTag(event.detail);
                }}
            />
        </div>
    </div>
</div>
