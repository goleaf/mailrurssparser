<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Search from 'lucide-svelte/icons/search';
    import X from 'lucide-svelte/icons/x';
    import {
        onDestroy,
        tick,
    } from 'svelte';
    import { fade, fly } from 'svelte/transition';
    import {
        resetFilters,
        setCategory,
        toggleTag,
    } from '@/features/articles/state/articles.svelte.js';
    import * as api from '@/features/portal/data/api';
    import {
        articleUrl,
        homeUrl,
        searchUrl,
        visitPublic,
    } from '@/features/portal/routing/publicRoutes';
    import SearchAutocompletePanel from '@/features/search/components/SearchAutocompletePanel.svelte';
    import {
        buildSearchAutocompleteItems,
        emptySearchSuggestions,
    } from '@/features/search/data/searchAutocomplete';
    import type {
        SearchAutocompleteItem,
        SearchSuggestions,
    } from '@/features/search/data/searchAutocomplete';
    import {
        getActiveHtmlElement,
        trapFocusWithin,
    } from '@/lib/focus';
    import {
        prefersReducedMotion,
        resolveFadeTransition,
        resolveFlyTransition,
    } from '@/lib/motion';

    const RECENT_SEARCHES_KEY = 'news-portal-recent-searches';
    const SEARCH_AUTOCOMPLETE_LISTBOX_ID = 'search-modal-autocomplete';
    const SEARCH_AUTOCOMPLETE_OPTION_ID_PREFIX = 'search-modal-option';
    const SEARCH_DIALOG_TITLE_ID = 'search-modal-title';
    const noop = (): void => {};

    interface Props {
        open?: boolean;
        onClose?: () => void;
        returnFocusElement?: HTMLElement | null;
    }

    let {
        open = false,
        onClose = noop,
        returnFocusElement = null,
    }: Props = $props();

    let query = $state('');
    let suggestions = $state<SearchSuggestions>({ ...emptySearchSuggestions });
    let loading = $state(false);
    let recentSearches = $state<string[]>([]);
    let modalContainer = $state<HTMLElement | null>(null);
    let searchInput = $state<HTMLInputElement | null>(null);
    let activeSuggestionIndex = $state(-1);
    let autocompleteAbortController: AbortController | null = null;
    let autocompleteRequestVersion = 0;
    let autocompleteTimer: ReturnType<typeof setTimeout> | null = null;
    let previouslyFocusedElement = $state<HTMLElement | null>(null);

    const activeSuggestionId = $derived(
        activeSuggestionIndex >= 0
            ? `${SEARCH_AUTOCOMPLETE_OPTION_ID_PREFIX}-${activeSuggestionIndex}`
            : undefined,
    );
    const modalBackdropTransition = $derived(
        resolveFadeTransition($prefersReducedMotion, {
            duration: 180,
        }),
    );
    const modalPanelTransition = $derived(
        resolveFlyTransition($prefersReducedMotion, {
            duration: 220,
            opacity: 1,
            y: 28,
        }),
    );

    function readRecentSearches(): string[] {
        if (typeof localStorage === 'undefined') {
            return [];
        }

        try {
            const stored = localStorage.getItem(RECENT_SEARCHES_KEY);

            if (!stored) {
                return [];
            }

            const parsed = JSON.parse(stored);

            return Array.isArray(parsed) ? parsed.slice(0, 5) : [];
        } catch {
            return [];
        }
    }

    function rememberSearch(value: string): void {
        const normalized = value.trim();

        if (!normalized || typeof localStorage === 'undefined') {
            return;
        }

        const next = [
            normalized,
            ...recentSearches.filter(
                (item) => item.toLowerCase() !== normalized.toLowerCase(),
            ),
        ].slice(0, 5);

        recentSearches = next;
        localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(next));
    }

    function navigateTo(path: string): void {
        visitPublic(path.startsWith('/') ? path : `/${path}`);
    }

    function resetModalState(): void {
        cancelAutocompleteRequest();
        query = '';
        loading = false;
        activeSuggestionIndex = -1;
        suggestions = { ...emptySearchSuggestions };
    }

    function restoreFocus(): void {
        const focusTarget =
            returnFocusElement?.isConnected === true
                ? returnFocusElement
                : previouslyFocusedElement?.isConnected === true
                  ? previouslyFocusedElement
                  : null;

        previouslyFocusedElement = null;

        if (focusTarget === null) {
            return;
        }

        queueMicrotask(() => {
            focusTarget.focus();
        });
    }

    function close(): void {
        resetModalState();
        onClose();
        restoreFocus();
    }

    function submitSearch(nextQuery = query): void {
        const normalized = nextQuery.trim();

        if (normalized.length < 2) {
            return;
        }

        rememberSearch(normalized);
        close();
        navigateTo(searchUrl(normalized));
    }

    function applyCategory(slug: string): void {
        resetFilters();
        setCategory(slug);
        close();
        router.visit(homeUrl());
    }

    function applyTag(slug: string): void {
        resetFilters();
        toggleTag(slug);
        close();
        router.visit(homeUrl());
    }

    function openArticle(slug: string): void {
        close();
        navigateTo(articleUrl(slug));
    }

    function formatArticleDate(value?: string | null): string {
        if (!value) {
            return '';
        }

        return new Intl.DateTimeFormat('ru-RU', {
            day: 'numeric',
            month: 'short',
        }).format(new Date(value));
    }

    function selectAutocompleteItem(item: SearchAutocompleteItem): void {
        switch (item.kind) {
            case 'search':
                submitSearch(item.query);

                return;
            case 'article':
                openArticle(item.article.slug);

                return;
            case 'category':
                applyCategory(item.category.slug);

                return;
            case 'tag':
                applyTag(item.tag.slug);

                return;
        }
    }

    function handleSearchKeydown(event: KeyboardEvent): void {
        const items = buildSearchAutocompleteItems(query, suggestions);

        if (event.key === 'ArrowDown') {
            if (items.length === 0) {
                return;
            }

            event.preventDefault();
            activeSuggestionIndex =
                (activeSuggestionIndex + 1 + items.length) % items.length;

            return;
        }

        if (event.key === 'ArrowUp') {
            if (items.length === 0) {
                return;
            }

            event.preventDefault();
            activeSuggestionIndex =
                activeSuggestionIndex <= 0
                    ? items.length - 1
                    : activeSuggestionIndex - 1;

            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();

            if (activeSuggestionIndex >= 0 && items[activeSuggestionIndex]) {
                selectAutocompleteItem(items[activeSuggestionIndex]);

                return;
            }

            submitSearch();
        }
    }

    function clearAutocompleteTimer(): void {
        if (autocompleteTimer !== null && typeof window !== 'undefined') {
            window.clearTimeout(autocompleteTimer);
            autocompleteTimer = null;
        }
    }

    function cancelAutocompleteRequest(): void {
        autocompleteAbortController?.abort();
        autocompleteAbortController = null;
    }

    function handleDialogKeydown(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();

            return;
        }

        trapFocusWithin(event, modalContainer);
    }

    onDestroy(() => {
        clearAutocompleteTimer();
        cancelAutocompleteRequest();
    });

    $effect(() => {
        if (!open || typeof window === 'undefined') {
            return;
        }

        previouslyFocusedElement = getActiveHtmlElement();
        recentSearches = readRecentSearches();

        void tick().then(() => {
            searchInput?.focus();
        });
    });

    $effect(() => {
        if (!open || typeof window === 'undefined') {
            return;
        }

        const normalized = query.trim();

        if (normalized.length < 2) {
            cancelAutocompleteRequest();
            suggestions = { ...emptySearchSuggestions };
            activeSuggestionIndex = -1;
            loading = false;

            return;
        }

        loading = true;
        const requestVersion = ++autocompleteRequestVersion;

        clearAutocompleteTimer();
        autocompleteTimer = window.setTimeout(async () => {
            cancelAutocompleteRequest();
            autocompleteAbortController = new AbortController();

            try {
                const response = await api.suggestSearch(normalized, {
                    signal: autocompleteAbortController.signal,
                });

                if (requestVersion !== autocompleteRequestVersion) {
                    return;
                }

                suggestions = response.data;
                activeSuggestionIndex = -1;
            } catch (error) {
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }

                if (requestVersion !== autocompleteRequestVersion) {
                    return;
                }

                suggestions = { ...emptySearchSuggestions };
                activeSuggestionIndex = -1;
            } finally {
                if (requestVersion === autocompleteRequestVersion) {
                    loading = false;
                }
            }
        }, 300);

        return () => {
            clearAutocompleteTimer();
        };
    });
