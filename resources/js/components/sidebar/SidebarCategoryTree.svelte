<script lang="ts">
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import { cn } from '@/lib/utils';
    import { appState, initApp } from '@/stores/app.svelte.js';
    import {
        filters,
        resetFilters,
        setCategory,
        setSubCategory,
    } from '@/stores/articles.svelte.js';

    type SubCategory = {
        id: number | string;
        name: string;
        slug: string;
    };

    type Category = {
        id: number | string;
        name: string;
        slug: string;
        icon?: string | null;
        color?: string | null;
        articles_count_cache?: number | null;
        sub_categories?: SubCategory[];
    };

    let expandedCategoryIds = $state<Record<string, boolean>>({});

    const categories = $derived((appState.categories ?? []) as Category[]);

    function toggleExpanded(categoryId: number | string): void {
        const key = String(categoryId);

        expandedCategoryIds = {
            ...expandedCategoryIds,
            [key]: !expandedCategoryIds[key],
        };
    }

    function isExpanded(categoryId: number | string): boolean {
        return Boolean(expandedCategoryIds[String(categoryId)]);
    }

    $effect(() => {
        if (!appState.initialized) {
            void initApp();
        }
    });
</script>

<aside class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900">
    <div class="mb-4 text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 dark:text-sky-300">
        📂 Категории
    </div>

    <div class="space-y-2">
        <button
            type="button"
            class={cn(
                'flex w-full items-center justify-between rounded-2xl border-l-4 px-4 py-3 text-left text-sm font-medium transition',
                !filters.category
                    ? 'border-sky-500 bg-sky-50 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200'
                    : 'border-transparent text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-white/5',
            )}
            onclick={() => {
                resetFilters();
            }}
        >
            <span class="flex items-center gap-3">
                <span class="text-base">📰</span>
                <span>Все новости</span>
            </span>
            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-500 dark:bg-white/10 dark:text-slate-300">
                Все
            </span>
        </button>

        {#each categories as category (category.id)}
            <div class="rounded-2xl border border-transparent transition hover:border-slate-200 dark:hover:border-white/10">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class={cn(
                            'flex min-w-0 flex-1 items-center justify-between rounded-2xl border-l-4 px-4 py-3 text-left text-sm font-medium transition',
                            filters.category === category.slug
                                ? 'bg-slate-100 text-slate-900 dark:bg-white/10 dark:text-white'
                                : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-white/5',
                        )}
                        style={`border-left-color: ${
                            filters.category === category.slug
                                ? (category.color ?? '#3B82F6')
                                : 'transparent'
                        }`}
                        onclick={() => {
                            setCategory(category.slug);
                        }}
                    >
                        <span class="flex min-w-0 items-center gap-3">
                            <span class="text-base">{category.icon ?? '•'}</span>
                            <span class="truncate">{category.name}</span>
                        </span>

                        <span class="ml-3 rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-500 dark:bg-white/10 dark:text-slate-300">
                            {category.articles_count_cache ?? 0}
                        </span>
                    </button>

                    {#if category.sub_categories?.length}
                        <button
                            type="button"
                            class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/5 dark:hover:text-slate-200"
                            onclick={() => {
                                toggleExpanded(category.id);
                            }}
                            aria-label="Показать подкатегории"
                        >
                            <ChevronDown
                                class={cn(
                                    'size-4 transition-transform duration-200',
                                    isExpanded(category.id) && 'rotate-180',
                                )}
                            />
                        </button>
                    {/if}
                </div>

                {#if category.sub_categories?.length && isExpanded(category.id)}
                    <div class="mt-2 space-y-1 pl-6">
                        {#each category.sub_categories as subCategory (subCategory.id)}
                            <button
                                type="button"
                                class={cn(
                                    'flex w-full items-center rounded-xl px-3 py-2 text-left text-sm transition',
                                    filters.sub === subCategory.slug
                                        ? 'bg-slate-100 text-slate-900 dark:bg-white/10 dark:text-white'
                                        : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-slate-200',
                                )}
                                onclick={() => {
                                    setCategory(category.slug);
                                    setSubCategory(subCategory.slug);
                                }}
                            >
                                {subCategory.name}
                            </button>
                        {/each}
                    </div>
                {/if}
            </div>
        {/each}
    </div>
</aside>
