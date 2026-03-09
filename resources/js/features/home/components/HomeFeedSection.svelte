<script lang="ts">
    import { createEventDispatcher } from 'svelte';
    import {
        ArticleCard,
        FilterBar,
        Pagination,
        SkeletonCard,
    } from '@/features/articles';

    const dispatch = createEventDispatcher<{
        clear: null;
        pagechange: number;
    }>();

    type Category = {
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
        tags?: Array<{
            id: number | string;
            name: string;
            slug: string;
        }>;
    };

    type PaginationMeta = {
        current_page?: number;
        last_page?: number;
        total?: number;
        total_results?: number;
    } | null;

    let {
        secondaryHighlights,
        selectedCategoryName,
        pagination,
        loading,
        error,
        streamArticles,
        totalArticles,
        currentPage,
        lastPage,
    }: {
        secondaryHighlights: Article[];
        selectedCategoryName: string | null;
        pagination: PaginationMeta;
        loading: boolean;
        error: string | null;
        streamArticles: Article[];
        totalArticles: number;
        currentPage: number;
        lastPage: number;
    } = $props();
</script>

{#if secondaryHighlights.length > 0}
    <section class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        {#each secondaryHighlights as article (article.id)}
            <ArticleCard {article} />
        {/each}
    </section>
{/if}

<section class="space-y-6">
    <div>
        <div
            class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400"
        >
            Редакционная лента
        </div>
        <h2
            class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
        >
            {selectedCategoryName ?? 'Все новости'}
        </h2>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">
            Основной поток материалов с фильтрами по рубрикам, тегам, форматам
            и календарю публикаций.
        </p>
    </div>

    <FilterBar {pagination} />

    <div>
        {#if loading}
            <div class="grid gap-5 md:grid-cols-2">
                {#each Array.from({ length: 6 }) as _, index (`home-loading-${index}`)}
                    <SkeletonCard
                        lineWidths={['w-20', 'w-full', 'w-4/5', 'w-full']}
                    />
                {/each}
            </div>
        {:else if error}
            <div
                class="rounded-[1.75rem] border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200"
            >
                {error}
            </div>
        {:else if streamArticles.length === 0}
            <div
                class="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center dark:border-white/10 dark:bg-white/5"
            >
                <div class="text-5xl">🧭</div>
                <h3
                    class="mt-4 text-2xl font-semibold text-slate-950 dark:text-white"
                >
                    По этим условиям пока нет материалов
                </h3>
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                    Попробуйте убрать часть фильтров или открыть общую ленту.
                </p>
                <button
                    type="button"
                    class="mt-6 rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                    onclick={() => {
                        dispatch('clear', null);
                    }}
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

            {#if totalArticles > 50}
                <div
                    class="mt-5 rounded-[1.5rem] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100"
                >
                    Показываем первые 50. Используйте фильтры для уточнения.
                </div>
            {/if}
        {/if}
    </div>

    <Pagination
        {currentPage}
        {lastPage}
        onChange={(page) => {
            dispatch('pagechange', page);
        }}
        label={`Страница ${currentPage} из ${lastPage}`}
        nextLabel="Далее"
    />
</section>
