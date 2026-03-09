<script lang="ts">
    import { createEventDispatcher } from 'svelte';
    import Skeleton from '@/components/ui/skeleton/Skeleton.svelte';
    import { formatNumber, popularPeriods } from '@/features/stats/lib/stats';
    import type {
        PopularPeriod,
        StatsPopularRowView,
    } from '@/features/stats/lib/stats';
    import { cn } from '@/lib/utils';

    interface Props {
        loading: boolean;
        rows: StatsPopularRowView[];
        selectedPeriod: PopularPeriod;
    }

    const dispatch = createEventDispatcher<{
        periodchange: PopularPeriod;
    }>();

    let { loading, rows, selectedPeriod }: Props = $props();

    function handlePeriodClick(event: Event): void {
        const period = (event.currentTarget as HTMLButtonElement).dataset
            .period as PopularPeriod | undefined;

        if (!period || period === selectedPeriod) {
            return;
        }

        dispatch('periodchange', period);
    }
</script>

<article
    class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900"
>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div
                class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
            >
                Популярное
            </div>
            <h2
                class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
            >
                Самые читаемые статьи
            </h2>
        </div>

        <div class="flex flex-wrap gap-2">
            {#each popularPeriods as period (period.key)}
                <button
                    type="button"
                    data-period={period.key}
                    class={cn(
                        'rounded-full px-4 py-2 text-sm font-medium transition',
                        selectedPeriod === period.key
                            ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                    )}
                    onclick={handlePeriodClick}
                >
                    {period.label}
                </button>
            {/each}
        </div>
    </div>

    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full table-auto text-sm">
            <thead class="text-left text-slate-400">
                <tr class="border-b border-slate-200 dark:border-white/10">
                    <th class="pb-3 pr-4 font-medium">#</th>
                    <th class="pb-3 pr-4 font-medium">Статья</th>
                    <th class="pb-3 pr-4 font-medium">Категория</th>
                    <th class="pb-3 pr-4 font-medium">Просмотры</th>
                    <th class="pb-3 pr-4 font-medium">Шеры</th>
                    <th class="pb-3 font-medium">Закладки</th>
                </tr>
            </thead>
            <tbody>
                {#if loading}
                    {#each Array.from({ length: 5 }) as _, index (index)}
                        <tr
                            class="border-b border-slate-100 dark:border-white/5"
                        >
                            <td class="py-4 pr-4"
                                ><Skeleton class="h-4 w-6" /></td
                            >
                            <td class="py-4 pr-4"
                                ><Skeleton class="h-4 w-48" /></td
                            >
                            <td class="py-4 pr-4"
                                ><Skeleton class="h-4 w-20" /></td
                            >
                            <td class="py-4 pr-4"
                                ><Skeleton class="h-4 w-12" /></td
                            >
                            <td class="py-4 pr-4"
                                ><Skeleton class="h-4 w-12" /></td
                            >
                            <td class="py-4"><Skeleton class="h-4 w-12" /></td>
                        </tr>
                    {/each}
                {:else}
                    {#each rows as row, index (row.article_id)}
                        <tr
                            class="border-b border-slate-100 dark:border-white/5"
                        >
                            <td
                                class="py-4 pr-4 font-semibold text-slate-500 dark:text-slate-400"
                            >
                                {index + 1}
                            </td>
                            <td class="py-4 pr-4">
                                <a
                                    href={row.article_href}
                                    class="font-medium text-slate-900 transition hover:text-sky-600 dark:text-white dark:hover:text-sky-300"
                                >
                                    {row.title}
                                </a>
                                {#if row.change_percent !== null && row.change_percent !== undefined}
                                    <div
                                        class={cn(
                                            'mt-1 text-xs',
                                            row.change_percent >= 0
                                                ? 'text-emerald-600 dark:text-emerald-300'
                                                : 'text-rose-600 dark:text-rose-300',
                                        )}
                                    >
                                        {row.change_percent >= 0
                                            ? '+'
                                            : ''}{row.change_percent}% к
                                        прошлому периоду
                                    </div>
                                {/if}
                            </td>
                            <td
                                class="py-4 pr-4 text-slate-600 dark:text-slate-300"
                            >
                                {row.category ?? 'Без категории'}
                            </td>
                            <td
                                class="py-4 pr-4 text-slate-600 dark:text-slate-300"
                            >
                                {formatNumber(row.view_count)}
                            </td>
                            <td
                                class="py-4 pr-4 text-slate-600 dark:text-slate-300"
                            >
                                {formatNumber(row.shares_count)}
                            </td>
                            <td class="py-4 text-slate-600 dark:text-slate-300">
                                {formatNumber(row.bookmarks_count)}
                            </td>
                        </tr>
                    {/each}
                {/if}
            </tbody>
        </table>
    </div>
</article>
