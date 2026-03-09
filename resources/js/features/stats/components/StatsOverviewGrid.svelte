<script lang="ts">
    import Activity from 'lucide-svelte/icons/activity';
    import TrendingDown from 'lucide-svelte/icons/trending-down';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Skeleton from '@/components/ui/skeleton/Skeleton.svelte';
    import type { StatsOverviewCard } from '@/features/stats/lib/stats';
    import { formatNumber } from '@/features/stats/lib/stats';
    import { cn } from '@/lib/utils';

    interface Props {
        loading: boolean;
        cards: StatsOverviewCard[];
    }

    let { loading, cards }: Props = $props();
</script>

<section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    {#if loading}
        {#each Array.from({ length: 4 }) as _, index (index)}
            <div
                class="rounded-[1.75rem] border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-900"
            >
                <Skeleton class="h-5 w-24" />
                <Skeleton class="mt-5 h-10 w-32" />
                <Skeleton class="mt-4 h-4 w-40" />
            </div>
        {/each}
    {:else}
        {#each cards as card (card.key)}
            <article
                class="rounded-[1.9rem] border border-slate-200 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(248,250,252,0.92))] p-5 shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.82))]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div
                            class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                        >
                            {card.title}
                        </div>
                        <div
                            class="mt-4 text-4xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            {formatNumber(card.value)}
                        </div>
                    </div>
                    <div
                        class="rounded-2xl bg-slate-100 p-3 text-slate-700 dark:bg-white/5 dark:text-slate-200"
                    >
                        <card.icon class="size-5" />
                    </div>
                </div>

                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                    {card.subtitle}
                </p>

                <div
                    class={cn(
                        'mt-4 inline-flex items-center gap-2 rounded-full px-3 py-2 text-xs font-medium',
                        card.trend.direction === 'up'
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                            : card.trend.direction === 'down'
                              ? 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'
                              : 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300',
                    )}
                >
                    {#if card.trend.direction === 'up'}
                        <TrendingUp class="size-4" />
                    {:else if card.trend.direction === 'down'}
                        <TrendingDown class="size-4" />
                    {:else}
                        <Activity class="size-4" />
                    {/if}
                    {card.trend.label}
                </div>
            </article>
        {/each}
    {/if}
</section>
