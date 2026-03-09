<script lang="ts">
    import X from 'lucide-svelte/icons/x';
    import { onMount } from 'svelte';
    import { slide } from 'svelte/transition';
    import { showToast } from '@/components/ui/Toast.svelte';
    import { usePolling } from '@/composables/usePolling.js';
    import type { ApiArticleListItem } from '@/features/portal/data/api';
    import * as api from '@/features/portal/data/api';
    import {
        articleUrl,
        homeUrl,
    } from '@/features/portal/routing/publicRoutes';
    import {
        appBreakingNews,
        appInitialized,
        initApp,
        setBreakingNews,
    } from '@/features/portal/state/app.svelte.js';
    import {
        prefersReducedMotion,
        resolveSlideTransition,
    } from '@/lib/motion';

    let dismissed = $state(false);
    let paused = $state(false);
    let hasHydrated = $state(false);
    let motionReady = $state(false);
    let seenBreakingIds = $state<Array<number | string>>([]);

    const breakingNews = $derived($appBreakingNews as ApiArticleListItem[]);
    const canToggleTicker = $derived(motionReady && !$prefersReducedMotion);
    const showStaticHeadlines = $derived(paused || !canToggleTicker);
    const tickerText = $derived(
        breakingNews.map((article) => article.title).join(' | '),
    );
    const tickerTransition = $derived(
        resolveSlideTransition($prefersReducedMotion, {
            duration: 220,
        }),
    );
    const durationSeconds = $derived(
        Math.max(16, Math.ceil(tickerText.length / 10)),
    );
    const breakingPolling = usePolling(
        async (): Promise<ApiArticleListItem[]> => {
            const response = await api.getBreaking();

            return response.data;
        },
        60000,
    );

    function dismissTicker(): void {
        dismissed = true;

        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.setItem('news-portal-breaking-dismissed', 'true');
        }
    }

    async function initializeTicker(): Promise<void> {
        if (!$appInitialized) {
            try {
                await initApp();
            } catch {
                return;
            }
        }

        if (typeof sessionStorage !== 'undefined') {
            dismissed =
                sessionStorage.getItem('news-portal-breaking-dismissed') ===
                'true';
        }
    }

    onMount(() => {
        motionReady = true;
        void initializeTicker();
    });

    function handleTickerToggle(): void {
        if (!canToggleTicker) {
            return;
        }

        paused = !paused;
    }

    $effect(() => {
        const nextItems = breakingPolling.data;

        if (!Array.isArray(nextItems)) {
            return;
        }

        const nextIds = nextItems.map((article) => article.id);
        const hasNewBreaking =
            hasHydrated && nextIds.some((id) => !seenBreakingIds.includes(id));

        setBreakingNews(nextItems);
        seenBreakingIds = nextIds;

        if (hasNewBreaking) {
            showToast('Новые срочные новости', 'warning');
        }

        if (!hasHydrated) {
            hasHydrated = true;
        }
    });

    $effect(() => {
        if (breakingNews.length > 0 && dismissed) {
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.removeItem('news-portal-breaking-dismissed');
            }

            dismissed = false;
        }
    });
</script>

{#if !dismissed && breakingNews.length > 0}
    <div
        class="relative z-30 border-b border-red-300/60 bg-linear-to-r from-red-600 via-rose-600 to-red-700 text-white shadow-lg shadow-red-900/20"
        in:slide={tickerTransition}
        out:slide={tickerTransition}
    >
        <div
            class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-2 lg:px-6"
        >
            <div
                class="shrink-0 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-white"
            >
                🔴 Срочно
            </div>

            <div class="min-w-0 flex-1 overflow-hidden">
                {#if showStaticHeadlines}
                    <div
                        class="flex flex-wrap gap-x-4 gap-y-2 text-sm font-medium"
                    >
                        {#each breakingNews as article, index (article.id)}
                            <a
                                href={article.slug
                                    ? articleUrl(article.slug)
                                    : homeUrl()}
                                class="hover:underline"
                                onclick={(event) => {
                                    event.stopPropagation();
                                }}
                            >
                                {article.title}{index < breakingNews.length - 1
                                    ? ' |'
                                    : ''}
                            </a>
                        {/each}
                    </div>
                {:else}
                    <div
                        class="ticker-track flex w-max items-center gap-5 whitespace-nowrap text-sm font-medium"
                        style={`--ticker-duration: ${durationSeconds}s;`}
                    >
                        {#each [...breakingNews, ...breakingNews] as article, index (`${article.id}-${index}`)}
                            <a
                                href={article.slug
                                    ? articleUrl(article.slug)
                                    : homeUrl()}
                                class="inline-flex items-center gap-5 hover:underline"
                                onclick={(event) => {
                                    event.stopPropagation();
                                }}
                            >
                                <span>{article.title}</span>
                                <span class="text-white/50">|</span>
                            </a>
                        {/each}
                    </div>
                {/if}
            </div>

            <div class="flex shrink-0 items-center gap-2">
                {#if canToggleTicker}
                    <button
                        type="button"
                        class="rounded-full border border-white/15 bg-white/10 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] transition hover:bg-white/20"
                        onclick={handleTickerToggle}
                    >
                        {paused ? 'Старт' : 'Пауза'}
                    </button>
                {/if}

                <button
                    type="button"
                    class="rounded-full border border-white/15 bg-white/10 p-2 transition hover:bg-white/20"
                    onclick={(event) => {
                        event.stopPropagation();
                        dismissTicker();
                    }}
                    aria-label="Скрыть срочные новости"
                >
                    <X class="size-4" />
                </button>
            </div>
        </div>
    </div>
{/if}

<style>
    @keyframes ticker {
        from {
            transform: translateX(0);
        }

        to {
            transform: translateX(-50%);
        }
    }

    .ticker-track {
        animation: ticker var(--ticker-duration) linear infinite;
    }
</style>
