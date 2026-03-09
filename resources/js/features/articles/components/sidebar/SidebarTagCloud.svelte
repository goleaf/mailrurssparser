<script lang="ts">
    import {
        clearTags,
        filters,
        toggleTag,
    } from '@/features/articles/state/articles.svelte.js';
    import {
        appInitialized,
        appTrendingTags,
        initApp,
    } from '@/features/portal/state/app.svelte.js';
    import { cn } from '@/lib/utils';

    type Tag = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
        usage_count?: number | null;
    };

    const tags = $derived(($appTrendingTags ?? []) as Tag[]);
    const selectedTags = $derived($filters.tags as string[]);

    function getSizeClass(usageCount: number): string {
        if (usageCount >= 50) {
            return 'text-sm font-bold';
        }

        if (usageCount >= 20) {
            return 'text-sm font-semibold';
        }

        return 'text-xs font-medium';
    }

    $effect(() => {
        if (!$appInitialized) {
            void initApp();
        }
    });
</script>

<aside
    class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900"
>
    <div class="mb-4 flex items-center justify-between">
        <div
            class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 dark:text-sky-300"
        >
            🔥 Популярные теги
        </div>

        {#if selectedTags.length > 0}
            <button
                type="button"
                class="text-xs font-medium text-slate-500 transition hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"
                onclick={() => {
                    clearTags();
                }}
            >
                Сбросить теги
            </button>
        {/if}
    </div>

    <div class="flex flex-wrap gap-2">
        {#each tags as tag (tag.id)}
            {@const active = selectedTags.includes(tag.slug)}
            <button
                type="button"
                class={cn(
                    'inline-flex items-center gap-2 rounded-full border px-3 py-2 transition',
                    getSizeClass(tag.usage_count ?? 0),
                    active
                        ? 'border-transparent text-white shadow-sm'
                        : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5',
                )}
                style={active
                    ? `background-color: ${tag.color ?? '#6B7280'}`
                    : `background-color: ${tag.color ? `${tag.color}22` : '#F1F5F9'}`}
                onclick={() => {
                    toggleTag(tag.slug);
                }}
            >
                <span>#{tag.name}</span>

                {#if active}
                    <span
                        class="rounded-full bg-white/20 px-1.5 py-0.5 text-[0.65rem]"
                    >
                        {tag.usage_count ?? 0}
                    </span>
                {/if}
            </button>
        {/each}
    </div>
</aside>
