<script lang="ts">
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import Search from 'lucide-svelte/icons/search';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import X from 'lucide-svelte/icons/x';
    import { slide } from 'svelte/transition';
    import { filterBarArticleContentTypeOptions } from '@/lib/articleEnums';
    import { cn, debounce } from '@/lib/utils';
    import { appState } from '@/stores/app.svelte.js';
    import {
        activeFiltersCount,
        clearDate,
        filters,
        resetFilters,
        setContentType,
        setDate,
        setDateRange,
        setImportance,
        setSearch,
        setSort,
        toggleTag,
    } from '@/stores/articles.svelte.js';

    type Tag = {
        id: number | string;
        name: string;
        slug: string;
        usage_count?: number | null;
    };

    type PaginationMeta = {
        total?: number;
        total_results?: number;
    } | null;

    const sortTabs = [
        { key: 'latest', label: 'Новые' },
        { key: 'popular', label: 'Популярные' },
        { key: 'trending', label: 'Тренды' },
        { key: 'importance', label: 'Важность' },
    ] as const;

    const contentTypes = filterBarArticleContentTypeOptions;

    let { pagination = null }: { pagination?: PaginationMeta } = $props();

    let showAdvanced = $state(false);
    let searchValue = $state(filters.search ?? '');
    let tagQuery = $state('');
    let activeDatePreset = $state<string | null>(null);

    const totalResults = $derived(
        Number(pagination?.total ?? pagination?.total_results ?? 0),
    );
    const activeCount = $derived(activeFiltersCount());
    const selectedTags = $derived(filters.tags as string[]);
    const trendingTags = $derived((appState.trendingTags ?? []) as Tag[]);
    const filteredTagSuggestions = $derived.by(() => {
        const normalizedQuery = tagQuery.trim().toLowerCase();

        return trendingTags
            .filter((tag) => !selectedTags.includes(tag.slug))
            .filter((tag) => {
                if (normalizedQuery === '') {
                    return true;
                }

                return (
                    tag.name.toLowerCase().includes(normalizedQuery) ||
                    tag.slug.toLowerCase().includes(normalizedQuery)
                );
            })
            .slice(0, 8);
    });

    const syncSearch = debounce((value: string) => {
        setSearch(value.trim());
    }, 400);

    function applyQuickRange(preset: string): void {
        activeDatePreset = activeDatePreset === preset ? null : preset;

        if (activeDatePreset === null) {
            clearDate();

            return;
        }

        const currentTime = Date.now();
        const toIsoDate = (value: number): string =>
            new Date(value).toLocaleDateString('en-CA');

        if (preset === 'today') {
            setDate(toIsoDate(currentTime));

            return;
        }

        if (preset === 'yesterday') {
            setDate(toIsoDate(currentTime - 24 * 60 * 60 * 1000));

            return;
        }

        if (preset === 'week') {
            setDateRange(
                toIsoDate(currentTime - 6 * 24 * 60 * 60 * 1000),
                toIsoDate(currentTime),
            );

            return;
        }

        if (preset === 'month') {
            const current = new Date(currentTime);
            const startOfMonthTime = Date.UTC(
                current.getUTCFullYear(),
                current.getUTCMonth(),
                1,
            );

            setDateRange(toIsoDate(startOfMonthTime), toIsoDate(currentTime));

            return;
        }
    }

    function clearAll(): void {
        activeDatePreset = null;
        searchValue = '';
        tagQuery = '';
        resetFilters();
    }

    function addTag(slug: string): void {
        if (!selectedTags.includes(slug)) {
            toggleTag(slug);
        }

        tagQuery = '';
    }

    $effect(() => {
        const externalSearch = filters.search ?? '';

        if (externalSearch !== searchValue) {
            searchValue = externalSearch;
        }
    });
</script>

