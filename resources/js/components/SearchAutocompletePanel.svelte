<script lang="ts">
    import Search from 'lucide-svelte/icons/search';
    import {
        buildSearchAutocompleteItems,
        hasSearchSuggestions,
        highlightAutocompleteText,
    } from '@/lib/searchAutocomplete';
    import type {
        SearchAutocompleteItem,
        SearchSuggestions,
    } from '@/lib/searchAutocomplete';
    import { cn } from '@/lib/utils';

    type Props = {
        query: string;
        suggestions: SearchSuggestions;
        loading?: boolean;
        activeIndex?: number;
        onSearchSubmit?: (query: string) => void;
        onArticleSelect?: (slug: string) => void;
        onCategorySelect?: (slug: string) => void;
        onTagSelect?: (slug: string) => void;
    };

    let {
        query,
        suggestions,
        loading = false,
        activeIndex = -1,
        onSearchSubmit,
        onArticleSelect,
        onCategorySelect,
        onTagSelect,
    }: Props = $props();

    const normalizedQuery = $derived(query.trim());
    const items = $derived(buildSearchAutocompleteItems(normalizedQuery, suggestions));
    const hasMatches = $derived(hasSearchSuggestions(suggestions));
    const searchAction = $derived.by(
        (): Extract<SearchAutocompleteItem, { kind: 'search' }> | null =>
            items.find(
                (
                    item,
                ): item is Extract<SearchAutocompleteItem, { kind: 'search' }> =>
                    item.kind === 'search',
            ) ?? null,
    );
    const articleItems = $derived.by(
        (): Array<Extract<SearchAutocompleteItem, { section: 'articles' }>> =>
            items.filter(
                (
                    item,
                ): item is Extract<SearchAutocompleteItem, { section: 'articles' }> =>
                    item.section === 'articles',
            ),
    );
    const categoryItems = $derived.by(
        (): Array<Extract<SearchAutocompleteItem, { section: 'categories' }>> =>
            items.filter(
                (
                    item,
                ): item is Extract<SearchAutocompleteItem, { section: 'categories' }> =>
                    item.section === 'categories',
            ),
    );
    const tagItems = $derived.by(
        (): Array<Extract<SearchAutocompleteItem, { section: 'tags' }>> =>
            items.filter(
                (
                    item,
                ): item is Extract<SearchAutocompleteItem, { section: 'tags' }> =>
                    item.section === 'tags',
            ),
    );

    function formatArticleDate(value?: string | null): string {
        if (!value) {
            return '';
        }

        return new Intl.DateTimeFormat('ru-RU', {
            day: 'numeric',
            month: 'short',
        }).format(new Date(value));
    }

    function selectItem(item: SearchAutocompleteItem): void {
        switch (item.kind) {
            case 'search':
                onSearchSubmit?.(item.query);

                return;
            case 'article':
                onArticleSelect?.(item.article.slug);

                return;
            case 'category':
                onCategorySelect?.(item.category.slug);

                return;
            case 'tag':
                onTagSelect?.(item.tag.slug);

                return;
        }
    }
</script>

