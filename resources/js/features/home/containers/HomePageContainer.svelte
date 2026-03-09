<script lang="ts">
    import Activity from 'lucide-svelte/icons/activity';
    import Newspaper from 'lucide-svelte/icons/newspaper';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import { onMount } from 'svelte';
    import { setSeoMeta } from '@/composables/useSeo.js';
    import {
        activeFiltersCount,
        filters,
        getArticleContentTypeFilterLabel,
        listState,
        loadArticles,
        resetFilters,
        setPage,
    } from '@/features/articles';
    import {
        HomeFeedSection,
        HomeHeroPanel,
        HomeSidebar,
    } from '@/features/home';
    import {
        absolutePublicUrl,
        AppHead,
        appBreakingNews,
        appCategories,
        articleUrl,
        bookmarksUrl,
        homeUrl,
        initApp,
        searchUrl,
        statsUrl,
    } from '@/features/portal';
    import * as api from '@/features/portal';

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
        importance_min: number | null;
        date: string | null;
        date_from: string | null;
        date_to: string | null;
        sort: string;
        search: string;
        page: number;
        per_page: number;
    };

    let featuredArticles = $state<Article[]>([]);
    let trendingArticles = $state<Article[]>([]);
    let highlightsLoading = $state(true);

    const pageFilters = $derived($filters as HomeFilters);
    const categories = $derived(($appCategories ?? []) as Category[]);
    const articles = $derived(($listState.articles ?? []) as Article[]);
    const pagination = $derived(
        ($listState.pagination ?? null) as PaginationMeta | null,
    );
    const activeFilterTotal = $derived($activeFiltersCount);
    const currentPage = $derived(
        Number(pagination?.current_page ?? pageFilters.page ?? 1),
    );
    const lastPage = $derived(Number(pagination?.last_page ?? 1));
    const totalResults = $derived(
        Number(
            pagination?.total ??
                pagination?.total_results ??
                articles.length,
        ),
    );
    const briefingDate = $derived(
        new Intl.DateTimeFormat('ru-RU', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
        }).format(new Date()),
    );
    const selectedCategory = $derived.by(() => {
        if (!pageFilters.category) {
            return null;
        }

        return (
            categories.find(
                (category) => category.slug === pageFilters.category,
            ) ?? null
        );
    });
    const articleFilters = $derived.by((): HomeFilters => ({
        category: pageFilters.category,
        sub: pageFilters.sub,
        tags: [...pageFilters.tags],
        content_type: pageFilters.content_type,
        importance_min: pageFilters.importance_min,
        date: pageFilters.date,
        date_from: pageFilters.date_from,
        date_to: pageFilters.date_to,
        sort: pageFilters.sort,
        search: pageFilters.search,
        page: pageFilters.page,
        per_page: pageFilters.per_page,
    }));

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
            chips.push(
                getArticleContentTypeFilterLabel(pageFilters.content_type),
            );
        }

        if (pageFilters.importance_min) {
            chips.push(`Важность от ${pageFilters.importance_min}`);
        }

        if (pageFilters.date_from && pageFilters.date_to) {
            chips.push(`${pageFilters.date_from} → ${pageFilters.date_to}`);
        }

        return chips;
    });

    const leadStory = $derived.by(() => {
        if (activeFilterTotal > 0) {
            return (
                (articles[0] as Article | undefined) ??
                featuredArticles[0] ??
                null
            );
        }

        return (
            featuredArticles[0] ??
            (articles[0] as Article | undefined) ??
            null
        );
    });

    const secondaryHighlights = $derived.by(() => {
        if (activeFilterTotal > 0) {
            return (articles.slice(1, 4) as Article[]).filter(
                Boolean,
            );
        }

        const preferred = featuredArticles.slice(1, 4);

        if (preferred.length > 0) {
            return preferred;
        }

        return (articles.slice(1, 4) as Article[]).filter(Boolean);
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
        articles.filter(
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
            value: $appBreakingNews.length,
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
                ? featuredResponse.value.data
                : [];
        trendingArticles =
            trendingResponse.status === 'fulfilled'
                ? trendingResponse.value.data
                : [];
        highlightsLoading = false;
    }

    function changePage(page: number): void {
        if (page < 1 || page > lastPage || page === currentPage) {
            return;
        }

        setPage(page);

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
        setSeoMeta({
            title: 'Главная',
            description:
                'Редакционная лента новостей с быстрым поиском, фильтрами по рубрикам и живой статистикой.',
            type: 'website',
            url: absolutePublicUrl(homeUrl()),
            tags: categories.slice(0, 8).map((category) => category.name),
        });
    });

    $effect(() => {
        const nextFilters = articleFilters;

        void loadArticles(nextFilters);
    });
</script>

<AppHead title="Новости">
    <meta
        name="description"
        content="Редакционная лента новостей с быстрым поиском, фильтрами по рубрикам и живой статистикой."
    />
</AppHead>

<div
    class="relative overflow-hidden bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.18),_transparent_34%),linear-gradient(to_bottom,_#f8fbff,_#eef2ff)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.16),_transparent_30%),linear-gradient(to_bottom,_#020617,_#0f172a)] sm:px-6 lg:px-8"
>
    <div
        class="pointer-events-none absolute inset-x-0 top-0 h-56 bg-[radial-gradient(circle_at_top_right,_rgba(14,165,233,0.22),_transparent_42%)]"
    ></div>
    <div
        class="pointer-events-none absolute inset-x-0 top-24 h-px bg-linear-to-r from-transparent via-sky-300/45 to-transparent dark:via-sky-700/40"
    ></div>

    <div class="relative mx-auto max-w-7xl">
        <HomeHeroPanel
            {briefingDate}
            {totalResults}
            {activeFilterTotal}
            {selectedFilters}
            {overviewStats}
            {highlightsLoading}
            {leadStory}
            {trendingPreview}
            searchHref={searchUrl()}
            statsHref={statsUrl()}
            bookmarksHref={bookmarksUrl()}
            articleHref={articleUrl}
            on:clear={clearAllFilters}
        />

        <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <HomeFeedSection
                {secondaryHighlights}
                selectedCategoryName={selectedCategory?.name ?? null}
                {pagination}
                loading={$listState.loading}
                error={$listState.error}
                {streamArticles}
                totalArticles={articles.length}
                {currentPage}
                {lastPage}
                on:clear={clearAllFilters}
                on:pagechange={(event) => {
                    changePage(event.detail);
                }}
            />

            <HomeSidebar />
        </div>
    </div>
</div>
