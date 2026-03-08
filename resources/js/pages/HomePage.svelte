<script lang="ts">
    import Activity from 'lucide-svelte/icons/activity';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Newspaper from 'lucide-svelte/icons/newspaper';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ArticleCard from '@/components/article/ArticleCard.svelte';
    import ArticleCardCompact from '@/components/article/ArticleCardCompact.svelte';
    import SidebarCategoryTree from '@/components/sidebar/SidebarCategoryTree.svelte';
    import SidebarDateCalendar from '@/components/sidebar/SidebarDateCalendar.svelte';
    import SidebarNewsletterBox from '@/components/sidebar/SidebarNewsletterBox.svelte';
    import SidebarPopularArticles from '@/components/sidebar/SidebarPopularArticles.svelte';
    import SidebarTagCloud from '@/components/sidebar/SidebarTagCloud.svelte';
    import Skeleton from '@/components/ui/skeleton/Skeleton.svelte';
    import * as api from '@/lib/api';
    import { cn } from '@/lib/utils';
    import { appState, initApp } from '@/stores/app.svelte.js';
    import {
        activeFiltersCount,
        filters,
        listState,
        loadArticles,
        resetFilters,
    } from '@/stores/articles.svelte.js';

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

    type PaginationMeta = {
        current_page?: number;
        last_page?: number;
        total?: number;
        total_results?: number;
    };

    type HomeFilters = {
        category: string | null;
        sub: string | null;
        tags: string[];
        content_type: string | null;
        date: string | null;
        date_from: string | null;
        date_to: string | null;
        sort: string;
        search: string;
        page: number;
        per_page: number;
    };

    const sortTabs = [
        { key: 'latest', label: 'Новые' },
        { key: 'popular', label: 'Популярные' },
        { key: 'importance', label: 'Главные' },
    ] as const;

    const contentTypeOptions = [
        { value: null, label: 'Все форматы' },
        { value: 'news', label: 'Новости' },
        { value: 'article', label: 'Статьи' },
        { value: 'analysis', label: 'Аналитика' },
        { value: 'opinion', label: 'Мнения' },
        { value: 'interview', label: 'Интервью' },
    ] as const;

    const contentTypeLabels: Record<string, string> = {
        news: 'Новости',
        article: 'Статьи',
        analysis: 'Аналитика',
        opinion: 'Мнения',
        interview: 'Интервью',
    };

    let featuredArticles = $state<Article[]>([]);
    let trendingArticles = $state<Article[]>([]);
    let highlightsLoading = $state(true);

    const pageFilters = filters as HomeFilters;
    const categories = $derived((appState.categories ?? []) as Category[]);
    const pagination = $derived((listState.pagination ?? null) as PaginationMeta | null);
    const activeFilterTotal = $derived(activeFiltersCount());
    const currentPage = $derived(Number(pagination?.current_page ?? pageFilters.page ?? 1));
    const lastPage = $derived(Number(pagination?.last_page ?? 1));
    const totalResults = $derived(
        Number(pagination?.total ?? pagination?.total_results ?? listState.articles.length),
    );
    const selectedCategory = $derived.by(() => {
        if (!pageFilters.category) {
            return null;
        }

        return categories.find((category) => category.slug === pageFilters.category) ?? null;
    });

    const selectedFilters = $derived.by(() => {
        const chips: string[] = [];

        if (selectedCategory?.name) {
            chips.push(selectedCategory.name);
        }

        if (pageFilters.sub) {
            chips.push(`Подрубрика: ${pageFilters.sub}`);
        }

        if (pageFilters.tags.length > 0) {
            chips.push(...pageFilters.tags.map((tag) => `#${tag}`));
        }

        if (pageFilters.date) {
            chips.push(pageFilters.date);
        }

        if (pageFilters.content_type) {
            chips.push(contentTypeLabels[pageFilters.content_type] ?? pageFilters.content_type);
        }

        return chips;
    });

    const visiblePages = $derived.by(() => {
        const pages: number[] = [];
        const start = Math.max(1, currentPage - 2);
        const end = Math.min(lastPage, currentPage + 2);

        for (let page = start; page <= end; page += 1) {
            pages.push(page);
        }

        return pages;
    });

    const leadStory = $derived.by(() => {
        if (activeFilterTotal > 0) {
            return (listState.articles[0] as Article | undefined) ?? featuredArticles[0] ?? null;
        }

        return featuredArticles[0] ?? ((listState.articles[0] as Article | undefined) ?? null);
    });

    const secondaryHighlights = $derived.by(() => {
        if (activeFilterTotal > 0) {
            return (listState.articles.slice(1, 4) as Article[]).filter(Boolean);
        }

        const preferred = featuredArticles.slice(1, 4);

        if (preferred.length > 0) {
            return preferred;
        }

        return (listState.articles.slice(1, 4) as Article[]).filter(Boolean);
    });

    const highlightArticleIds = $derived.by(() => {
        const ids: Array<number | string> = [];

        if (leadStory?.id !== undefined) {
            ids.push(leadStory.id);
        }

        for (const article of secondaryHighlights) {
            if (!ids.includes(article.id)) {
                ids.push(article.id);
            }
        }

        return ids;
    });

    const streamArticles = $derived.by(() =>
        (listState.articles as Article[]).filter(
            (article) => !highlightArticleIds.includes(article.id),
        ),
    );

    const trendingPreview = $derived.by(() => {
        if (trendingArticles.length > 0) {
            return trendingArticles.slice(0, 4);
        }

        return featuredArticles.slice(0, 4);
    });

    const overviewStats = $derived.by(() => [
        {
            label: 'Рубрик',
            value: categories.length,
            caption: 'активных лент',
            icon: Newspaper,
        },
        {
            label: 'Срочно',
            value: (appState.breakingNews ?? []).length,
            caption: 'сейчас в эфире',
            icon: Activity,
        },
        {
            label: 'В потоке',
            value: totalResults,
            caption: activeFilterTotal > 0 ? 'по фильтрам' : 'в общей ленте',
            icon: TrendingUp,
        },
    ]);

    async function loadHighlights(): Promise<void> {
        highlightsLoading = true;

        const [featuredResponse, trendingResponse] = await Promise.allSettled([
            api.getFeatured(),
            api.getTrending(),
        ]);

        featuredArticles =
            featuredResponse.status === 'fulfilled'
                ? featuredResponse.value.data?.data ?? []
                : [];
        trendingArticles =
            trendingResponse.status === 'fulfilled'
                ? trendingResponse.value.data?.data ?? []
                : [];
        highlightsLoading = false;
    }

    function changeSort(sort: (typeof sortTabs)[number]['key']): void {
        pageFilters.sort = sort;
        pageFilters.page = 1;
    }

    function changeContentType(value: (typeof contentTypeOptions)[number]['value']): void {
        pageFilters.content_type = value;
        pageFilters.page = 1;
    }

    function changePage(page: number): void {
        if (page < 1 || page > lastPage || page === currentPage) {
            return;
        }

        pageFilters.page = page;

        if (typeof window !== 'undefined') {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function clearAllFilters(): void {
        resetFilters();
    }

    onMount(() => {
        void loadHighlights();
    });

    $effect(() => {
        void initApp();
    });

    $effect(() => {
        const currentCategory = pageFilters.category;
        const currentSub = pageFilters.sub;
        const currentTags = pageFilters.tags.join(',');
        const currentContentType = pageFilters.content_type;
        const currentDate = pageFilters.date;
        const currentDateFrom = pageFilters.date_from;
        const currentDateTo = pageFilters.date_to;
        const currentSort = pageFilters.sort;
        const currentSearch = pageFilters.search;
        const pageNumber = pageFilters.page;
        const perPage = pageFilters.per_page;

        void currentCategory;
        void currentSub;
        void currentTags;
        void currentContentType;
        void currentDate;
        void currentDateFrom;
        void currentDateTo;
        void currentSort;
        void currentSearch;
        void pageNumber;
        void perPage;

        void loadArticles();
    });
</script>

<AppHead title="Новости">
    <meta
        name="description"
        content="Редакционная лента новостей с быстрым поиском, фильтрами по рубрикам и живой статистикой."
    />
</AppHead>

<div class="relative overflow-hidden bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.18),_transparent_34%),linear-gradient(to_bottom,_#f8fbff,_#eef2ff)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.16),_transparent_30%),linear-gradient(to_bottom,_#020617,_#0f172a)] sm:px-6 lg:px-8">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-56 bg-[radial-gradient(circle_at_top_right,_rgba(14,165,233,0.22),_transparent_42%)]"></div>

    <div class="relative mx-auto max-w-7xl">
        <section class="relative overflow-hidden rounded-[2.5rem] border border-slate-200/80 bg-white/85 p-6 shadow-[0_40px_120px_-60px_rgba(15,23,42,0.45)] backdrop-blur dark:border-white/10 dark:bg-slate-950/80 sm:p-8 lg:p-10">
            <div class="absolute right-0 top-0 h-44 w-44 rounded-full bg-sky-200/60 blur-3xl dark:bg-sky-500/20"></div>
            <div class="absolute bottom-0 left-0 h-40 w-40 rounded-full bg-amber-200/70 blur-3xl dark:bg-amber-500/10"></div>

            <div class="relative grid gap-8 xl:grid-cols-[minmax(0,1.15fr)_minmax(20rem,0.85fr)]">
                <div class="space-y-6">
                    <div class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.26em] text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/50 dark:text-sky-300">
                        <Sparkles class="size-4" />
                        Mailru RSS Parser
                    </div>

                    <div class="max-w-3xl">
                        <h1 class="max-w-3xl text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl lg:text-6xl dark:text-white">
                            Лента, поиск и аналитика в одном публичном интерфейсе.
                        </h1>
                        <p class="mt-5 max-w-2xl text-base leading-7 text-slate-600 dark:text-slate-300 sm:text-lg">
                            Главная страница теперь использует существующие API, фильтры,
                            закладки, поиск и публичные разделы проекта вместо стартового
                            Laravel-шаблона.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a
                            href="/#/search"
                            class="inline-flex items-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        >
                            Открыть поиск
                            <ArrowUpRight class="size-4" />
                        </a>
                        <a
                            href="/#/stats"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
                        >
                            Публичная статистика
                            <TrendingUp class="size-4" />
                        </a>
                        <a
                            href="/#/bookmarks"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
                        >
                            Закладки
                            <Newspaper class="size-4" />
                        </a>
                    </div>

                    {#if activeFilterTotal > 0}
                        <div class="rounded-[2rem] border border-slate-200 bg-slate-50/90 p-4 dark:border-white/10 dark:bg-white/5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                                        Активные фильтры
                                    </div>
                                    <div class="mt-2 text-sm font-medium text-slate-900 dark:text-white">
                                        {activeFilterTotal} выбрано в ленте
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
                                    onclick={clearAllFilters}
                                >
                                    Сбросить всё
                                </button>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                {#each selectedFilters as filterValue (`filter-${filterValue}`)}
                                    <span class="rounded-full bg-slate-900 px-3 py-1.5 text-xs font-medium text-white dark:bg-white dark:text-slate-950">
                                        {filterValue}
                                    </span>
                                {/each}
                            </div>
                        </div>
                    {/if}

                    <div class="grid gap-3 sm:grid-cols-3">
                        {#each overviewStats as item (item.label)}
                            <div class="rounded-[1.75rem] border border-slate-200 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/80">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                        {item.label}
                                    </div>
                                    <div class="rounded-2xl bg-slate-100 p-2 text-slate-700 dark:bg-white/10 dark:text-slate-200">
                                        <item.icon class="size-4" />
                                    </div>
                                </div>
                                <div class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">
                                    {item.value}
                                </div>
                                <div class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                    {item.caption}
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>

                <div class="space-y-5">
                    {#if highlightsLoading}
                        <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900">
                            <Skeleton class="h-[22rem] w-full rounded-[1.5rem]" />
                        </div>
                    {:else if leadStory}
                        <a
                            href={`/#/articles/${leadStory.slug}`}
                            class="group relative block min-h-[24rem] overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-950 text-white shadow-[0_25px_80px_-50px_rgba(15,23,42,0.65)] dark:border-white/10"
                        >
                            {#if leadStory.image_url}
                                <img
                                    src={leadStory.image_url}
                                    alt={leadStory.title}
                                    class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105"
                                />
                            {:else}
                                <div class="absolute inset-0 bg-linear-to-br from-sky-500 via-slate-900 to-slate-950"></div>
                            {/if}

                            <div class="absolute inset-0 bg-linear-to-t from-slate-950 via-slate-950/65 to-slate-900/10"></div>

                            <div class="relative flex min-h-[24rem] flex-col justify-between p-6 sm:p-7">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-semibold text-white shadow-sm"
                                        style={`background-color: ${leadStory.category.color ?? '#0EA5E9'};`}
                                    >
                                        {leadStory.category.name}
                                    </span>
                                    {#if leadStory.is_breaking}
                                        <span class="rounded-full bg-rose-500 px-3 py-1 text-xs font-semibold text-white">
                                            Срочно
                                        </span>
                                    {/if}
                                </div>

                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-white/60">
                                        Главный материал
                                    </div>
                                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-white">
                                        {leadStory.title}
                                    </h2>
                                    {#if leadStory.short_description}
                                        <p class="mt-4 max-w-xl text-sm leading-6 text-white/80">
                                            {leadStory.short_description}
                                        </p>
                                    {/if}

                                    <div class="mt-5 flex flex-wrap items-center gap-4 text-sm text-white/70">
                                        <span>👁 {leadStory.views_count ?? 0}</span>
                                        <span>⏱ {leadStory.reading_time ?? 1} мин</span>
                                        {#if leadStory.published_at}
                                            <span>{leadStory.published_at}</span>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        </a>
                    {:else}
                        <div class="rounded-[2rem] border border-dashed border-slate-300 bg-white/80 p-6 text-sm text-slate-500 dark:border-white/10 dark:bg-slate-900/80 dark:text-slate-400">
                            Пока нет материалов для главного блока.
                        </div>
                    {/if}

                    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                                    В тренде
                                </div>
                                <div class="mt-1 text-xl font-semibold text-slate-950 dark:text-white">
                                    Что читают прямо сейчас
                                </div>
                            </div>
                            <a
                                href="/#/stats"
                                class="text-sm font-medium text-sky-700 transition hover:text-sky-800 dark:text-sky-300"
                            >
                                Все метрики
                            </a>
                        </div>

                        <div class="mt-5 space-y-3">
                            {#if highlightsLoading}
                                {#each Array.from({ length: 3 }) as _, index (`trending-loading-${index}`)}
                                    <div class="h-26 animate-pulse rounded-2xl bg-slate-100 dark:bg-white/5"></div>
                                {/each}
                            {:else}
                                {#each trendingPreview as article (article.id)}
                                    <ArticleCardCompact {article} />
                                {/each}
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {#if secondaryHighlights.length > 0}
            <section class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                {#each secondaryHighlights as article (article.id)}
                    <ArticleCard {article} />
                {/each}
            </section>
        {/if}

        <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="space-y-6">
                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-5">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                                Редакционная лента
                            </div>
                            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">
                                {selectedCategory?.name ?? 'Все новости'}
                            </h2>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                                Основной поток материалов с фильтрами по рубрикам, тегам, форматам
                                и календарю публикаций.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            {#each sortTabs as tab (tab.key)}
                                <button
                                    type="button"
                                    class={cn(
                                        'rounded-full px-4 py-2 text-sm font-medium transition',
                                        pageFilters.sort === tab.key
                                            ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                                    )}
                                    onclick={() => {
                                        changeSort(tab.key);
                                    }}
                                >
                                    {tab.label}
                                </button>
                            {/each}
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        {#each contentTypeOptions as option (`type-${option.value ?? 'all'}`)}
                            <button
                                type="button"
                                class={cn(
                                    'rounded-full border px-3 py-2 text-sm transition',
                                    pageFilters.content_type === option.value
                                        ? 'border-transparent bg-sky-500 text-white'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                                )}
                                onclick={() => {
                                    changeContentType(option.value);
                                }}
                            >
                                {option.label}
                            </button>
                        {/each}
                    </div>

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-[1.5rem] bg-slate-50 px-4 py-3 dark:bg-white/5">
                        <div class="text-sm text-slate-500 dark:text-slate-400">
                            Найдено <span class="font-semibold text-slate-900 dark:text-white">{totalResults}</span>
                            материалов
                        </div>

                        {#if activeFilterTotal > 0}
                            <button
                                type="button"
                                class="text-sm font-medium text-sky-700 transition hover:text-sky-800 dark:text-sky-300"
                                onclick={clearAllFilters}
                            >
                                Очистить фильтры
                            </button>
                        {/if}
                    </div>

                    <div class="mt-6">
                        {#if listState.loading}
                            <div class="grid gap-5 md:grid-cols-2">
                                {#each Array.from({ length: 6 }) as _, index (`home-loading-${index}`)}
                                    <div class="rounded-3xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
                                        <Skeleton class="h-48 w-full rounded-2xl" />
                                        <div class="mt-4 space-y-3">
                                            <Skeleton class="h-4 w-20" />
                                            <Skeleton class="h-6 w-full" />
                                            <Skeleton class="h-4 w-4/5" />
                                            <Skeleton class="h-4 w-full" />
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        {:else if listState.error}
                            <div class="rounded-[1.75rem] border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200">
                                {listState.error}
                            </div>
                        {:else if listState.articles.length === 0}
                            <div class="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center dark:border-white/10 dark:bg-white/5">
                                <div class="text-5xl">🧭</div>
                                <h3 class="mt-4 text-2xl font-semibold text-slate-950 dark:text-white">
                                    По этим условиям пока нет материалов
                                </h3>
                                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                                    Попробуйте убрать часть фильтров или открыть общую ленту.
                                </p>
                                <button
                                    type="button"
                                    class="mt-6 rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                                    onclick={clearAllFilters}
                                >
                                    Сбросить фильтры
                                </button>
                            </div>
                        {:else}
                            <div class="grid gap-5 md:grid-cols-2">
                                {#each streamArticles as article (article.id)}
                                    <ArticleCard {article} />
                                {/each}
                            </div>
                        {/if}
                    </div>

                    {#if lastPage > 1}
                        <div class="mt-8 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6 dark:border-white/10">
                            <div class="text-sm text-slate-500 dark:text-slate-400">
                                Страница {currentPage} из {lastPage}
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10"
                                    onclick={() => {
                                        changePage(currentPage - 1);
                                    }}
                                    disabled={currentPage <= 1}
                                >
                                    Назад
                                </button>

                                {#each visiblePages as pageNumber (pageNumber)}
                                    <button
                                        type="button"
                                        class={cn(
                                            'rounded-full px-4 py-2 text-sm font-medium transition',
                                            pageNumber === currentPage
                                                ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                                                : 'border border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10',
                                        )}
                                        onclick={() => {
                                            changePage(pageNumber);
                                        }}
                                    >
                                        {pageNumber}
                                    </button>
                                {/each}

                                <button
                                    type="button"
                                    class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10"
                                    onclick={() => {
                                        changePage(currentPage + 1);
                                    }}
                                    disabled={currentPage >= lastPage}
                                >
                                    Далее
                                </button>
                            </div>
                        </div>
                    {/if}
                </section>
            </section>

            <aside class="space-y-6">
                <SidebarPopularArticles />
                <SidebarCategoryTree />
                <SidebarTagCloud />
                <SidebarDateCalendar />
                <SidebarNewsletterBox />
            </aside>
        </div>
    </div>
</div>
