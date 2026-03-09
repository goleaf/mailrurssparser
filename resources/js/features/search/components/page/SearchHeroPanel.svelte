<script lang="ts">
    import Search from 'lucide-svelte/icons/search';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import X from 'lucide-svelte/icons/x';
    import { createEventDispatcher } from 'svelte';
    import type { SearchSuggestions } from '@/features/search';
    import { emptySearchSuggestions } from '@/features/search';
    import { SearchAutocompletePanel } from '@/features/search';
    import { cn } from '@/lib/utils';

    type Category = {
        id: number | string;
        name: string;
        slug: string;
    };

    type SearchSnapshot = {
        label: string;
        value: string | number;
        caption: string;
    };

    type SearchSortKey = 'relevance' | 'latest' | 'popular';
    const dispatch = createEventDispatcher<{
        queryinput: string;
        querykeydown: KeyboardEvent;
        clear: null;
        search: string;
        categorychange: string | null;
        contenttypechange: string | null;
        datechange: { field: 'date_from' | 'date_to'; value: string | null };
        sortchange: SearchSortKey;
        articleselect: string;
        categoryselect: string;
        tagselect: string;
    }>();

    /**
     * Public prop contract for the search hero.
     * The component is presentational: it renders the current state and forwards
     * user intent upward through callbacks.
     */
    interface Props {
        /** Current search query shown in the input. */
        query: string;
        /** Available categories for the category filter. */
        categories: Category[];
        /** Active category slug filter. */
        selectedCategory: string | null;
        /** Active content type filter. */
        selectedContentType: string | null;
        /** Active lower date boundary in YYYY-MM-DD format. */
        selectedDateFrom: string | null;
        /** Active upper date boundary in YYYY-MM-DD format. */
        selectedDateTo: string | null;
        /** Active search sorting mode. */
        selectedSort: SearchSortKey;
        /** Available content type filter options. */
        contentTypeOptions: ReadonlyArray<{ value: string; label: string }>;
        /** Available sort tabs displayed under the filters. */
        sortTabs: ReadonlyArray<{ key: SearchSortKey; label: string }>;
        /** Search summary cards rendered in the hero sidebar. */
        searchSnapshots: SearchSnapshot[];
        /** Structured autocomplete suggestions for the current query. */
        suggestions: SearchSuggestions;
        /** Whether autocomplete data is loading. */
        suggestionsLoading: boolean;
        /** Index of the currently keyboard-highlighted suggestion. */
        activeSuggestionIndex: number;
    }

    let {
        query = '',
        categories = [],
        selectedCategory = null,
        selectedContentType = null,
        selectedDateFrom = null,
        selectedDateTo = null,
        selectedSort = 'relevance',
        contentTypeOptions = [],
        sortTabs = [],
        searchSnapshots = [],
        suggestions = { ...emptySearchSuggestions },
        suggestionsLoading = false,
        activeSuggestionIndex = -1,
    }: Props = $props();
</script>

<section
    class="relative overflow-hidden rounded-[2.35rem] border border-slate-200/80 bg-[linear-gradient(135deg,rgba(255,255,255,0.96),rgba(248,250,252,0.94),rgba(239,246,255,0.96))] p-6 shadow-[0_36px_110px_-60px_rgba(15,23,42,0.44)] backdrop-blur dark:border-white/10 dark:bg-[linear-gradient(135deg,rgba(15,23,42,0.94),rgba(15,23,42,0.88),rgba(8,47,73,0.88))] sm:p-8"
