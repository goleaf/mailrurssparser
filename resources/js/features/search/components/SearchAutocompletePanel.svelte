<script lang="ts">
    import Search from 'lucide-svelte/icons/search';
    import {
        buildSearchAutocompleteItems,
        hasSearchSuggestions,
        highlightAutocompleteText,
    } from '@/features/search/data/searchAutocomplete';
    import type {
        SearchAutocompleteItem,
        SearchSuggestions,
    } from '@/features/search/data/searchAutocomplete';
    import { cn } from '@/lib/utils';

    /**
     * Typed prop contract for the shared autocomplete dropdown.
     * The component renders suggestion groups and delegates selection upward.
     */
    interface Props {
        /** Raw query string used for filtering and highlighting. */
        query: string;
        /** Structured suggestions grouped by result type. */
        suggestions: SearchSuggestions;
        /** Whether suggestion data is currently loading. */
        loading?: boolean;
        /** Index of the keyboard-highlighted suggestion. */
        activeIndex?: number;
        /** DOM id used to link the search input with the listbox. */
        listboxId?: string;
        /** DOM id prefix used for individual suggestion options. */
        optionIdPrefix?: string;
        /** Called when the user submits a free-text search action. */
        onSearchSubmit?: (query: string) => void;
        /** Called when the user chooses an article suggestion. */
        onArticleSelect?: (slug: string) => void;
        /** Called when the user chooses a category suggestion. */
        onCategorySelect?: (slug: string) => void;
        /** Called when the user chooses a tag suggestion. */
        onTagSelect?: (slug: string) => void;
    }

    let {
        query,
        suggestions,
        loading = false,
        activeIndex = -1,
        listboxId = 'search-autocomplete',
        optionIdPrefix = 'search-autocomplete-option',
        onSearchSubmit,
        onArticleSelect,
        onCategorySelect,
        onTagSelect,
    }: Props = $props();

    type SearchActionItem = Extract<SearchAutocompleteItem, { kind: 'search' }>;
    type ArticleSuggestionItem = Extract<
        SearchAutocompleteItem,
        { section: 'articles' }
    >;
    type CategorySuggestionItem = Extract<
        SearchAutocompleteItem,
        { section: 'categories' }
    >;
    type TagSuggestionItem = Extract<SearchAutocompleteItem, { section: 'tags' }>;

    const normalizedQuery = $derived(query.trim());
    const items = $derived(
        buildSearchAutocompleteItems(normalizedQuery, suggestions),
    );
    const autocompleteSections = $derived.by(() => {
        const sections: {
            hasMatches: boolean;
            suggestionCount: number;
            searchAction: SearchActionItem | null;
            articleItems: ArticleSuggestionItem[];
            categoryItems: CategorySuggestionItem[];
            tagItems: TagSuggestionItem[];
            itemByKey: Map<string, SearchAutocompleteItem>;
        } = {
            hasMatches: hasSearchSuggestions(suggestions),
            suggestionCount: 0,
            searchAction: null,
            articleItems: [],
            categoryItems: [],
            tagItems: [],
            itemByKey: new Map<string, SearchAutocompleteItem>(),
        };

        for (const item of items) {
            sections.itemByKey.set(getItemKey(item), item);

            if (item.kind === 'search') {
                sections.searchAction = item;

                continue;
            }

            sections.suggestionCount += 1;

            if (item.section === 'articles') {
                sections.articleItems.push(item);

                continue;
            }

            if (item.section === 'categories') {
                sections.categoryItems.push(item);

                continue;
            }

            sections.tagItems.push(item);
        }

        return sections;
    });

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

    function getItemKey(item: SearchAutocompleteItem): string {
        switch (item.kind) {
            case 'search':
                return `search:${item.query}`;
            case 'article':
                return `article:${item.article.slug}`;
            case 'category':
                return `category:${item.category.slug}`;
            case 'tag':
                return `tag:${item.tag.slug}`;
        }
    }

    function handleItemClick(event: Event): void {
        const itemKey = (event.currentTarget as HTMLButtonElement).dataset.itemKey;

        if (!itemKey) {
            return;
        }

        const item = autocompleteSections.itemByKey.get(itemKey);

        if (!item) {
            return;
        }

        selectItem(item);
    }

    function getOptionId(index: number): string {
        return `${optionIdPrefix}-${index}`;
    }
</script>

