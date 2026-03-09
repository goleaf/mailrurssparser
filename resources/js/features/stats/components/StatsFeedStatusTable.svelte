<script lang="ts">
    import Skeleton from '@/components/ui/skeleton/Skeleton.svelte';
    import type { StatsFeedPerformance } from '@/features/portal';
    import {
        feedStatus,
        formatNumber,
        formatRelativeDate,
    } from '@/features/stats/lib/stats';
    import { cn } from '@/lib/utils';

    interface Props {
        loading: boolean;
        feeds: StatsFeedPerformance[];
    }

    let { loading, feeds }: Props = $props();
</script>

<article
    class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900"
>
    <div
        class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
    >
        RSS-ленты
    </div>
    <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">
        Статус парсинга
    </h2>

    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full table-auto text-sm">
            <thead class="text-left text-slate-400">
                <tr class="border-b border-slate-200 dark:border-white/10">
                    <th class="pb-3 pr-4 font-medium">Лента</th>
                    <th class="pb-3 pr-4 font-medium">Последний запуск</th>
                    <th class="pb-3 pr-4 font-medium">Сегодня</th>
                    <th class="pb-3 pr-4 font-medium">Всего</th>
                    <th class="pb-3 font-medium">Статус</th>
                </tr>
            </thead>
            <tbody>
                {#if loading}
                    {#each Array.from({ length: 5 }) as _, index (index)}
                        <tr
                            class="border-b border-slate-100 dark:border-white/5"
                        >
                            <td class="py-4 pr-4"
                                ><Skeleton class="h-4 w-40" /></td
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
                            <td class="py-4"><Skeleton class="h-4 w-20" /></td>
                        </tr>
                    {/each}
                {:else}
                    {#each feeds as feed (feed.id)}
                        {@const status = feedStatus(feed)}
                        <tr
                            class="border-b border-slate-100 dark:border-white/5"
                        >
                            <td class="py-4 pr-4">
                                <div
                                    class="font-medium text-slate-900 dark:text-white"
                                >
                                    {feed.title}
                                </div>
                                <div
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {feed.category ?? 'Без категории'}
                                </div>
                            </td>
                            <td
                                class="py-4 pr-4 text-slate-600 dark:text-slate-300"
                            >
                                {formatRelativeDate(feed.last_run?.started_at)}
                            </td>
                            <td
                                class="py-4 pr-4 text-slate-600 dark:text-slate-300"
                            >
                                {formatNumber(feed.today_articles_count)}
                            </td>
                            <td
                                class="py-4 pr-4 text-slate-600 dark:text-slate-300"
                            >
                                {formatNumber(feed.total_articles)}
                            </td>
                            <td class="py-4">
                                <span
                                    class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-xs font-medium text-slate-700 dark:bg-white/5 dark:text-slate-300"
                                >
                                    <span
                                        class={cn(
                                            'size-2 rounded-full',
                                            status.dot,
                                        )}
                                    ></span>
                                    {status.label}
                                </span>
                            </td>
                        </tr>
                    {/each}
                {/if}
            </tbody>
        </table>
    </div>
</article>
