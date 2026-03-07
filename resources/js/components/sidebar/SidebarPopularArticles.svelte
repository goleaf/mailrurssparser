<script lang="ts">
    import ArticleCardCompact from '@/components/article/ArticleCardCompact.svelte';
    import * as api from '@/lib/api';
    import { cn } from '@/lib/utils';

    type PopularArticle = {
        article_id?: number | string;
        title: string;
        slug: string;
        published_at?: string | null;
        image_url?: string | null;
        category?: {
            icon?: string | null;
        } | null;
    };

    const periods = [
        { key: 'today', label: 'Сегодня' },
        { key: 'week', label: 'Неделя' },
        { key: 'month', label: 'Месяц' },
    ] as const;

    let selectedPeriod = $state<(typeof periods)[number]['key']>('week');
    let articles = $state<PopularArticle[]>([]);
    let loading = $state(false);

    $effect(() => {
        const period = selectedPeriod;
        let cancelled = false;

        loading = true;

        void api
            .getPopular({
                period,
                limit: 5,
            })
            .then((response) => {
                if (!cancelled) {
                    articles = response.data?.data ?? [];
                }
            })
            .catch(() => {
                if (!cancelled) {
                    articles = [];
                }
            })
            .finally(() => {
                if (!cancelled) {
                    loading = false;
                }
            });

        return () => {
            cancelled = true;
        };
    });
</script>

<aside class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900">
    <div class="mb-4 text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 dark:text-sky-300">
        📊 Популярное
    </div>

    <div class="mb-4 flex gap-2">
        {#each periods as period (period.key)}
            <button
                type="button"
                class={cn(
                    'rounded-full px-3 py-2 text-xs font-medium transition',
                    selectedPeriod === period.key
                        ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                )}
                onclick={() => {
                    selectedPeriod = period.key;
                }}
            >
                {period.label}
            </button>
        {/each}
    </div>

    <div class="space-y-3">
        {#if loading}
            <div class="space-y-3">
                {#each Array.from({ length: 3 }) as _, index (`loading-${index}`)}
                    <div class="h-26 animate-pulse rounded-2xl bg-slate-100 dark:bg-white/5"></div>
                {/each}
            </div>
        {:else if articles.length === 0}
            <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500 dark:border-white/10 dark:text-slate-400">
                Пока нет данных по популярным статьям.
            </div>
        {:else}
            {#each articles as article (article.article_id ?? article.slug)}
                <ArticleCardCompact {article} />
            {/each}
        {/if}
    </div>
</aside>