<section class="rounded-[2rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(248,250,252,0.9))] p-5 shadow-[0_25px_80px_-60px_rgba(15,23,42,0.55)] dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.82))]">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
        <label class="relative min-w-0 flex-1">
            <Search class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
            <input
                bind:value={searchValue}
                type="search"
                class="w-full rounded-full border border-slate-200 bg-white/90 py-3 pl-11 pr-12 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:bg-white dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-slate-500 dark:focus:bg-white/8"
                placeholder="Поиск по заголовкам и описанию"
                oninput={(event) => {
                    syncSearch((event.currentTarget as HTMLInputElement).value);
                }}
            />

            {#if searchValue}
                <button
                    type="button"
                    class="absolute right-3 top-1/2 inline-flex size-8 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-white"
                    onclick={() => {
                        searchValue = '';
                        setSearch('');
                    }}
                    aria-label="Очистить поиск"
                >
                    <X class="size-4" />
                </button>
            {/if}
        </label>

        <div class="flex flex-wrap gap-2">
            {#each sortTabs as tab (tab.key)}
                <button
                    type="button"
                    class={cn(
                        'rounded-full px-4 py-2 text-sm font-medium transition',
                        filters.sort === tab.key
                            ? 'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-950'
                            : 'bg-slate-100/90 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                    )}
                    onclick={() => {
                        setSort(tab.key);
                    }}
                >
                    {tab.label}
                </button>
            {/each}
        </div>

        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 bg-white/85 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
            aria-expanded={showAdvanced}
            onclick={() => {
                showAdvanced = !showAdvanced;
            }}
        >
            <SlidersHorizontal class="size-4" />
            Фильтры
            {#if activeCount > 0}
                <span class="rounded-full bg-sky-500 px-2 py-0.5 text-xs font-semibold text-white">
                    {activeCount}
                </span>
            {/if}
        </button>
    </div>

    {#if showAdvanced}
        <div class="mt-5 space-y-5 rounded-[1.75rem] border border-slate-200/80 bg-white/70 p-4 dark:border-white/10 dark:bg-black/10" transition:slide>
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                    Формат
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    {#each contentTypes as type (`type-${type.value ?? 'all'}`)}
                        <button
                            type="button"
                            class={cn(
                                'rounded-full border px-3 py-2 text-sm transition',
                                filters.content_type === type.value
                                    ? 'border-transparent bg-sky-500 text-white shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                            )}
                            onclick={() => {
                                setContentType(type.value);
                            }}
                        >
                            {type.label}
                        </button>
                    {/each}
                </div>
            </div>

            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                    Важность от
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    {#each Array.from({ length: 10 }, (_, index) => index + 1) as value (value)}
                        <button
                            type="button"
                            class={cn(
                                'inline-flex size-10 items-center justify-center rounded-full border text-sm font-semibold transition',
                                filters.importance_min === value
                                    ? 'border-transparent bg-amber-400 text-slate-950 shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                            )}
                            onclick={() => {
                                setImportance(
                                    filters.importance_min === value ? null : value,
                                );
                            }}
                        >
                            {value}
                        </button>
                    {/each}
                </div>
            </div>

            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                    <CalendarDays class="size-4" />
                    Период
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    {#each [
                        { key: 'today', label: 'Сегодня' },
                        { key: 'yesterday', label: 'Вчера' },
                        { key: 'week', label: 'Эта неделя' },
                        { key: 'month', label: 'Этот месяц' },
                        { key: 'custom', label: 'Произвольный' },
                    ] as preset (preset.key)}
                        <button
                            type="button"
                            class={cn(
                                'rounded-full border px-3 py-2 text-sm transition',
                                activeDatePreset === preset.key
                                    ? 'border-transparent bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-950'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                            )}
                            onclick={() => {
                                if (preset.key === 'custom') {
                                    activeDatePreset =
                                        activeDatePreset === 'custom' ? null : 'custom';

                                    if (activeDatePreset === null) {
                                        clearDate();
                                    }

                                    return;
                                }

                                applyQuickRange(preset.key);
                            }}
                        >
                            {preset.label}
                        </button>
                    {/each}
                </div>

                {#if activeDatePreset === 'custom'}
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <input
                            bind:value={filters.date_from}
                            type="date"
                            class="scheme-light-dark rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:bg-white dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:bg-white/8"
                            oninput={() => {
                                if (filters.date_from && filters.date_to) {
                                    setDateRange(filters.date_from, filters.date_to);
                                } else {
                                    filters.date = null;
                                    filters.page = 1;
                                }
                            }}
                        />
                        <input
                            bind:value={filters.date_to}
                            type="date"
                            class="scheme-light-dark rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:bg-white dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:bg-white/8"
                            oninput={() => {
                                if (filters.date_from && filters.date_to) {
                                    setDateRange(filters.date_from, filters.date_to);
                                } else {
                                    filters.date = null;
                                    filters.page = 1;
                                }
                            }}
                        />
                    </div>
                {/if}
            </div>

            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                    Активные теги
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    {#if selectedTags.length === 0}
                        <div class="text-sm text-slate-500 dark:text-slate-400">
                            Теги пока не выбраны.
                        </div>
                    {:else}
                        {#each selectedTags as tagSlug (tagSlug)}
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1.5 text-sm font-medium text-white dark:bg-white dark:text-slate-950"
                                onclick={() => {
                                    toggleTag(tagSlug);
                                }}
                            >
                                #{tagSlug}
                                <X class="size-3.5" />
                            </button>
                        {/each}
                    {/if}
                </div>

                <div class="mt-4 space-y-3">
                    <input
                        bind:value={tagQuery}
                        type="search"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:bg-white dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:bg-white/8"
                        placeholder="Добавить тег"
                    />

                    {#if filteredTagSuggestions.length > 0}
                        <div class="flex flex-wrap gap-2">
                            {#each filteredTagSuggestions as tag (tag.id)}
                                <button
                                    type="button"
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
                                    onclick={() => {
                                        addTag(tag.slug);
                                    }}
                                >
                                    #{tag.name}
                                </button>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    {/if}

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-[1.5rem] border border-slate-200/70 bg-slate-50/80 px-4 py-3 dark:border-white/10 dark:bg-white/5">
        <div class="text-sm text-slate-500 dark:text-slate-400">
            Найдено
            <span class="font-semibold text-slate-900 dark:text-white">
                {totalResults}
            </span>
            статей
        </div>

        {#if activeCount > 0}
            <button
                type="button"
                class="inline-flex items-center gap-2 text-sm font-medium text-sky-700 transition hover:text-sky-800 dark:text-sky-300"
                onclick={clearAll}
            >
                Сбросить всё
                <X class="size-4" />
            </button>
        {/if}
    </div>
</section>
