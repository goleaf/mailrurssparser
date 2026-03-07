<script lang="ts">
    import Bookmark from 'lucide-svelte/icons/bookmark';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import Menu from 'lucide-svelte/icons/menu';
    import MoonStar from 'lucide-svelte/icons/moon-star';
    import Newspaper from 'lucide-svelte/icons/newspaper';
    import Search from 'lucide-svelte/icons/search';
    import SunMedium from 'lucide-svelte/icons/sun-medium';
    import X from 'lucide-svelte/icons/x';
    import * as api from '@/lib/api';
    import { cn } from '@/lib/utils';
    import { appState, initApp, toggleDarkMode, toggleSidebar } from '@/stores/app.svelte.js';
    import { bookmarkIds, loadBookmarks } from '@/stores/bookmarks.svelte.js';

    type Category = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
    };

    type SearchSuggestions = {
        articles: Array<{
            id: number | string;
            title: string;
            slug: string;
            published_at?: string | null;
        }>;
        categories: Category[];
        tags: Array<{
            id: number | string;
            name: string;
            slug: string;
        }>;
    };

    let currentHash = $state('#/');
    let hasShadow = $state(false);
    let moreMenuOpen = $state(false);
    let searchOpen = $state(false);
    let searchQuery = $state('');
    let searchInput = $state<HTMLInputElement | null>(null);
    let suggestionsLoading = $state(false);
    let recentSearches = $state<string[]>([]);
    let suggestions = $state<SearchSuggestions>({
        articles: [],
        categories: [],
        tags: [],
    });

    const categories = $derived((appState.categories ?? []) as Category[]);
    const featuredCategories = $derived(categories.slice(0, 6));
    const bookmarkCount = $derived(bookmarkIds.length);

    const searchSuggestions = {
        articles: [],
        categories: [],
        tags: [],
    } satisfies SearchSuggestions;

    function readRecentSearches(): string[] {
        if (typeof localStorage === 'undefined') {
            return [];
        }

        try {
            const stored = localStorage.getItem('news-portal-recent-searches');

            if (!stored) {
                return [];
            }

            const parsed = JSON.parse(stored);

            return Array.isArray(parsed) ? parsed.slice(0, 5) : [];
        } catch {
            return [];
        }
    }

    function saveRecentSearch(query: string): void {
        const value = query.trim();

        if (!value || typeof localStorage === 'undefined') {
            return;
        }

        const next = [
            value,
            ...recentSearches.filter((item) => item.toLowerCase() !== value.toLowerCase()),
        ].slice(0, 5);

        recentSearches = next;
        localStorage.setItem(
            'news-portal-recent-searches',
            JSON.stringify(next),
        );
    }

    function navigateTo(path: string): void {
        if (typeof window === 'undefined') {
            return;
        }

        window.location.hash = path.startsWith('/') ? path : `/${path}`;
        searchOpen = false;
        moreMenuOpen = false;
        appState.sidebarOpen = false;
    }

    function openSearch(query = ''): void {
        searchQuery = query;
        searchOpen = true;
    }

    function closeSearch(): void {
        searchOpen = false;
        searchQuery = '';
        suggestions = {
            ...searchSuggestions,
        };
        suggestionsLoading = false;
    }

    function submitSearch(): void {
        const query = searchQuery.trim();

        if (!query) {
            return;
        }

        saveRecentSearch(query);
        navigateTo(`/search?q=${encodeURIComponent(query)}`);
    }

    function isCategoryActive(slug: string): boolean {
        return currentHash.startsWith(`#/category/${slug}`);
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

    function handleSearchKeydown(event: KeyboardEvent): void {
        if (event.key === 'Enter') {
            event.preventDefault();
            submitSearch();
        }
    }

    $effect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const syncViewportState = (): void => {
            currentHash = window.location.hash || '#/';
            hasShadow = window.scrollY > 12;
        };

        syncViewportState();
        window.addEventListener('scroll', syncViewportState, {
            passive: true,
        });
        window.addEventListener('hashchange', syncViewportState);

        return () => {
            window.removeEventListener('scroll', syncViewportState);
            window.removeEventListener('hashchange', syncViewportState);
        };
    });

    $effect(() => {
        if (!searchOpen || typeof window === 'undefined') {
            return;
        }

        const focusTimer = window.setTimeout(() => {
            searchInput?.focus();
        }, 0);

        const handleEscape = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                closeSearch();
            }
        };

        window.addEventListener('keydown', handleEscape);

        return () => {
            window.clearTimeout(focusTimer);
            window.removeEventListener('keydown', handleEscape);
        };
    });

    $effect(() => {
        if (!searchOpen || typeof window === 'undefined') {
            return;
        }

        const query = searchQuery.trim();

        if (!query) {
            suggestions = {
                ...searchSuggestions,
            };
            suggestionsLoading = false;

            return;
        }

        suggestionsLoading = true;

        const timeoutId = window.setTimeout(async () => {
            try {
                const response = await api.suggestSearch(query);

                suggestions = {
                    articles: response.data?.articles ?? [],
                    categories: response.data?.categories ?? [],
                    tags: response.data?.tags ?? [],
                };
            } catch {
                suggestions = {
                    ...searchSuggestions,
                };
            } finally {
                suggestionsLoading = false;
            }
        }, 300);

        return () => {
            window.clearTimeout(timeoutId);
        };
    });

    $effect(() => {
        void (async () => {
            if (!appState.initialized) {
                try {
                    await initApp();
                } catch {
                    return;
                }
            }

            try {
                await loadBookmarks();
            } catch {
                return;
            }
        })();

        if (typeof document !== 'undefined') {
            appState.darkMode = document.documentElement.classList.contains('dark');
        }

        recentSearches = readRecentSearches();
    });
