<script lang="ts">
    import { createEventDispatcher } from 'svelte';
    import {
        ArticleCard,
        Pagination,
        SkeletonCard,
    } from '@/features/articles';

    const dispatch = createEventDispatcher<{
        pagechange: number;
        categoryselect: string;
        tagselect: string;
    }>();

    type Category = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
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

    type SearchSuggestionItem = {
        type: 'category' | 'tag';
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
    };

    let {
        activeQuery,
        totalResults,
        loading,
        results,
        emptyStateSuggestions,
        currentPage,
        lastPage,
    }: {
        activeQuery: string;
        totalResults: number;
        loading: boolean;
        results: Article[];
        emptyStateSuggestions: SearchSuggestionItem[];
        currentPage: number;
        lastPage: number;
    } = $props();

    function handlePageChange(page: number): void {
        dispatch('pagechange', page);
    }

    function handleEmptySuggestionClick(event: Event): void {
        const button = event.currentTarget as HTMLButtonElement;
        const suggestionType = button.dataset.suggestionType;
        const suggestionSlug = button.dataset.suggestionSlug;

        if (!suggestionType || !suggestionSlug) {
            return;
        }

        if (suggestionType === 'category') {
            dispatch('categoryselect', suggestionSlug);

            return;
        }

        dispatch('tagselect', suggestionSlug);
    }
</script>

<section class="space-y-6">
    {#if activeQuery}
        <div
            class="rounded-[1.85rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(248,250,252,0.94))] p-5 shadow-[0_24px_80px_-60px_rgba(15,23,42,0.45)] dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.82))]"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div
                        class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                    >
                        Результаты поиска
                    </div>
                    <h2
                        class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white"
                    >
                        Найдено: {totalResults} результатов
                    </h2>
                </div>
                <div
                    class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300"
                >
                    Запрос: <span class="font-semibold text-slate-900 dark:text-white"
                        >{activeQuery}</span
                    >
                </div>
            </div>
        </div>
    {/if}

    {#if loading}
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            {#each Array.from({ length: 6 }) as _, index (index)}
                <SkeletonCard
                    lineWidths={['w-24', 'w-full', 'w-4/5', 'w-full', 'w-3/4']}
                />
            {/each}
        </div>
    {:else if activeQuery && results.length === 0}
        <div
            class="rounded-[2rem] border border-dashed border-slate-300 bg-white p-8 text-center dark:border-white/10 dark:bg-slate-900"
        >
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
                            data-suggestion-type={suggestion.type}
                            data-suggestion-slug={suggestion.slug}
                            onclick={handleEmptySuggestionClick}
                        >
                            <span
                                class="size-2 rounded-full"
                                style={`background-color: ${suggestion.color ?? '#2563EB'};`}
                            ></span>
                            {suggestion.type === 'category' ? 'Рубрика' : 'Тег'}:
                            {suggestion.name}
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
        <div
            class="rounded-[2rem] border border-dashed border-slate-300 bg-white p-8 text-center dark:border-white/10 dark:bg-slate-900"
        >
            <h3 class="text-2xl font-semibold text-slate-950 dark:text-white">
                Начните поиск
            </h3>
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                Введите минимум два символа, чтобы получить результаты и
                подсказки.
            </p>
        </div>
    {/if}

    <Pagination
        {currentPage}
        {lastPage}
        onChange={handlePageChange}
    />
</section>
