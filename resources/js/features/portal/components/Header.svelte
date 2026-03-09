<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import Bookmark from 'lucide-svelte/icons/bookmark';
    import Menu from 'lucide-svelte/icons/menu';
    import MoonStar from 'lucide-svelte/icons/moon-star';
    import Search from 'lucide-svelte/icons/search';
    import SunMedium from 'lucide-svelte/icons/sun-medium';
    import X from 'lucide-svelte/icons/x';
    import { onMount } from 'svelte';
    import { usePolling } from '@/composables/usePolling.js';
    import { resetFilters } from '@/features/articles/state/articles.svelte.js';
    import {
        bookmarkCount,
        loadBookmarks,
    } from '@/features/bookmarks/state/bookmarks.svelte.js';
    import HeaderMenuBlock from '@/features/portal/components/HeaderMenuBlock.svelte';
    import type {
        ApiCategory,
        StatsOverview,
    } from '@/features/portal/data/api';
    import * as api from '@/features/portal/data/api';
    import { currentPath } from '@/features/portal/routing/currentUrl';
    import {
        bookmarksUrl,
        categoryUrl,
        homeUrl,
    } from '@/features/portal/routing/publicRoutes';
    import {
        appCategories,
        appDarkMode,
        appInitialized,
        appSidebarOpen,
        initApp,
        setSidebarOpen,
        syncDarkModeState,
        toggleDarkMode,
        toggleSidebar,
    } from '@/features/portal/state/app.svelte.js';
    import { cn } from '@/lib/utils';

    type SearchModalComponentType =
        typeof import('@/features/search/components/SearchModal.svelte').default;

    let hasShadow = $state(false);
    let searchOpen = $state(false);
    let initialArticleCount = $state<number | null>(null);
    let SearchModalComponent = $state<SearchModalComponentType | null>(null);
    let searchModalLoader: Promise<void> | null = null;

    const categories = $derived($appCategories as ApiCategory[]);
    const totalBookmarks = $derived($bookmarkCount);
    const currentPathname = $derived(currentPath(page.url ?? homeUrl()));
    const statsPolling = usePolling(async (): Promise<StatsOverview> => {
        const response = await api.getStats();

        return response.data;
    }, 300000);
    const newArticleDelta = $derived.by(() => {
        const currentTotal = Number(statsPolling.data?.articles?.total ?? 0);

        if (initialArticleCount === null) {
            return 0;
        }

        return Math.max(0, currentTotal - initialArticleCount);
    });

    async function ensureSearchModalLoaded(): Promise<void> {
        if (SearchModalComponent) {
            return;
        }

        if (!searchModalLoader) {
            searchModalLoader = import(
                '@/features/search/components/SearchModal.svelte'
            )
                .then((module) => {
                    SearchModalComponent = module.default;
                })
                .finally(() => {
                    searchModalLoader = null;
                });
        }

        await searchModalLoader;
    }

    function prefetchSearchModal(): void {
        void ensureSearchModalLoaded();
    }

    function openSearch(): void {
        searchOpen = true;
        setSidebarOpen(false);
        prefetchSearchModal();
    }

    function closeSearch(): void {
        searchOpen = false;
    }

    function goHome(): void {
        resetFilters();
        setSidebarOpen(false);
    }

    async function initializeHeaderState(): Promise<void> {
        if (!$appInitialized) {
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
    }

    onMount(() => {
        void initializeHeaderState();

        if (typeof document !== 'undefined') {
            syncDarkModeState(
                document.documentElement.classList.contains('dark'),
            );
        }

        if (typeof window === 'undefined') {
            return;
        }

        const syncViewportState = (): void => {
            hasShadow = window.scrollY > 12;
        };

        syncViewportState();
        window.addEventListener('scroll', syncViewportState, {
            passive: true,
        });

        return () => {
            window.removeEventListener('scroll', syncViewportState);
        };
    });

    $effect(() => {
        const totalArticles = Number(statsPolling.data?.articles?.total ?? 0);

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
        <div
            class="flex items-center gap-4 rounded-[2rem] border border-slate-200/80 bg-white/85 px-3 py-3 shadow-[0_18px_60px_-35px_rgba(15,23,42,0.35)] backdrop-blur dark:border-white/10 dark:bg-slate-950/72"
        >
            <a
                href={homeUrl()}
                onclick={goHome}
                class="group flex shrink-0 items-center gap-3 rounded-[1.6rem] border border-slate-200 bg-[linear-gradient(135deg,rgba(240,249,255,0.95),rgba(255,255,255,0.92))] px-3 py-2.5 text-slate-900 transition hover:border-sky-300 hover:shadow-md dark:border-white/10 dark:bg-[linear-gradient(135deg,rgba(15,23,42,0.92),rgba(2,6,23,0.94))] dark:text-slate-50"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-[1.15rem] bg-[linear-gradient(135deg,#0f172a,#0369a1)] text-lg text-white shadow-sm dark:bg-[linear-gradient(135deg,#e2e8f0,#7dd3fc)] dark:text-slate-950"
                >
                    🗞️
                </span>
                <div class="min-w-0">
                    <div
                        class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-sky-700 dark:text-sky-300"
                    >
                        Editorial Desk
                    </div>
                    <div
                        class="flex items-center gap-2 text-[0.95rem] font-semibold"
                    >
                        <span>Новости</span>
                        {#if newArticleDelta > 0}
                            <span
                                class="rounded-full bg-emerald-500 px-2 py-0.5 text-[0.65rem] font-semibold text-white shadow-sm"
                            >
                                +{newArticleDelta}
                            </span>
                        {/if}
                    </div>
                </div>
            </a>

            <HeaderMenuBlock
                {categories}
                currentPath={currentPathname}
                onHome={goHome}
            />

            <div class="ml-auto flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex size-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-sky-700 dark:hover:bg-sky-950/60 dark:hover:text-sky-300"
                    onclick={openSearch}
                    onmouseenter={prefetchSearchModal}
                    onfocus={prefetchSearchModal}
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
                    {#if $appDarkMode}
                        <SunMedium class="size-5" />
                    {:else}
                        <MoonStar class="size-5" />
                    {/if}
                </button>

                <a
                    href={bookmarksUrl()}
                    class="relative inline-flex size-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-rose-700 dark:hover:bg-rose-950/50 dark:hover:text-rose-300"
                    aria-label="Закладки"
                >
                    <Bookmark class="size-5" />
                    {#if totalBookmarks > 0}
                        <span
                            class="absolute -right-1 -top-1 flex min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[0.65rem] font-semibold text-white"
                        >
                            {totalBookmarks}
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

    {#if $appSidebarOpen}
        <div
            class="fixed inset-0 z-[45] bg-slate-950/50 backdrop-blur-sm lg:hidden"
            role="button"
            tabindex="0"
            onclick={() => {
                setSidebarOpen(false);
            }}
            onkeydown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    setSidebarOpen(false);
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
                        <div
                            class="text-xs font-semibold uppercase tracking-[0.22em] text-sky-600 dark:text-sky-300"
                        >
                            Новости
                        </div>
                        <div
                            class="font-semibold text-slate-900 dark:text-white"
                        >
                            Меню
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex size-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 dark:border-white/10 dark:text-slate-200"
                    onclick={() => {
                        setSidebarOpen(false);
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
                    onclick={openSearch}
                    onmouseenter={prefetchSearchModal}
                    onfocus={prefetchSearchModal}
                >
                    Открыть поиск
                </button>

                <a
                    href={bookmarksUrl()}
                    class="flex items-center justify-between rounded-3xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 dark:border-white/10 dark:text-slate-200"
                >
                    <span>Закладки</span>
                    <span
                        class="rounded-full bg-slate-100 px-2 py-1 text-xs dark:bg-white/10"
                    >
                        {totalBookmarks}
                    </span>
                </a>
            </div>

            <div class="mt-8">
                <div
                    class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-slate-400"
                >
                    Категории
                </div>
                <div class="space-y-2">
                    <a
                        href={homeUrl()}
                        onclick={goHome}
                        class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-white/5"
                    >
                        <span class="size-2 rounded-full bg-slate-400"></span>
                        Все новости
                    </a>

                    {#each categories as category (category.id)}
                        <a
                            href={categoryUrl(category.slug)}
                            onclick={() => {
                                setSidebarOpen(false);
                            }}
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

{#if SearchModalComponent}
    <SearchModalComponent
        open={searchOpen}
        onClose={closeSearch}
    />
{/if}
