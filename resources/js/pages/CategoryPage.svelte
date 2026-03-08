<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import ArticleCard from '@/components/article/ArticleCard.svelte';
    import ArticleCardCompact from '@/components/article/ArticleCardCompact.svelte';
    import FilterBar from '@/components/FilterBar.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import SidebarCategoryTree from '@/components/sidebar/SidebarCategoryTree.svelte';
    import SidebarDateCalendar from '@/components/sidebar/SidebarDateCalendar.svelte';
    import SidebarNewsletterBox from '@/components/sidebar/SidebarNewsletterBox.svelte';
    import SidebarPopularArticles from '@/components/sidebar/SidebarPopularArticles.svelte';
    import SidebarTagCloud from '@/components/sidebar/SidebarTagCloud.svelte';
    import SkeletonCard from '@/components/SkeletonCard.svelte';
    import { setSeoMeta } from '@/composables/useSeo.js';
    import * as api from '@/lib/api';
    import { cn } from '@/lib/utils';
    import { initApp } from '@/stores/app.svelte.js';
    import { filters } from '@/stores/articles.svelte.js';

    type SubCategory = {
        id: number | string;
        name: string;
        slug: string;
    };

    type Category = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
        description?: string | null;
        articles_count_cache?: number | null;
        sub_categories?: SubCategory[];
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

    type PageFilters = {
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

    let { slug }: { slug: string } = $props();

    let category = $state<Category | null>(null);
    let articles = $state<Article[]>([]);
    let pinnedArticles = $state<Article[]>([]);
    let loading = $state(true);
    let pagination = $state<PaginationMeta | null>(null);
    let error = $state<string | null>(null);

    const pageFilters = filters as PageFilters;
    const totalResults = $derived(
        Number(
            pagination?.total ??
                pagination?.total_results ??
                category?.articles_count_cache ??
                articles.length,
        ),
    );
    const currentPage = $derived(Number(pagination?.current_page ?? 1));
    const lastPage = $derived(Number(pagination?.last_page ?? 1));
    function navigateToCategory(nextSlug: string | null): void {
        if (typeof window === 'undefined') {
            return;
        }

        if (!nextSlug) {
            window.location.hash = '/';

            return;
        }

        window.location.hash = `/category/${nextSlug}`;
    }

    function applySubCategory(nextSlug: string | null): void {
        pageFilters.sub = nextSlug;
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

    async function loadPage(currentSlug: string): Promise<void> {
        loading = true;
        error = null;

        try {
            const [categoryResponse, pinnedResponse, articlesResponse] =
                await Promise.all([
                    api.getCategory(currentSlug),
                    api.getPinnedArticles(currentSlug).catch(() => ({
                        data: { data: [] },
                    })),
                    api.getArticles({
                        category: currentSlug,
                        sub: pageFilters.sub,
                        tags: pageFilters.tags,
                        content_type: pageFilters.content_type,
                        importance_min: pageFilters.importance_min,
                        date: pageFilters.date,
                        date_from: pageFilters.date_from,
                        date_to: pageFilters.date_to,
                        search: pageFilters.search,
                        sort: pageFilters.sort,
                        page: pageFilters.page,
                        per_page: pageFilters.per_page,
                    }),
                ]);

            category = categoryResponse.data?.data ?? null;
            pinnedArticles = pinnedResponse.data?.data ?? [];
            articles = articlesResponse.data?.data ?? [];
            pagination = articlesResponse.data?.meta ?? null;
        } catch (loadError) {
            error =
                loadError instanceof Error
                    ? loadError.message
                    : 'Не удалось загрузить категорию.';
            category = null;
            pinnedArticles = [];
            articles = [];
            pagination = null;
        } finally {
            loading = false;
        }
    }

    $effect(() => {
        void initApp();
    });

    $effect(() => {
        setSeoMeta({
            title: category ? category.name : 'Категория',
            description:
                category?.description ||
                `Свежая лента материалов раздела ${category?.name ?? 'новостей'}.`,
            type: 'website',
            url:
                typeof window !== 'undefined'
                    ? `${window.location.origin}/#/category/${slug}`
                    : undefined,
            tags: category ? [category.name] : [],
        });
    });

    $effect(() => {
        pageFilters.category = slug;
        pageFilters.page = 1;
    });

    $effect(() => {
        const activeCategory = pageFilters.category;

        if (activeCategory === slug) {
            return;
        }

        navigateToCategory(activeCategory);
    });

    $effect(() => {
        const currentSlug = slug;
        const activeSub = pageFilters.sub;
        const activeTags = pageFilters.tags.join(',');
        const activeContentType = pageFilters.content_type;
        const activeImportance = pageFilters.importance_min;
        const activeDate = pageFilters.date;
        const activeDateFrom = pageFilters.date_from;
        const activeDateTo = pageFilters.date_to;
        const activeSort = pageFilters.sort;
        const activeSearch = pageFilters.search;
        const activePage = pageFilters.page;
        const activePerPage = pageFilters.per_page;

        void activeSub;
        void activeTags;
        void activeContentType;
        void activeImportance;
        void activeDate;
        void activeDateFrom;
        void activeDateTo;
        void activeSort;
        void activeSearch;
        void activePage;
        void activePerPage;

        void loadPage(currentSlug);
    });
</script>

<AppHead title={category ? category.name : 'Категория'} />

<div class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.16),_transparent_30%),linear-gradient(to_bottom,_#f8fbff,_#f1f5f9)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.18),_transparent_30%),linear-gradient(to_bottom,_#020617,_#111827)] sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <section
            class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/90 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.35)] backdrop-blur dark:border-white/10 dark:bg-slate-950/80"
        >
            <div
                class="px-6 py-8 sm:px-8"
                style={`background: linear-gradient(135deg, ${category?.color ?? '#2563EB'} 0%, rgba(15, 23, 42, 0.92) 100%);`}
            >
                <div class="flex flex-wrap items-end justify-between gap-6">
                    <div class="max-w-3xl">
                        <div class="text-sm font-semibold tracking-[0.24em] text-white/70 uppercase">
                            Раздел портала
                        </div>
                        <div class="mt-4 flex items-center gap-4">
                            <div class="flex size-16 items-center justify-center rounded-3xl bg-white/12 text-4xl text-white backdrop-blur">
                                {category?.icon ?? '📰'}
                            </div>
                            <div>
                                <h1 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                                    {category?.name ?? 'Загрузка...'}
                                </h1>
                                <p class="mt-2 text-sm text-white/75 sm:text-base">
                                    {category?.description ||
                                        'Свежая лента материалов по выбранной рубрике.'}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[1.75rem] border border-white/10 bg-white/8 px-5 py-4 text-white backdrop-blur">
                        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-white/60">
                            Материалов в разделе
                        </div>
                        <div class="mt-2 text-3xl font-semibold">{totalResults}</div>
                    </div>
                </div>
            </div>

            {#if category?.sub_categories?.length}
                <div class="border-t border-slate-200/70 bg-white px-6 py-4 dark:border-white/10 dark:bg-slate-950 sm:px-8">
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class={cn(
                                'rounded-full px-4 py-2 text-sm font-medium transition',
                                !pageFilters.sub
                                    ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                            )}
                            onclick={() => {
                                applySubCategory(null);
                            }}
                        >
                            Все материалы
                        </button>

                        {#each category.sub_categories as subCategory (subCategory.id)}
                            <button
                                type="button"
                                class={cn(
                                    'rounded-full px-4 py-2 text-sm font-medium transition',
                                    pageFilters.sub === subCategory.slug
                                        ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                                )}
                                onclick={() => {
                                    applySubCategory(subCategory.slug);
                                }}
                            >
                                {subCategory.name}
                            </button>
                        {/each}
                    </div>
                </div>
            {/if}
        </section>

        {#if pinnedArticles.length > 0}
            <section class="mt-8 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 dark:text-sky-300">
                            Закреплённое
                        </div>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">
                            Важное в разделе {category?.name}
                        </h2>
                    </div>
                    <div class="rounded-full bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700 dark:bg-sky-950/40 dark:text-sky-300">
                        {pinnedArticles.length} статьи
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {#each pinnedArticles as article (article.id)}
                        <div class="rounded-3xl bg-slate-50 p-2 dark:bg-white/5">
                            <ArticleCardCompact {article} />
                        </div>
                    {/each}
                </div>
            </section>
        {/if}

        <div class="mt-8 grid gap-8 xl:grid-cols-[18rem_minmax(0,1fr)]">
            <aside class="space-y-5">
                <SidebarCategoryTree />
                <SidebarDateCalendar />
                <SidebarTagCloud />
                <SidebarPopularArticles />
                <SidebarNewsletterBox />
            </aside>

            <section class="space-y-6">
                <FilterBar pagination={pagination} />

                {#if loading}
                    <div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
                        {#each Array.from({ length: 6 }) as _, index (index)}
                            <SkeletonCard />
                        {/each}
                    </div>
                {:else if error}
                    <div class="rounded-[2rem] border border-dashed border-rose-300 bg-white p-8 text-center text-rose-600 dark:border-rose-500/30 dark:bg-slate-900 dark:text-rose-300">
                        {error}
                    </div>
                {:else if articles.length === 0}
                    <div class="rounded-[2rem] border border-dashed border-slate-300 bg-white p-8 text-center dark:border-white/10 dark:bg-slate-900">
                        <h3 class="text-2xl font-semibold text-slate-950 dark:text-white">
                            В этом разделе пока ничего нет
                        </h3>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                            Попробуйте снять часть фильтров или открыть соседнюю подкатегорию.
                        </p>
                    </div>
                {:else}
                    <div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
                        {#each articles as article (article.id)}
                            <ArticleCard {article} />
                        {/each}
                    </div>
                {/if}

                <Pagination {currentPage} {lastPage} onChange={changePage} />
            </section>
        </div>
    </div>
</div>