</script>

{#if open}
    <div class="fixed inset-0 z-50 px-4 py-6 sm:px-6 lg:px-8">
        <button
            type="button"
            class="absolute inset-0 bg-slate-950/70 backdrop-blur-md"
            onclick={close}
            aria-label="Закрыть поиск"
            in:fade={modalBackdropTransition}
            out:fade={modalBackdropTransition}
        ></button>

        <div
            class="relative mx-auto flex min-h-full max-w-5xl items-start justify-center"
        >
            <div
                bind:this={modalContainer}
                class="mt-8 w-full overflow-hidden rounded-[2rem] border border-white/10 bg-white p-6 shadow-2xl shadow-black/30 dark:bg-neutral-950 sm:p-8"
                role="dialog"
                aria-modal="true"
                aria-labelledby={SEARCH_DIALOG_TITLE_ID}
                tabindex="-1"
                onkeydown={handleDialogKeydown}
                in:fly={modalPanelTransition}
                out:fly={modalPanelTransition}
            >
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <div
                            class="text-xs font-semibold uppercase tracking-[0.25em] text-sky-600 dark:text-sky-300"
                        >
                            Быстрый поиск
                        </div>
                        <h2
                            id={SEARCH_DIALOG_TITLE_ID}
                            class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white sm:text-3xl"
                        >
                            Найти статью, рубрику или тег
                        </h2>
                    </div>

                    <button
                        type="button"
                        class="inline-flex size-11 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:bg-slate-100 hover:text-slate-900 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                        onclick={close}
                        aria-label="Закрыть поиск"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div class="rounded-[1.75rem] bg-slate-100 p-2 dark:bg-white/5">
                    <div
                        class="rounded-[1.25rem] bg-white px-4 py-3 shadow-sm dark:bg-neutral-900"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center"
                        >
                            <Search class="size-5 shrink-0 text-slate-400" />
                            <input
                                bind:this={searchInput}
                                bind:value={query}
                                type="text"
                                role="combobox"
                                autocomplete="off"
                                aria-activedescendant={activeSuggestionId}
                                aria-autocomplete="list"
                                aria-controls={SEARCH_AUTOCOMPLETE_LISTBOX_ID}
                                aria-expanded={query.trim().length >= 2}
                                class="min-w-0 flex-1 bg-transparent text-base text-slate-900 outline-none placeholder:text-slate-400 dark:text-white"
                                placeholder="Поиск по новостям..."
                                onkeydown={handleSearchKeydown}
                            />
                            <button
                                type="button"
                                class="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                                onclick={() => {
                                    submitSearch();
                                }}
                            >
                                Искать
                            </button>
                        </div>

                        <SearchAutocompletePanel
                            {query}
                            {suggestions}
                            {loading}
                            activeIndex={activeSuggestionIndex}
                            listboxId={SEARCH_AUTOCOMPLETE_LISTBOX_ID}
                            optionIdPrefix={SEARCH_AUTOCOMPLETE_OPTION_ID_PREFIX}
                            onSearchSubmit={submitSearch}
                            onArticleSelect={openArticle}
                            onCategorySelect={applyCategory}
                            onTagSelect={applyTag}
                        />
                    </div>
                </div>

                <div class="mt-6 grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3
                                class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400"
                            >
                                Подсказки
                            </h3>
                            {#if loading}
                                <span class="text-xs text-slate-400"
                                    >Обновляем...</span
                                >
                            {/if}
                        </div>

                        {#if !query.trim()}
                            <div
                                class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                            >
                                Начните вводить запрос, чтобы увидеть подходящие
                                статьи, рубрики и теги.
                            </div>
                        {/if}

                        {#if query.trim() && !loading && suggestions.articles.length === 0 && suggestions.categories.length === 0 && suggestions.tags.length === 0}
                            <div
                                class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                            >
                                Ничего не найдено. Попробуйте другой запрос.
                            </div>
                        {/if}

                        {#if suggestions.articles.length > 0}
                            <div
                                class="rounded-3xl border border-slate-200 p-3 dark:border-white/10"
                            >
                                <div
                                    class="mb-2 px-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                                >
                                    Статьи
                                </div>
                                <div class="space-y-1">
                                    {#each suggestions.articles as article (article.id)}
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-4 rounded-2xl px-3 py-3 text-left transition hover:bg-slate-50 dark:hover:bg-white/5"
                                            onclick={() => {
                                                openArticle(article.slug);
                                            }}
                                        >
                                            <span
                                                class="font-medium text-slate-800 dark:text-slate-100"
                                            >
                                                {article.title}
                                            </span>
                                            <span
                                                class="shrink-0 text-xs text-slate-400"
                                            >
                                                {formatArticleDate(
                                                    article.published_at,
                                                )}
                                            </span>
                                        </button>
                                    {/each}
                                </div>
                            </div>
                        {/if}

                        {#if suggestions.categories.length > 0}
                            <div
                                class="rounded-3xl border border-slate-200 p-4 dark:border-white/10"
                            >
                                <div
                                    class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                                >
                                    Рубрики
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    {#each suggestions.categories as category (category.id)}
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                            onclick={() => {
                                                applyCategory(category.slug);
                                            }}
                                        >
                                            <span
                                                class="size-2 rounded-full"
                                                style={`background-color: ${category.color ?? '#2563EB'};`}
                                            ></span>
                                            {category.name}
                                        </button>
                                    {/each}
                                </div>
                            </div>
                        {/if}

                        {#if suggestions.tags.length > 0}
                            <div
                                class="rounded-3xl border border-slate-200 p-4 dark:border-white/10"
                            >
                                <div
                                    class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                                >
                                    Теги
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    {#each suggestions.tags as tag (tag.id)}
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-200 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                                            onclick={() => {
                                                applyTag(tag.slug);
                                            }}
                                        >
                                            #{tag.name}
                                        </button>
                                    {/each}
                                </div>
                            </div>
                        {/if}
                    </div>

                    <div class="space-y-4">
                        <div
                            class="rounded-3xl bg-slate-950 p-5 text-white dark:bg-slate-900"
                        >
                            <div
                                class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-300"
                            >
                                Последние запросы
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                {#if recentSearches.length > 0}
                                    {#each recentSearches as item (item)}
                                        <button
                                            type="button"
                                            class="rounded-full border border-white/15 px-3 py-2 text-sm transition hover:border-sky-300 hover:bg-white/10"
                                            onclick={() => {
                                                query = item;
                                                submitSearch(item);
                                            }}
                                        >
                                            {item}
                                        </button>
                                    {/each}
                                {:else}
                                    <p class="text-sm text-slate-300">
                                        История поиска появится здесь после
                                        первых запросов.
                                    </p>
                                {/if}
                            </div>
                        </div>

                        <button
                            type="button"
                            class="w-full rounded-3xl border border-slate-200 px-4 py-4 text-left text-sm text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                            onclick={() => {
                                submitSearch();
                            }}
                        >
                            <span
                                class="block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                            >
                                Полный поиск
                            </span>
                            <span
                                class="mt-1 block text-base font-semibold text-slate-900 dark:text-white"
                            >
                                Показать все результаты
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}
