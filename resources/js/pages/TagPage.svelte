<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import ArticleCard from '@/components/article/ArticleCard.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import SidebarNewsletterBox from '@/components/sidebar/SidebarNewsletterBox.svelte';
    import SidebarPopularArticles from '@/components/sidebar/SidebarPopularArticles.svelte';
    import SkeletonCard from '@/components/SkeletonCard.svelte';
    import * as api from '@/lib/api';
    import { tagUrl, visitPublic } from '@/lib/publicRoutes';
    import { cn } from '@/lib/utils';
    import { appState, initApp } from '@/stores/app.svelte.js';

    type Tag = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
        description?: string | null;
        usage_count?: number | null;
        article_count?: number | null;
    };

    type Category = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
    };

    type ArticleTag = {
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
        tags?: ArticleTag[];
    };

    type PaginationMeta = {
        current_page?: number;
        last_page?: number;
        total?: number;
        total_results?: number;
    };

    const sortTabs = [
        { key: 'latest', label: 'Новые' },
        { key: 'popular', label: 'Популярные' },
        { key: 'oldest', label: 'Старые' },
    ] as const;

    let { slug }: { slug: string } = $props();

    let tag = $state<Tag | null>(null);
    let articles = $state<Article[]>([]);
    let pagination = $state<PaginationMeta | null>(null);
    let loading = $state(true);
    let dateFrom = $state<string | null>(null);
    let dateTo = $state<string | null>(null);
    let sort = $state<(typeof sortTabs)[number]['key']>('latest');
    let page = $state(1);
    let error = $state<string | null>(null);

    const relatedTags = $derived.by(() => {
        const counts: Record<string, Tag> = {};

        for (const article of articles) {
            for (const articleTag of article.tags ?? []) {
                if (articleTag.slug === slug) {
                    continue;
                }

                const existing = counts[articleTag.slug];

                counts[articleTag.slug] = {
                    id: articleTag.id,
                    name: articleTag.name,
                    slug: articleTag.slug,
                    color: articleTag.color,
                    usage_count: (existing?.usage_count ?? 0) + 1,
                };
            }
        }

        const calculated = Object.values(counts).sort(
            (left, right) => (right.usage_count ?? 0) - (left.usage_count ?? 0),
        );

        if (calculated.length > 0) {
            return calculated.slice(0, 8);
        }

        return ((appState.trendingTags ?? []) as Tag[])
            .filter((item) => item.slug !== slug)
            .slice(0, 8);
    });

    const totalResults = $derived(
        Number(
            pagination?.total ??
                pagination?.total_results ??
                tag?.article_count ??
                articles.length,
        ),
    );
    const currentPage = $derived(Number(pagination?.current_page ?? 1));
    const lastPage = $derived(Number(pagination?.last_page ?? 1));
    const heroStats = $derived([
        {
            label: 'Использований',
            value: tag?.usage_count ?? 0,
        },
        {
            label: 'Материалов',
            value: totalResults,
        },
        {
            label: 'Связанных тегов',
            value: relatedTags.length,
        },
    ]);

    function navigateToTag(nextSlug: string): void {
        visitPublic(tagUrl(nextSlug));
    }

    function changePage(nextPage: number): void {
        if (nextPage < 1 || nextPage > lastPage || nextPage === currentPage) {
            return;
        }

        page = nextPage;

        if (typeof window !== 'undefined') {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function changeSort(nextSort: (typeof sortTabs)[number]['key']): void {
        sort = nextSort;
        page = 1;
    }

    async function loadPage(currentSlug: string): Promise<void> {
        loading = true;
        error = null;

        try {
            const [tagResponse, articlesResponse] = await Promise.all([
                api.getTag(currentSlug),
                api.getArticles({
                    tag: currentSlug,
                    date_from: dateFrom,
                    date_to: dateTo,
                    sort,
                    page,
                    per_page: 20,
                }),
            ]);

            tag = tagResponse.data;
            articles = articlesResponse.data;
            pagination = articlesResponse.meta;
        } catch (loadError) {
            error =
                loadError instanceof Error
                    ? loadError.message
                    : 'Не удалось загрузить страницу тега.';
            tag = null;
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
        const currentSlug = slug;
        const currentSort = sort;
        const currentDateFrom = dateFrom;
        const currentDateTo = dateTo;
        const currentPageNumber = page;

        void currentSort;
        void currentDateFrom;
        void currentDateTo;
        void currentPageNumber;

        void loadPage(currentSlug);
    });
</script>

<AppHead title={tag ? `Тег: ${tag.name}` : 'Тег'} />

<div
    class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(107,114,128,0.16),_transparent_30%),linear-gradient(to_bottom,_#f8fafc,_#eef2ff)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(148,163,184,0.16),_transparent_30%),linear-gradient(to_bottom,_#020617,_#111827)] sm:px-6 lg:px-8"
>
    <div class="mx-auto max-w-7xl">
        <section
            class="relative overflow-hidden rounded-[2.35rem] border border-slate-200/80 bg-[linear-gradient(135deg,rgba(255,255,255,0.96),rgba(248,250,252,0.94),rgba(241,245,249,0.96))] p-6 shadow-[0_36px_110px_-60px_rgba(15,23,42,0.4)] backdrop-blur dark:border-white/10 dark:bg-[linear-gradient(135deg,rgba(15,23,42,0.92),rgba(30,41,59,0.88),rgba(15,23,42,0.92))] sm:p-8"
        >
            <div
                class="absolute right-0 top-0 h-44 w-44 rounded-full bg-slate-300/55 blur-3xl dark:bg-slate-400/10"
            ></div>
            <div
                class="absolute bottom-0 left-0 h-36 w-36 rounded-full blur-3xl dark:opacity-40"
                style={`background-color: ${tag?.color ?? '#6B7280'}33`}
            ></div>
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div class="relative max-w-3xl">
                    <div
                        class="inline-flex items-center gap-3 rounded-full border border-black/5 px-4 py-2 text-sm font-semibold text-white shadow-sm"
                        style={`background-color: ${tag?.color ?? '#6B7280'}`}
                    >
                        #{tag?.name ?? 'tag'}
                    </div>
                    <h1
                        class="mt-5 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-4xl"
                    >
                        Тег: {tag?.name ?? 'Загрузка...'}
                    </h1>
                    {#if tag?.description}
                        <p
                            class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base"
                        >
                            {tag.description}
                        </p>
                    {/if}

                    {#if relatedTags.length > 0}
                        <div class="mt-5 flex flex-wrap gap-2">
                            {#each relatedTags.slice(0, 4) as relatedTag (relatedTag.slug)}
                                <button
                                    type="button"
                                    class="rounded-full border border-slate-200/80 bg-white/80 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                                    onclick={() => {
                                        navigateToTag(relatedTag.slug);
                                    }}
                                >
                                    #{relatedTag.name}
                                </button>
                            {/each}
                        </div>
                    {/if}
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    {#each heroStats as stat (stat.label)}
                        <div
                            class="rounded-[1.6rem] border border-slate-200/80 bg-white/75 px-5 py-4 dark:border-white/10 dark:bg-white/5"
                        >
                            <div
                                class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400"
                            >
                                {stat.label}
                            </div>
                            <div
                                class="mt-2 text-3xl font-semibold text-slate-950 dark:text-white"
                            >
                                {stat.value}
                            </div>
                        </div>
                    {/each}
                </div>
            </div>
        </section>

        <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="space-y-6">
                <div
                    class="rounded-[1.85rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.97),rgba(248,250,252,0.94))] p-5 shadow-[0_24px_80px_-60px_rgba(15,23,42,0.45)] dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.82))]"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <div>
                            <div
                                class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                            >
                                Материалы по тегу
                            </div>
                            <div
                                class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white"
                            >
                                Найдено: {totalResults}
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            {#each sortTabs as tab (tab.key)}
                                <button
                                    type="button"
                                    class={cn(
                                        'rounded-full px-4 py-2 text-sm font-medium transition',
                                        sort === tab.key
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

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <label class="space-y-2">
                            <span
                                class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                            >
                                С даты
                            </span>
                            <input
                                type="date"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-sky-300 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                                value={dateFrom ?? ''}
                                onchange={(event) => {
                                    const target =
                                        event.currentTarget as HTMLInputElement;

                                    dateFrom = target.value || null;
                                    page = 1;
                                }}
                            />
                        </label>

                        <label class="space-y-2">
                            <span
                                class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                            >
                                По дату
                            </span>
                            <input
                                type="date"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-sky-300 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                                value={dateTo ?? ''}
                                onchange={(event) => {
                                    const target =
                                        event.currentTarget as HTMLInputElement;

                                    dateTo = target.value || null;
                                    page = 1;
                                }}
                            />
                        </label>
                    </div>
                </div>

                {#if loading}
                    <div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
                        {#each Array.from({ length: 6 }) as _, index (index)}
                            <SkeletonCard />
                        {/each}
                    </div>
                {:else if error}
                    <div
                        class="rounded-[2rem] border border-dashed border-rose-300 bg-white p-8 text-center text-rose-600 dark:border-rose-500/30 dark:bg-slate-900 dark:text-rose-300"
                    >
                        {error}
                    </div>
                {:else if articles.length === 0}
                    <div
                        class="rounded-[2rem] border border-dashed border-slate-300 bg-white p-8 text-center dark:border-white/10 dark:bg-slate-900"
                    >
                        <h2
                            class="text-2xl font-semibold text-slate-950 dark:text-white"
                        >
                            По этому тегу пока нет материалов
                        </h2>
                        <p
                            class="mt-3 text-sm text-slate-500 dark:text-slate-400"
                        >
                            Попробуйте поменять сортировку или выбрать соседний
                            тег.
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

            <aside class="space-y-5">
                <section
                    class="rounded-[1.85rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.94))] p-5 shadow-[0_24px_80px_-60px_rgba(15,23,42,0.45)] dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.82))]"
                >
                    <div
                        class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                    >
                        Похожие теги
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        {#if relatedTags.length > 0}
                            {#each relatedTags as relatedTag (relatedTag.slug)}
                                <button
                                    type="button"
                                    class="rounded-full px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:opacity-90"
                                    style={`background-color: ${relatedTag.color ?? '#6B7280'}`}
                                    onclick={() => {
                                        navigateToTag(relatedTag.slug);
                                    }}
                                >
                                    #{relatedTag.name}
                                </button>
                            {/each}
                        {:else}
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                Связанные теги появятся после загрузки
                                материалов.
                            </p>
                        {/if}
                    </div>
                </section>

                <SidebarPopularArticles />
                <SidebarNewsletterBox />
            </aside>
        </div>
    </div>
</div>