{#if normalizedQuery.length >= 2}
    <div
        class="mt-3 overflow-hidden rounded-[1.5rem] border border-slate-200/90 bg-white/96 shadow-[0_24px_70px_-45px_rgba(15,23,42,0.5)] backdrop-blur dark:border-white/10 dark:bg-slate-950/95"
    >
        <div
            class="flex items-center justify-between border-b border-slate-200/80 px-4 py-3 dark:border-white/10"
        >
            <div
                class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-slate-400"
            >
                Автодополнение
            </div>
            <div class="text-xs text-slate-400">
                {#if loading}
                    Ищем варианты...
                {:else if autocompleteSections.hasMatches}
                    {autocompleteSections.suggestionCount} вариантов
                {:else}
                    Без совпадений
                {/if}
            </div>
        </div>

        <div
            id={listboxId}
            class="max-h-[24rem] overflow-y-auto p-2"
            role="listbox"
            aria-label="Подсказки поиска"
        >
            {#if autocompleteSections.searchAction}
                <button
                    id={getOptionId(autocompleteSections.searchAction.index)}
                    type="button"
                    role="option"
                    aria-selected={activeIndex === autocompleteSections.searchAction.index}
                    class={cn(
                        'flex w-full items-center gap-3 rounded-[1.15rem] px-3 py-3 text-left transition',
                        activeIndex === autocompleteSections.searchAction.index
                            ? 'bg-sky-50 text-sky-950 ring-1 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-50 dark:ring-sky-500/30'
                            : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-white/5',
                    )}
                    data-item-key={getItemKey(autocompleteSections.searchAction)}
                    onclick={handleItemClick}
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-300"
                    >
                        <Search class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold">
                            Искать по запросу “{autocompleteSections.searchAction.query}”
                        </div>
                        <div class="text-xs text-slate-400">
                            Показать полную поисковую выдачу
                        </div>
                    </div>
                </button>
            {/if}

            {#if autocompleteSections.articleItems.length > 0}
                <div
                    class="px-3 pb-2 pt-3 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-slate-400"
                >
                    Статьи
                </div>
                {#each autocompleteSections.articleItems as item (item.id)}
                    <button
                        id={getOptionId(item.index)}
                        type="button"
                        role="option"
                        aria-selected={activeIndex === item.index}
                        class={cn(
                            'flex w-full items-center justify-between gap-4 rounded-[1.15rem] px-3 py-3 text-left transition',
                            activeIndex === item.index
                                ? 'bg-sky-50 text-sky-950 ring-1 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-50 dark:ring-sky-500/30'
                                : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-white/5',
                        )}
                        data-item-key={getItemKey(item)}
                        onclick={handleItemClick}
                    >
                        <div class="min-w-0">
                            <div class="line-clamp-1 text-sm font-semibold">
                                {#each highlightAutocompleteText(item.label, normalizedQuery) as segment, index (`${item.id}-${index}`)}
                                    {#if segment.highlighted}
                                        <mark
                                            class="rounded bg-sky-100 px-1 text-slate-950 dark:bg-sky-500/25 dark:text-sky-50"
                                        >
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

            {#if autocompleteSections.categoryItems.length > 0}
                <div
                    class="px-3 pb-2 pt-3 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-slate-400"
                >
                    Рубрики
                </div>
                <div class="flex flex-wrap gap-2 px-2 pb-1">
                    {#each autocompleteSections.categoryItems as item (item.id)}
                        <button
                            id={getOptionId(item.index)}
                            type="button"
                            role="option"
                            aria-selected={activeIndex === item.index}
                            class={cn(
                                'inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm transition',
                                activeIndex === item.index
                                    ? 'border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-500/40 dark:bg-sky-500/15 dark:text-sky-50'
                                    : 'border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5',
                            )}
                            data-item-key={getItemKey(item)}
                            onclick={handleItemClick}
                        >
                            <span
                                class="size-2 rounded-full"
                                style={`background-color: ${item.category.color ?? '#2563EB'};`}
                            ></span>
                            <span class="font-medium">
                                {#each highlightAutocompleteText(item.label, normalizedQuery) as segment, index (`${item.id}-${index}`)}
                                    {#if segment.highlighted}
                                        <mark
                                            class="rounded bg-sky-100 px-1 text-slate-950 dark:bg-sky-500/25 dark:text-sky-50"
                                        >
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

            {#if autocompleteSections.tagItems.length > 0}
                <div
                    class="px-3 pb-2 pt-3 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-slate-400"
                >
                    Теги
                </div>
                <div class="flex flex-wrap gap-2 px-2 pb-1">
                    {#each autocompleteSections.tagItems as item (item.id)}
                        <button
                            id={getOptionId(item.index)}
                            type="button"
                            role="option"
                            aria-selected={activeIndex === item.index}
                            class={cn(
                                'inline-flex items-center gap-2 rounded-full px-3 py-2 text-sm transition',
                                activeIndex === item.index
                                    ? 'bg-sky-50 text-sky-950 ring-1 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-50 dark:ring-sky-500/30'
                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10',
                            )}
                            data-item-key={getItemKey(item)}
                            onclick={handleItemClick}
                        >
                            <span class="text-slate-400">#</span>
                            <span class="font-medium">
                                {#each highlightAutocompleteText(item.label, normalizedQuery) as segment, index (`${item.id}-${index}`)}
                                    {#if segment.highlighted}
                                        <mark
                                            class="rounded bg-sky-100 px-1 text-slate-950 dark:bg-sky-500/25 dark:text-sky-50"
                                        >
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

            {#if !loading && !autocompleteSections.hasMatches}
                <div
                    class="px-3 py-4 text-sm text-slate-500 dark:text-slate-400"
                >
                    Быстрых совпадений нет. Нажмите Enter, чтобы открыть полную
                    выдачу по запросу.
                </div>
            {/if}
        </div>
    </div>
{/if}
