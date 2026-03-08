<script lang="ts">
    import Bookmark from 'lucide-svelte/icons/bookmark';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import Menu from 'lucide-svelte/icons/menu';
    import MoonStar from 'lucide-svelte/icons/moon-star';
    import Newspaper from 'lucide-svelte/icons/newspaper';
    import Search from 'lucide-svelte/icons/search';
    import SunMedium from 'lucide-svelte/icons/sun-medium';
    import X from 'lucide-svelte/icons/x';
    import SearchModal from '@/components/SearchModal.svelte';
    import { usePolling } from '@/composables/usePolling.js';
    import * as api from '@/lib/api';
    import { cn } from '@/lib/utils';
    import { appState, initApp, toggleDarkMode, toggleSidebar } from '@/stores/app.svelte.js';
    import { resetFilters } from '@/stores/articles.svelte.js';
    import { bookmarkIds, loadBookmarks } from '@/stores/bookmarks.svelte.js';

    type Category = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
    };

    let currentHash = $state('#/');
    let hasShadow = $state(false);
    let moreMenuOpen = $state(false);
    let searchOpen = $state(false);
    let initialArticleCount = $state<number | null>(null);

    const categories = $derived((appState.categories ?? []) as Category[]);
    const featuredCategories = $derived(categories.slice(0, 6));
    const bookmarkCount = $derived(bookmarkIds.length);
    const statsPolling = usePolling(async () => {
        const response = await api.getStats();

        return response.data ?? null;
    }, 300000);
    const newArticleDelta = $derived.by(() => {
        const stats = statsPolling.data as
            | { articles?: { total?: number | null } }
            | null;
        const currentTotal = Number(stats?.articles?.total ?? 0);

        if (initialArticleCount === null) {
            return 0;
        }

        return Math.max(0, currentTotal - initialArticleCount);
    });

    function openSearch(): void {
        searchOpen = true;
        appState.sidebarOpen = false;
    }

    function isCategoryActive(slug: string): boolean {
        return currentHash.startsWith(`#/category/${slug}`);
    }

    function goHome(): void {
        resetFilters();
        appState.sidebarOpen = false;
        moreMenuOpen = false;
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
    });

    $effect(() => {
        const stats = statsPolling.data as
            | { articles?: { total?: number | null } }
            | null;
        const totalArticles = Number(stats?.articles?.total ?? 0);

        if (!Number.isFinite(totalArticles) || totalArticles <= 0) {
            return;
        }

        if (initialArticleCount === null) {
            initialArticleCount = totalArticles;
        }
    });
</script>

<header
    class={cn(
        'sticky top-0 z-40 border-b border-black/5 bg-white/78 backdrop-blur-xl transition-shadow duration-300 dark:border-white/10 dark:bg-neutral-950/80',
        hasShadow && 'shadow-[0_18px_50px_-30px_rgba(15,23,42,0.45)]',
    )}
>
    <div class="mx-auto max-w-7xl px-4 py-3 lg:px-6">
        <div class="flex items-center gap-4 rounded-[2rem] border border-slate-200/80 bg-white/85 px-3 py-3 shadow-[0_18px_60px_-35px_rgba(15,23,42,0.35)] backdrop-blur dark:border-white/10 dark:bg-slate-950/72">
        <a
            href="/#/"
            onclick={goHome}
            class="group flex shrink-0 items-center gap-3 rounded-[1.6rem] border border-slate-200 bg-[linear-gradient(135deg,rgba(240,249,255,0.95),rgba(255,255,255,0.92))] px-3 py-2.5 text-slate-900 transition hover:border-sky-300 hover:shadow-md dark:border-white/10 dark:bg-[linear-gradient(135deg,rgba(15,23,42,0.92),rgba(2,6,23,0.94))] dark:text-slate-50"
        >
            <span
                class="flex size-11 items-center justify-center rounded-[1.15rem] bg-[linear-gradient(135deg,#0f172a,#0369a1)] text-lg text-white shadow-sm dark:bg-[linear-gradient(135deg,#e2e8f0,#7dd3fc)] dark:text-slate-950"
            >
                🗞️
            </span>
            <div class="min-w-0">
                <div class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-sky-700 dark:text-sky-300">
                    Editorial Desk
                </div>
                <div class="flex items-center gap-2 text-[0.95rem] font-semibold">
                    <span>Новости</span>
                    {#if newArticleDelta > 0}
                        <span class="rounded-full bg-emerald-500 px-2 py-0.5 text-[0.65rem] font-semibold text-white shadow-sm">
                            +{newArticleDelta}
                        </span>
                    {/if}
                </div>
            </div>
        </a>

        <nav class="hidden min-w-0 flex-1 items-center gap-2 lg:flex">
            <a
                href="/#/"
                onclick={goHome}
                class={cn(
                    'rounded-full px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white',
                    currentHash === '#/' &&
                        'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-950',
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
                            'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-950',
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
                            class="absolute left-0 top-full mt-3 w-84 rounded-[2rem] border border-slate-200 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.96))] p-4 shadow-2xl shadow-slate-900/15 dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.98),rgba(2,6,23,0.96))] dark:shadow-black/50"
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
    </div>

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
            class="fixed inset-y-0 right-0 z-50 w-full max-w-sm overflow-y-auto border-l border-white/10 bg-[linear-gradient(180deg,rgba(255,255,255,0.99),rgba(248,250,252,0.98))] px-5 py-5 shadow-2xl shadow-black/20 dark:bg-[linear-gradient(180deg,rgba(2,6,23,0.98),rgba(15,23,42,0.98))] lg:hidden"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-[1rem] bg-[linear-gradient(135deg,#0f172a,#0369a1)] text-lg text-white dark:bg-[linear-gradient(135deg,#e2e8f0,#7dd3fc)] dark:text-slate-900"
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
                        onclick={goHome}
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

<SearchModal bind:open={searchOpen} />