</script>

<header
    class={cn(
        'sticky top-0 z-40 border-b border-black/5 bg-white/88 backdrop-blur-xl transition-shadow duration-300 dark:border-white/10 dark:bg-neutral-950/88',
        hasShadow && 'shadow-[0_18px_50px_-30px_rgba(15,23,42,0.45)]',
    )}
>
    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 lg:px-6">
        <a
            href="/#/"
            class="group flex shrink-0 items-center gap-3 rounded-full border border-sky-200 bg-sky-50 px-4 py-2 text-slate-900 transition hover:border-sky-300 hover:bg-sky-100 dark:border-sky-900/60 dark:bg-sky-950/50 dark:text-slate-50 dark:hover:border-sky-800 dark:hover:bg-sky-950"
        >
            <span
                class="flex size-10 items-center justify-center rounded-full bg-slate-900 text-lg text-white shadow-sm dark:bg-white dark:text-slate-900"
            >
                🗞️
            </span>
            <div class="min-w-0">
                <div class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-sky-700 dark:text-sky-300">
                    News Portal
                </div>
                <div class="font-semibold">Новости</div>
            </div>
        </a>

        <nav class="hidden min-w-0 flex-1 items-center gap-2 lg:flex">
            <a
                href="/#/"
                class={cn(
                    'rounded-full px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white',
                    currentHash === '#/' &&
                        'bg-slate-900 text-white dark:bg-white dark:text-slate-950',
                )}
            >
                Все новости
            </a>

            {#each featuredCategories as category (category.id)}
                <a
                    href={`/#/category/${category.slug}`}
                    class={cn(
                        'inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white',
                        isCategoryActive(category.slug) &&
                            'bg-slate-900 text-white dark:bg-white dark:text-slate-950',
                    )}
                >
                    <span
                        class="size-2 rounded-full"
                        style={`background-color: ${category.color ?? '#2563EB'};`}
                    ></span>
                    <span class="truncate">{category.name}</span>
                </a>
            {/each}

            {#if categories.length > 6}
                <div
                    class="relative"
                    role="presentation"
                    onmouseenter={() => {
                        moreMenuOpen = true;
                    }}
                    onmouseleave={() => {
                        moreMenuOpen = false;
                    }}
                >
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                    >
                        Ещё
                        <ChevronDown
                            class={cn(
                                'size-4 transition-transform duration-200',
                                moreMenuOpen && 'rotate-180',
                            )}
                        />
                    </button>

                    {#if moreMenuOpen}
                        <div
                            class="absolute left-0 top-full mt-3 w-80 rounded-3xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-900/15 dark:border-white/10 dark:bg-neutral-900 dark:shadow-black/50"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white">
                                        Все рубрики
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        Быстрый переход по разделам
                                    </div>
                                </div>
                                <Newspaper class="size-4 text-slate-400" />
                            </div>

                            <div class="grid gap-2 sm:grid-cols-2">
                                {#each categories as category (category.id)}
                                    <a
                                        href={`/#/category/${category.slug}`}
                                        class="flex items-center gap-3 rounded-2xl border border-transparent px-3 py-2 text-sm text-slate-700 transition hover:border-slate-200 hover:bg-slate-50 dark:text-slate-200 dark:hover:border-white/10 dark:hover:bg-white/5"
                                    >
                                        <span
                                            class="size-2 rounded-full"
                                            style={`background-color: ${category.color ?? '#2563EB'};`}
                                        ></span>
                                        <span class="truncate"
                                            >{category.icon ?? '•'} {category.name}</span
                                        >
                                    </a>
                                {/each}
                            </div>
                        </div>
                    {/if}
                </div>
            {/if}
        </nav>

        <div class="ml-auto flex items-center gap-2">
            <button
                type="button"
                class="inline-flex size-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-sky-700 dark:hover:bg-sky-950/60 dark:hover:text-sky-300"
                onclick={() => {
                    openSearch();
                }}
                aria-label="Открыть поиск"
            >
                <Search class="size-5" />
            </button>

            <button
                type="button"
                class="inline-flex size-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition hover:border-amber-300 hover:bg-amber-50 hover:text-amber-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-amber-700 dark:hover:bg-amber-950/50 dark:hover:text-amber-300"
                onclick={() => {
                    toggleDarkMode();
                }}
                aria-label="Переключить тему"
            >
                {#if appState.darkMode}
                    <SunMedium class="size-5" />
                {:else}
                    <MoonStar class="size-5" />
                {/if}
            </button>

            <a
                href="/#/bookmarks"
                class="relative inline-flex size-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-rose-700 dark:hover:bg-rose-950/50 dark:hover:text-rose-300"
                aria-label="Закладки"
            >
                <Bookmark class="size-5" />
                {#if bookmarkCount > 0}
                    <span
                        class="absolute -right-1 -top-1 flex min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[0.65rem] font-semibold text-white"
                    >
                        {bookmarkCount}
                    </span>
                {/if}
            </a>

            <button
                type="button"
                class="inline-flex size-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition hover:border-slate-300 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10 lg:hidden"
                onclick={() => {
                    toggleSidebar();
                }}
                aria-label="Открыть меню"
            >
                <Menu class="size-5" />
            </button>
        </div>
    </div>

    {#if searchOpen}
        <div class="fixed inset-0 z-50 px-4 py-8">
            <button
                type="button"
                class="absolute inset-0 bg-slate-950/70 backdrop-blur-md"
                onclick={() => {
                    closeSearch();
                }}
                aria-label="Закрыть поиск"
            ></button>

            <div class="relative mx-auto flex min-h-full max-w-4xl items-start justify-center">
                <div class="w-full rounded-[2rem] border border-white/10 bg-white p-6 shadow-2xl shadow-black/30 dark:bg-neutral-950">
                    <div class="mb-5 flex items-center justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.25em] text-sky-600 dark:text-sky-300">
                                Быстрый поиск
                            </div>
                            <h2 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">
                                Найти статью, рубрику или тег
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="inline-flex size-11 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:bg-slate-100 hover:text-slate-900 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                            onclick={() => {
                                closeSearch();
                            }}
                            aria-label="Закрыть поиск"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    <div class="rounded-[1.75rem] bg-slate-100 p-2 dark:bg-white/5">
                        <div class="flex items-center gap-3 rounded-[1.25rem] bg-white px-4 py-3 shadow-sm dark:bg-neutral-900">
                            <Search class="size-5 text-slate-400" />
                            <input
                                bind:this={searchInput}
                                bind:value={searchQuery}
                                type="text"
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
                    </div>

                    <div class="mt-6 grid gap-6 lg:grid-cols-[1.25fr_0.75fr]">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                                    Подсказки
                                </h3>
                                {#if suggestionsLoading}
                                    <span class="text-xs text-slate-400">Обновляем...</span>
                                {/if}
                            </div>

                            {#if searchQuery.trim() && !suggestionsLoading && suggestions.articles.length === 0 && suggestions.categories.length === 0 && suggestions.tags.length === 0}
                                <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400">
                                    Ничего не найдено. Попробуйте другой запрос.
                                </div>
                            {/if}

                            {#if suggestions.articles.length > 0}
                                <div class="rounded-3xl border border-slate-200 p-3 dark:border-white/10">
                                    <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                        Статьи
                                    </div>
                                    <div class="space-y-1">
                                        {#each suggestions.articles as article (article.id)}
                                            <button
                                                type="button"
                                                class="flex w-full items-center justify-between gap-4 rounded-2xl px-3 py-3 text-left transition hover:bg-slate-50 dark:hover:bg-white/5"
                                                onclick={() => {
                                                    navigateTo(`/articles/${article.slug}`);
                                                }}
                                            >
                                                <span class="font-medium text-slate-800 dark:text-slate-100">
                                                    {article.title}
                                                </span>
                                                <span class="shrink-0 text-xs text-slate-400">
                                                    {formatArticleDate(article.published_at)}
                                                </span>
                                            </button>
                                        {/each}
                                    </div>
                                </div>
                            {/if}

                            {#if suggestions.categories.length > 0}
                                <div class="rounded-3xl border border-slate-200 p-4 dark:border-white/10">
                                    <div class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                        Рубрики
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        {#each suggestions.categories as category (category.id)}
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                                onclick={() => {
                                                    navigateTo(`/category/${category.slug}`);
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
                                <div class="rounded-3xl border border-slate-200 p-4 dark:border-white/10">
                                    <div class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                        Теги
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        {#each suggestions.tags as tag (tag.id)}
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-200 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                                                onclick={() => {
                                                    navigateTo(`/tag/${tag.slug}`);
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
                            <div class="rounded-3xl bg-slate-950 p-5 text-white dark:bg-slate-900">
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-300">
                                    Последние запросы
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    {#if recentSearches.length > 0}
                                        {#each recentSearches as item (item)}
                                            <button
                                                type="button"
                                                class="rounded-full border border-white/15 px-3 py-2 text-sm transition hover:border-sky-300 hover:bg-white/10"
                                                onclick={() => {
                                                    searchQuery = item;
                                                    submitSearch();
                                                }}
                                            >
                                                {item}
                                            </button>
                                        {/each}
                                    {:else}
                                        <p class="text-sm text-slate-300">
                                            История поиска появится здесь после первых запросов.
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
                                <span class="block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                    Полный поиск
                                </span>
                                <span class="mt-1 block text-base font-semibold text-slate-900 dark:text-white">
                                    Показать все результаты
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {/if}

    {#if appState.sidebarOpen}
        <div
            class="fixed inset-0 z-[45] bg-slate-950/50 backdrop-blur-sm lg:hidden"
            role="button"
            tabindex="0"
            onclick={() => {
                appState.sidebarOpen = false;
            }}
            onkeydown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    appState.sidebarOpen = false;
                }
            }}
        ></div>

        <aside
            class="fixed inset-y-0 right-0 z-50 w-full max-w-sm overflow-y-auto border-l border-white/10 bg-white px-5 py-5 shadow-2xl shadow-black/20 dark:bg-neutral-950 lg:hidden"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-full bg-slate-900 text-lg text-white dark:bg-white dark:text-slate-900"
                    >
                        🗞️
                    </span>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-sky-600 dark:text-sky-300">
                            Новости
                        </div>
                        <div class="font-semibold text-slate-900 dark:text-white">
                            Меню
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex size-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 dark:border-white/10 dark:text-slate-200"
                    onclick={() => {
                        appState.sidebarOpen = false;
                    }}
                    aria-label="Закрыть меню"
                >
                    <X class="size-5" />
                </button>
            </div>

            <div class="mt-6 space-y-3">
                <button
                    type="button"
                    class="w-full rounded-3xl bg-slate-900 px-4 py-3 text-left text-sm font-medium text-white dark:bg-white dark:text-slate-950"
                    onclick={() => {
                        openSearch();
                    }}
                >
                    Открыть поиск
                </button>

                <a
                    href="/#/bookmarks"
                    class="flex items-center justify-between rounded-3xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 dark:border-white/10 dark:text-slate-200"
                >
                    <span>Закладки</span>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs dark:bg-white/10">
                        {bookmarkCount}
                    </span>
                </a>
            </div>

            <div class="mt-8">
                <div class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                    Категории
                </div>
                <div class="space-y-2">
                    <a
                        href="/#/"
                        class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-white/5"
                    >
                        <span class="size-2 rounded-full bg-slate-400"></span>
                        Все новости
                    </a>

                    {#each categories as category (category.id)}
                        <a
                            href={`/#/category/${category.slug}`}
                            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-white/5"
                        >
                            <span
                                class="size-2 rounded-full"
                                style={`background-color: ${category.color ?? '#2563EB'};`}
                            ></span>
                            <span>{category.icon ?? '•'} {category.name}</span>
                        </a>
                    {/each}
                </div>
            </div>
        </aside>
    {/if}
</header>
