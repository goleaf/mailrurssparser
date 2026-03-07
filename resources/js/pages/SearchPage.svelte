<script lang="ts">
    import Search from 'lucide-svelte/icons/search';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import X from 'lucide-svelte/icons/x';
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ArticleCard from '@/components/article/ArticleCard.svelte';
    import Skeleton from '@/components/ui/skeleton/Skeleton.svelte';
    import * as api from '@/lib/api';
    import { cn } from '@/lib/utils';
    import { appState, initApp } from '@/stores/app.svelte.js';
    import { filters } from '@/stores/articles.svelte.js';

    type Category = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
    };

    type Tag = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
    };

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

    type SearchSuggestions = {
        articles: Array<{
            id: number | string;
            title: string;
            slug: string;
            published_at?: string | null;
        }>;
        categories: Category[];
        tags: Tag[];
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
        sort: string;
        page: number;
        per_page: number;
    };

    const RECENT_SEARCHES_KEY = 'news-portal-recent-searches';
    const emptySuggestions = {
        articles: [],
        categories: [],
        tags: [],
    } satisfies SearchSuggestions;

    const sortTabs = [
        { key: 'relevance', label: 'Релевантность' },
        { key: 'latest', label: 'Новые' },
        { key: 'popular', label: 'Популярные' },
    ] as const;

    const contentTypeOptions = [
        { value: '', label: 'Все форматы' },
        { value: 'news', label: 'Новости' },
        { value: 'article', label: 'Статьи' },
        { value: 'opinion', label: 'Мнения' },
        { value: 'analysis', label: 'Аналитика' },
        { value: 'interview', label: 'Интервью' },
    ] as const;

    let query = $state('');
    let activeQuery = $state('');
    let results = $state<Article[]>([]);
    let pagination = $state<PaginationMeta | null>(null);
    let loading = $state(false);
    let suggestions = $state<SearchSuggestions>({ ...emptySuggestions });
    let emptyStateSuggestions = $state<SearchSuggestionItem[]>([]);
    let highlights = $state<HighlightItem[]>([]);
    let hashListenerAttached = false;
    const searchFilters = filters as SearchFilters;

    const categories = $derived((appState.categories ?? []) as Category[]);
    const totalResults = $derived(
        Number(
            pagination?.total ??
                pagination?.total_results ??
                results.length,
        ),
    );
    const currentPage = $derived(Number(pagination?.current_page ?? 1));
    const lastPage = $derived(Number(pagination?.last_page ?? 1));
    const visiblePages = $derived.by(() => {
        const pages: number[] = [];
        const start = Math.max(1, currentPage - 2);
        const end = Math.min(lastPage, currentPage + 2);

        for (let page = start; page <= end; page += 1) {
            pages.push(page);
        }

        return pages;
    });

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

    function parseSearchQuery(): string {
        if (typeof window === 'undefined') {
            return '';
        }

        const [, rawQuery = ''] = window.location.hash.split('?');
        const params = new URLSearchParams(rawQuery);

        return params.get('q')?.trim() ?? '';
    }

    function updateSearchHash(value: string): void {
        if (typeof window === 'undefined') {
            return;
        }

        const nextHash =
            value.trim().length > 0
                ? `#/search?q=${encodeURIComponent(value.trim())}`
                : '#/search';

        if (window.location.hash !== nextHash) {
            window.location.hash =
                value.trim().length > 0
                    ? `/search?q=${encodeURIComponent(value.trim())}`
                    : '/search';
        }
    }

    function clearResults(): void {
        activeQuery = '';
        results = [];
        pagination = null;
        highlights = [];
        emptyStateSuggestions = [];
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

    async function loadHighlights(term: string, articles: Article[]): Promise<void> {
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

    async function doSearch(nextQuery: string, page = 1): Promise<void> {
        const normalized = nextQuery.trim();

        if (normalized.length < 2) {
            clearResults();
            updateSearchHash('');
            loading = false;

            return;
        }

        loading = true;

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

            results = response.data?.data ?? [];
            pagination = response.data?.meta ?? null;
            emptyStateSuggestions = response.data?.meta?.suggestions ?? [];
            activeQuery = normalized;
            query = normalized;
            rememberSearch(normalized);
            updateSearchHash(normalized);
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

    function submitSearch(): void {
        void doSearch(query, 1);
    }

    function clearSearch(): void {
        query = '';
        suggestions = { ...emptySuggestions };
        clearResults();
        updateSearchHash('');
    }

    function handleHashChange(): void {
        const nextQuery = parseSearchQuery();

        if (!nextQuery) {
            query = '';
            clearResults();

            return;
        }

        if (nextQuery === activeQuery && nextQuery === query) {
            return;
        }

        query = nextQuery;
        void doSearch(nextQuery, 1);
    }

    function handleCategoryChange(event: Event): void {
        const target = event.currentTarget as HTMLSelectElement;

        searchFilters.category = target.value || null;
        searchFilters.page = 1;

        if (activeQuery) {
            void doSearch(activeQuery, 1);
        }
    }

    function handleContentTypeChange(event: Event): void {
        const target = event.currentTarget as HTMLSelectElement;

        searchFilters.content_type = target.value || null;
        searchFilters.page = 1;

        if (activeQuery) {
            void doSearch(activeQuery, 1);
        }
    }

    function handleDateChange(
        key: 'date_from' | 'date_to',
        event: Event,
    ): void {
        const target = event.currentTarget as HTMLInputElement;

        searchFilters[key] = target.value || null;
        searchFilters.page = 1;

        if (activeQuery) {
            void doSearch(activeQuery, 1);
        }
    }

    function handleSortChange(sort: (typeof sortTabs)[number]['key']): void {
        searchFilters.sort = sort;
        searchFilters.page = 1;

        if (activeQuery) {
            void doSearch(activeQuery, 1);
        }
    }

    function changePage(page: number): void {
        if (!activeQuery || page === currentPage || page < 1 || page > lastPage) {
            return;
        }

        searchFilters.page = page;
        void doSearch(activeQuery, page);

        if (typeof window !== 'undefined') {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function navigateToCategory(slug: string): void {
        if (typeof window === 'undefined') {
            return;
        }

        window.location.hash = `/category/${slug}`;
    }

    function navigateToTag(slug: string): void {
        if (typeof window === 'undefined') {
            return;
        }

        window.location.hash = `/tag/${slug}`;
    }

    function handleQueryKeydown(event: KeyboardEvent): void {
        if (event.key === 'Enter') {
            event.preventDefault();
            submitSearch();
        }
    }

    $effect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const normalized = query.trim();

        if (!normalized || normalized === activeQuery) {
            if (!normalized) {
                suggestions = { ...emptySuggestions };
            }

            return;
        }

        const timeoutId = window.setTimeout(async () => {
            try {
                const response = await api.suggestSearch(normalized);

                suggestions = {
                    articles: response.data?.articles ?? [],
                    categories: response.data?.categories ?? [],
                    tags: response.data?.tags ?? [],
                };
            } catch {
                suggestions = { ...emptySuggestions };
            }
        }, 500);

        return () => {
            window.clearTimeout(timeoutId);
        };
    });

    onMount(() => {
        void initApp();

        const nextQuery = parseSearchQuery();

        if (nextQuery) {
            query = nextQuery;
            void doSearch(nextQuery, 1);
        }

        if (!hashListenerAttached && typeof window !== 'undefined') {
            window.addEventListener('hashchange', handleHashChange);
            hashListenerAttached = true;
        }

        return () => {
            if (hashListenerAttached && typeof window !== 'undefined') {
                window.removeEventListener('hashchange', handleHashChange);
                hashListenerAttached = false;
            }
        };
    });
</script>

<AppHead title={activeQuery ? `Поиск: ${activeQuery}` : 'Поиск'} />

<div class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.12),_transparent_35%),linear-gradient(to_bottom,_#f8fbff,_#f1f5f9)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.12),_transparent_35%),linear-gradient(to_bottom,_#020617,_#111827)] sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <section class="rounded-[2rem] border border-slate-200/80 bg-white/90 p-6 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.4)] backdrop-blur dark:border-white/10 dark:bg-slate-950/80 sm:p-8">
            <div class="max-w-3xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-4 py-2 text-xs font-semibold tracking-[0.24em] text-sky-700 uppercase dark:border-sky-900/60 dark:bg-sky-950/50 dark:text-sky-300">
                    <Sparkles class="size-4" />
                    Поиск по порталу
                </div>

                <h1 class="mt-5 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-4xl">
                    Найдите новости, рубрики и темы за секунды
                </h1>

                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base">
                    Ищите по заголовкам, описаниям, авторам и полному тексту.
                    Фильтруйте выдачу по рубрике, формату и датам, не выходя
                    из страницы.
                </p>
            </div>

            <div class="mt-8 space-y-5">
                <div class="rounded-[1.75rem] bg-slate-100 p-2 dark:bg-white/5">
                    <div class="flex flex-col gap-3 rounded-[1.25rem] bg-white px-4 py-3 shadow-sm dark:bg-slate-900 md:flex-row md:items-center">
                        <Search class="size-5 shrink-0 text-slate-400" />
                        <input
                            bind:value={query}
                            type="text"
                            class="min-w-0 flex-1 bg-transparent text-base text-slate-900 outline-none placeholder:text-slate-400 dark:text-white"
                            placeholder="Например: санкции, спорт, интервью"
                            onkeydown={handleQueryKeydown}
                        />
                        {#if query}
                            <button
                                type="button"
                                class="inline-flex size-10 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-white"
                                onclick={clearSearch}
                                aria-label="Очистить поиск"
                            >
                                <X class="size-4" />
                            </button>
                        {/if}
                        <button
                            type="button"
                            class="rounded-full bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            onclick={submitSearch}
                        >
                            Искать
                        </button>
                    </div>
                </div>

                <div class="grid gap-3 lg:grid-cols-[1.2fr_1fr_1fr_1fr]">
                    <label class="space-y-2">
                        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                            Рубрика
                        </span>
                        <select
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-sky-300 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200"
                            value={searchFilters.category ?? ''}
                            onchange={handleCategoryChange}
                        >
                            <option value="">Все категории</option>
                            {#each categories as category (category.id)}
                                <option value={category.slug}>{category.name}</option>
                            {/each}
                        </select>
                    </label>

                    <label class="space-y-2">
                        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                            Формат
                        </span>
                        <select
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-sky-300 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200"
                            value={searchFilters.content_type ?? ''}
                            onchange={handleContentTypeChange}
                        >
                            {#each contentTypeOptions as option (option.value)}
                                <option value={option.value}>{option.label}</option>
                            {/each}
                        </select>
                    </label>

                    <label class="space-y-2">
                        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                            С даты
                        </span>
                        <input
                            type="date"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-sky-300 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200"
                            value={searchFilters.date_from ?? ''}
                            onchange={(event) => {
                                handleDateChange('date_from', event);
                            }}
                        />
                    </label>

                    <label class="space-y-2">
                        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                            По дату
                        </span>
                        <input
                            type="date"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-sky-300 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200"
                            value={searchFilters.date_to ?? ''}
                            onchange={(event) => {
                                handleDateChange('date_to', event);
                            }}
                        />
                    </label>
                </div>

                <div class="flex flex-wrap gap-2">
                    {#each sortTabs as tab (tab.key)}
                        <button
                            type="button"
                            class={cn(
                                'rounded-full px-4 py-2 text-sm font-medium transition',
                                searchFilters.sort === tab.key
                                    ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                            )}
                            onclick={() => {
                                handleSortChange(tab.key);
                            }}
                        >
                            {tab.label}
                        </button>
                    {/each}
                </div>
            </div>
        </section>

        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="space-y-6">
                {#if activeQuery}
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                Результаты поиска
                            </div>
                            <h2 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">
                                Найдено: {totalResults} результатов
                            </h2>
                        </div>
                        <div class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300">
                            Запрос: <span class="font-semibold text-slate-900 dark:text-white">{activeQuery}</span>
                        </div>
                    </div>
                {/if}

                {#if loading}
                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {#each Array.from({ length: 6 }) as _, index (index)}
                            <div class="rounded-3xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-900">
                                <Skeleton class="h-48 w-full rounded-2xl" />
                                <div class="mt-4 space-y-3">
                                    <Skeleton class="h-4 w-24" />
                                    <Skeleton class="h-5 w-full" />
                                    <Skeleton class="h-5 w-4/5" />
                                    <Skeleton class="h-4 w-full" />
                                    <Skeleton class="h-4 w-3/4" />
                                </div>
                            </div>
                        {/each}
                    </div>
                {:else if activeQuery && results.length === 0}
                    <div class="rounded-[2rem] border border-dashed border-slate-300 bg-white p-8 text-center dark:border-white/10 dark:bg-slate-900">
                        <h3 class="text-2xl font-semibold text-slate-950 dark:text-white">
                            По запросу “{activeQuery}” ничего не найдено
                        </h3>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                            Попробуйте сократить запрос или перейти в похожую рубрику.
                        </p>

                        {#if emptyStateSuggestions.length > 0}
                            <div class="mt-6 flex flex-wrap justify-center gap-3">
                                {#each emptyStateSuggestions as suggestion (suggestion.type + String(suggestion.id))}
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                        onclick={() => {
                                            if (suggestion.type === 'category') {
                                                navigateToCategory(suggestion.slug);
                                                return;
                                            }

                                            navigateToTag(suggestion.slug);
                                        }}
                                    >
                                        <span
                                            class="size-2 rounded-full"
                                            style={`background-color: ${suggestion.color ?? '#2563EB'};`}
                                        ></span>
                                        {suggestion.type === 'category' ? 'Рубрика' : 'Тег'}: {suggestion.name}
                                    </button>
                                {/each}
                            </div>
                        {/if}
                    </div>
                {:else if results.length > 0}
                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {#each results as article (article.id)}
                            <ArticleCard {article} />
                        {/each}
                    </div>
                {:else}
                    <div class="rounded-[2rem] border border-dashed border-slate-300 bg-white p-8 text-center dark:border-white/10 dark:bg-slate-900">
                        <h3 class="text-2xl font-semibold text-slate-950 dark:text-white">
                            Начните поиск
                        </h3>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                            Введите минимум два символа, чтобы получить результаты и подсказки.
                        </p>
                    </div>
                {/if}

                {#if lastPage > 1}
                    <div class="flex flex-wrap items-center justify-center gap-2 rounded-[1.75rem] border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-900">
                        <button
                            type="button"
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                            onclick={() => {
                                changePage(currentPage - 1);
                            }}
                            disabled={currentPage <= 1}
                        >
                            Назад
                        </button>

                        {#each visiblePages as page (page)}
                            <button
                                type="button"
                                class={cn(
                                    'inline-flex size-10 items-center justify-center rounded-full text-sm font-medium transition',
                                    page === currentPage
                                        ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                                )}
                                onclick={() => {
                                    changePage(page);
                                }}
                            >
                                {page}
                            </button>
                        {/each}

                        <button
                            type="button"
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                            onclick={() => {
                                changePage(currentPage + 1);
                            }}
                            disabled={currentPage >= lastPage}
                        >
                            Дальше
                        </button>
                    </div>
                {/if}
            </section>

            <aside class="space-y-5">
                <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-900">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                        Подсветка совпадений
                    </div>

                    <div class="mt-4 space-y-3">
                        {#if highlights.length > 0}
                            {#each highlights as item (String(item.articleId))}
                                <a
                                    href={`/#/articles/${item.slug}`}
                                    class="block rounded-2xl border border-slate-200 p-4 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5"
                                >
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white">
                                        {item.title}
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                        {#each item.segments as segment, index (index)}
                                            {#if segment.highlighted}
                                                <mark class="rounded bg-sky-100 px-1 text-slate-900 dark:bg-sky-500/25 dark:text-sky-50">
                                                    {segment.text}
                                                </mark>
                                            {:else}
                                                {segment.text}
                                            {/if}
                                        {/each}
                                    </p>
                                </a>
                            {/each}
                        {:else}
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                После поиска здесь появятся самые точные совпадения по тексту.
                            </p>
                        {/if}
                    </div>
                </section>

                <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-900">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                        Похожие запросы
                    </div>

                    <div class="mt-4 space-y-4">
                        <div>
                            <div class="text-sm font-semibold text-slate-900 dark:text-white">
                                Рубрики
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                {#if suggestions.categories.length > 0}
                                    {#each suggestions.categories as category (category.id)}
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                            onclick={() => {
                                                navigateToCategory(category.slug);
                                            }}
                                        >
                                            <span
                                                class="size-2 rounded-full"
                                                style={`background-color: ${category.color ?? '#2563EB'};`}
                                            ></span>
                                            {category.name}
                                        </button>
                                    {/each}
                                {:else}
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        Подходящие рубрики появятся после ввода запроса.
                                    </p>
                                {/if}
                            </div>
                        </div>

                        <div>
                            <div class="text-sm font-semibold text-slate-900 dark:text-white">
                                Теги
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                {#if suggestions.tags.length > 0}
                                    {#each suggestions.tags as tag (tag.id)}
                                        <button
                                            type="button"
                                            class="rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-200 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                                            onclick={() => {
                                                navigateToTag(tag.slug);
                                            }}
                                        >
                                            #{tag.name}
                                        </button>
                                    {/each}
                                {:else}
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        Подходящие теги появятся после ввода запроса.
                                    </p>
                                {/if}
                            </div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>