>
    <div
        class="absolute right-0 top-0 h-48 w-48 rounded-full bg-sky-200/60 blur-3xl dark:bg-sky-500/20"
    ></div>
    <div
        class="absolute bottom-0 left-0 h-36 w-36 rounded-full bg-cyan-200/55 blur-3xl dark:bg-cyan-500/10"
    ></div>
    <div
        class="grid gap-8 lg:grid-cols-[minmax(0,1.25fr)_19rem] lg:items-start"
    >
        <div class="max-w-3xl">
            <div
                class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-4 py-2 text-xs font-semibold tracking-[0.24em] text-sky-700 uppercase dark:border-sky-900/60 dark:bg-sky-950/50 dark:text-sky-300"
            >
                <Sparkles class="size-4" />
                Поиск по порталу
            </div>

            <h1
                class="mt-5 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-4xl"
            >
                Найдите новости, рубрики и темы за секунды
            </h1>

            <p
                class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base"
            >
                Ищите по заголовкам, описаниям, авторам и полному тексту.
                Фильтруйте выдачу по рубрике, формату и датам, не выходя из
                страницы.
            </p>
        </div>

        <div
            class="rounded-[1.9rem] border border-slate-200/80 bg-white/75 p-5 shadow-sm dark:border-white/10 dark:bg-white/5"
        >
            <div
                class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400"
            >
                Пульс поиска
            </div>
            <div class="mt-4 space-y-3">
                {#each searchSnapshots as snapshot (snapshot.label)}
                    <div
                        class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:border-white/10 dark:bg-black/10"
                    >
                        <div
                            class="text-[0.7rem] font-semibold uppercase tracking-[0.2em] text-slate-400"
                        >
                            {snapshot.label}
                        </div>
                        <div
                            class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
                        >
                            {snapshot.value}
                        </div>
                        <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {snapshot.caption}
                        </div>
                    </div>
                {/each}
            </div>
        </div>
    </div>

    <div class="mt-8 space-y-5">
        <div class="rounded-[1.75rem] bg-slate-100 p-2 dark:bg-white/5">
            <div
                class="rounded-[1.25rem] bg-white px-4 py-3 shadow-sm dark:bg-slate-900"
            >
                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                    <Search class="size-5 shrink-0 text-slate-400" />
                    <input
                        value={query}
                        type="text"
                        class="min-w-0 flex-1 bg-transparent text-base text-slate-900 outline-none placeholder:text-slate-400 dark:text-white"
                        placeholder="Например: санкции, спорт, интервью"
                        oninput={(event) => {
                            dispatch(
                                'queryinput',
                                (event.currentTarget as HTMLInputElement).value,
                            );
                        }}
                        onkeydown={(event) => {
                            dispatch('querykeydown', event);
                        }}
                    />
                    {#if query}
                        <button
                            type="button"
                            class="inline-flex size-10 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-white"
                            onclick={() => {
                                dispatch('clear', null);
                            }}
                            aria-label="Очистить поиск"
                        >
                            <X class="size-4" />
                        </button>
                    {/if}
                    <button
                        type="button"
                        class="rounded-full bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        onclick={() => {
                            dispatch('search', query);
                        }}
                    >
                        Искать
                    </button>
                </div>

                <SearchAutocompletePanel
                    {query}
                    {suggestions}
                    loading={suggestionsLoading}
                    activeIndex={activeSuggestionIndex}
                    onSearchSubmit={(nextQuery) => {
                        dispatch('search', nextQuery);
                    }}
                    onArticleSelect={(slug) => {
                        dispatch('articleselect', slug);
                    }}
                    onCategorySelect={(slug) => {
                        dispatch('categoryselect', slug);
                    }}
                    onTagSelect={(slug) => {
                        dispatch('tagselect', slug);
                    }}
                />
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-[1.2fr_1fr_1fr_1fr]">
            <label class="space-y-2">
                <span
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                >
                    Рубрика
                </span>
                <select
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-sky-300 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200"
                    value={selectedCategory ?? ''}
                    onchange={(event) => {
                        dispatch(
                            'categorychange',
                            (event.currentTarget as HTMLSelectElement).value ||
                                null,
                        );
                    }}
                >
                    <option value="">Все категории</option>
                    {#each categories as category (category.id)}
                        <option value={category.slug}>{category.name}</option>
                    {/each}
                </select>
            </label>

            <label class="space-y-2">
                <span
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                >
                    Формат
                </span>
                <select
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-sky-300 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200"
                    value={selectedContentType ?? ''}
                    onchange={(event) => {
                        dispatch(
                            'contenttypechange',
                            (event.currentTarget as HTMLSelectElement).value ||
                                null,
                        );
                    }}
                >
                    {#each contentTypeOptions as option (option.value)}
                        <option value={option.value}>{option.label}</option>
                    {/each}
                </select>
            </label>

            <label class="space-y-2">
                <span
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                >
                    С даты
                </span>
                <input
                    type="date"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-sky-300 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200"
                    value={selectedDateFrom ?? ''}
                    onchange={(event) => {
                        dispatch('datechange', {
                            field: 'date_from',
                            value:
                                (event.currentTarget as HTMLInputElement)
                                    .value || null,
                        });
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
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-sky-300 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200"
                    value={selectedDateTo ?? ''}
                    onchange={(event) => {
                        dispatch('datechange', {
                            field: 'date_to',
                            value:
                                (event.currentTarget as HTMLInputElement)
                                    .value || null,
                        });
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
                        selectedSort === tab.key
                            ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                    )}
                    onclick={() => {
                        dispatch('sortchange', tab.key);
                    }}
                >
                    {tab.label}
                </button>
            {/each}
        </div>
    </div>
</section>
