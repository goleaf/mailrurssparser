<script lang="ts">
    import Skeleton from '@/components/ui/skeleton/Skeleton.svelte';
    import type { StatsCategoryBreakdownView } from '@/features/stats/lib/stats';
    import { formatNumber } from '@/features/stats/lib/stats';

    interface Props {
        loading: boolean;
        items: StatsCategoryBreakdownView[];
    }

    let { loading, items }: Props = $props();
</script>

<article
    class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900"
>
    <div
        class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
    >
        Категории
    </div>
    <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">
        Доля публикаций
    </h2>

    {#if loading}
        <div class="mt-6 space-y-4">
            {#each Array.from({ length: 5 }) as _, index (index)}
                <div>
                    <Skeleton class="h-4 w-28" />
                    <Skeleton class="mt-3 h-3 w-full rounded-full" />
                </div>
            {/each}
        </div>
    {:else}
        <div class="mt-6 space-y-4">
            {#each items as item (item.id)}
                <div>
                    <div
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <div class="font-medium text-slate-900 dark:text-white">
                            {item.name}
                        </div>
                        <div class="text-slate-500 dark:text-slate-400">
                            {formatNumber(item.article_count)} • {item.percentage}%
                        </div>
                    </div>
                    <div
                        class="mt-2 h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-white/5"
                    >
                        <div
                            class="h-full rounded-full"
                            style={`width: ${Math.max(item.percentage, 3)}%; background-color: ${item.color ?? '#3B82F6'};`}
                        ></div>
                    </div>
                    {#if item.top_article && item.top_article_href}
                        <a
                            href={item.top_article_href}
                            class="mt-2 block text-xs text-slate-500 transition hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"
                        >
                            Топ материал: {item.top_article.title}
                        </a>
                    {/if}
                </div>
            {/each}
        </div>
    {/if}
</article>
