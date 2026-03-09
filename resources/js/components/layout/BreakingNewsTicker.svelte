<script lang="ts">
    import X from 'lucide-svelte/icons/x';
    import { showToast } from '@/components/ui/Toast.svelte';
    import { usePolling } from '@/composables/usePolling.js';
    import * as api from '@/lib/api';
    import { appState, initApp, setBreakingNews } from '@/stores/app.svelte.js';

    type BreakingArticle = {
        id: number | string;
        title: string;
        slug?: string | null;
    };

    let dismissed = $state(false);
    let paused = $state(false);
    let hasHydrated = $state(false);
    let seenBreakingIds = $state<Array<number | string>>([]);

    const breakingNews = $derived(
        (appState.breakingNews ?? []) as BreakingArticle[],
    );
    const tickerText = $derived(
        breakingNews.map((article) => article.title).join(' | '),
    );
    const durationSeconds = $derived(
        Math.max(16, Math.ceil(tickerText.length / 10)),
    );
    const breakingPolling = usePolling(async () => {
        const response = await api.getBreaking();

        return response.data as BreakingArticle[];
    }, 60000);

    function dismissTicker(): void {
        dismissed = true;

        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.setItem('news-portal-breaking-dismissed', 'true');
        }
    }

    $effect(() => {
        void (async () => {
            if (!appState.initialized) {
                try {
                    await initApp();
                } catch {
                    return;
                }
            }
        })();

        if (typeof sessionStorage !== 'undefined') {
            dismissed =
                sessionStorage.getItem('news-portal-breaking-dismissed') ===
                'true';
        }
    });

    $effect(() => {
        const nextItems = breakingPolling.data as BreakingArticle[] | null;

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
        role="button"
        tabindex="0"
        onclick={() => {
            paused = !paused;
        }}
        onkeydown={(event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                paused = !paused;
            }
        }}
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
                {#if paused}
                    <div
                        class="flex flex-wrap gap-x-4 gap-y-2 text-sm font-medium"
                    >
                        {#each breakingNews as article, index (article.id)}
                            <a
                                href={article.slug
                                    ? `/#/articles/${article.slug}`
                                    : '/#/'}
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
                                    ? `/#/articles/${article.slug}`
                                    : '/#/'}
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

            <button
                type="button"
                class="shrink-0 rounded-full border border-white/15 bg-white/10 p-2 transition hover:bg-white/20"
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