{#if normalizedQuery.length >= 2}
    <div class="mt-3 overflow-hidden rounded-[1.5rem] border border-slate-200/90 bg-white/96 shadow-[0_24px_70px_-45px_rgba(15,23,42,0.5)] backdrop-blur dark:border-white/10 dark:bg-slate-950/95">
        <div class="flex items-center justify-between border-b border-slate-200/80 px-4 py-3 dark:border-white/10">
            <div class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-slate-400">
                Автодополнение
            </div>
            <div class="text-xs text-slate-400">
                {#if loading}
                    Ищем варианты...
                {:else if hasMatches}
                    {items.length - 1} вариантов
                {:else}
                    Без совпадений
                {/if}
            </div>
        </div>

        <div class="max-h-[24rem] overflow-y-auto p-2" role="listbox" aria-label="Подсказки поиска">
            {#if searchAction}
                <button
                    type="button"
                    role="option"
                    aria-selected={activeIndex === searchAction.index}
                    class={cn(
                        'flex w-full items-center gap-3 rounded-[1.15rem] px-3 py-3 text-left transition',
                        activeIndex === searchAction.index
                            ? 'bg-sky-50 text-sky-950 ring-1 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-50 dark:ring-sky-500/30'
                            : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-white/5',
                    )}
                    onclick={() => {
                        selectItem(searchAction);
                    }}
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-300">
                        <Search class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold">
                            Искать по запросу “{searchAction.query}”
                        </div>
                        <div class="text-xs text-slate-400">
                            Показать полную поисковую выдачу
                        </div>
                    </div>
                </button>
            {/if}

            {#if articleItems.length > 0}
                <div class="px-3 pb-2 pt-3 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-slate-400">
                    Статьи
                </div>
                {#each articleItems as item (item.id)}
                    <button
                        type="button"
                        role="option"
                        aria-selected={activeIndex === item.index}
                        class={cn(
                            'flex w-full items-center justify-between gap-4 rounded-[1.15rem] px-3 py-3 text-left transition',
                            activeIndex === item.index
                                ? 'bg-sky-50 text-sky-950 ring-1 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-50 dark:ring-sky-500/30'
                                : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-white/5',
                        )}
                        onclick={() => {
                            selectItem(item);
                        }}
                    >
                        <div class="min-w-0">
                            <div class="line-clamp-1 text-sm font-semibold">
                                {#each highlightAutocompleteText(item.label, normalizedQuery) as segment, index (`${item.id}-${index}`)}
                                    {#if segment.highlighted}
                                        <mark class="rounded bg-sky-100 px-1 text-slate-950 dark:bg-sky-500/25 dark:text-sky-50">
                                            {segment.text}
                                        </mark>
                                    {:else}
                                        {segment.text}
                                    {/if}
                                {/each}
                            </div>
                            <div class="mt-1 text-xs text-slate-400">
                                Перейти к статье
                            </div>
                        </div>
                        <div class="shrink-0 text-xs text-slate-400">
                            {formatArticleDate(item.article.published_at)}
                        </div>
                    </button>
                {/each}
            {/if}

            {#if categoryItems.length > 0}
                <div class="px-3 pb-2 pt-3 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-slate-400">
                    Рубрики
                </div>
                <div class="flex flex-wrap gap-2 px-2 pb-1">
                    {#each categoryItems as item (item.id)}
                        <button
                            type="button"
                            role="option"
                            aria-selected={activeIndex === item.index}
                            class={cn(
                                'inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm transition',
                                activeIndex === item.index
                                    ? 'border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-500/40 dark:bg-sky-500/15 dark:text-sky-50'
                                    : 'border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5',
                            )}
                            onclick={() => {
                                selectItem(item);
                            }}
                        >
                            <span
                                class="size-2 rounded-full"
                                style={`background-color: ${item.category.color ?? '#2563EB'};`}
                            ></span>
                            <span class="font-medium">
                                {#each highlightAutocompleteText(item.label, normalizedQuery) as segment, index (`${item.id}-${index}`)}
                                    {#if segment.highlighted}
                                        <mark class="rounded bg-sky-100 px-1 text-slate-950 dark:bg-sky-500/25 dark:text-sky-50">
                                            {segment.text}
                                        </mark>
                                    {:else}
                                        {segment.text}
                                    {/if}
                                {/each}
                            </span>
                        </button>
                    {/each}
                </div>
            {/if}

            {#if tagItems.length > 0}
                <div class="px-3 pb-2 pt-3 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-slate-400">
                    Теги
                </div>
                <div class="flex flex-wrap gap-2 px-2 pb-1">
                    {#each tagItems as item (item.id)}
                        <button
                            type="button"
                            role="option"
                            aria-selected={activeIndex === item.index}
                            class={cn(
                                'inline-flex items-center gap-2 rounded-full px-3 py-2 text-sm transition',
                                activeIndex === item.index
                                    ? 'bg-sky-50 text-sky-950 ring-1 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-50 dark:ring-sky-500/30'
                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10',
                            )}
                            onclick={() => {
                                selectItem(item);
                            }}
                        >
                            <span class="text-slate-400">#</span>
                            <span class="font-medium">
                                {#each highlightAutocompleteText(item.label, normalizedQuery) as segment, index (`${item.id}-${index}`)}
                                    {#if segment.highlighted}
                                        <mark class="rounded bg-sky-100 px-1 text-slate-950 dark:bg-sky-500/25 dark:text-sky-50">
                                            {segment.text}
                                        </mark>
                                    {:else}
                                        {segment.text}
                                    {/if}
                                {/each}
                            </span>
                        </button>
                    {/each}
                </div>
            {/if}

            {#if !loading && !hasMatches}
                <div class="px-3 py-4 text-sm text-slate-500 dark:text-slate-400">
                    Быстрых совпадений нет. Нажмите Enter, чтобы открыть полную выдачу по запросу.
                </div>
            {/if}
        </div>
    </div>
{/if}
