<script lang="ts">
    import { onMount } from 'svelte';
    import Skeleton from '@/components/ui/skeleton/Skeleton.svelte';
    import type { StatsChartPayload } from '@/features/portal';
    import {
        articlesChartFrame,
        buildArticlesSeries,
        formatLabel,
        formatNumber,
    } from '@/features/stats/lib/stats';
    import type { HoveredPoint } from '@/features/stats/lib/stats';

    interface Props {
        loading: boolean;
        chartData: StatsChartPayload | null;
    }

    let { loading, chartData }: Props = $props();

    let hoveredBar = $state<HoveredPoint | null>(null);

    const articlesSeries = $derived.by(() => buildArticlesSeries(chartData));

    function showBarTooltip(point: HoveredPoint): void {
        hoveredBar = point;
    }

    function hideBarTooltip(): void {
        hoveredBar = null;
    }

    onMount(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const resetHover = (): void => {
            hoveredBar = null;
        };

        window.addEventListener('scroll', resetHover, { passive: true });

        return () => {
            window.removeEventListener('scroll', resetHover);
        };
    });
</script>

<article
    class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900"
>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div
                class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
            >
                Публикации
            </div>
            <h2
                class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
            >
                Статьи по дням
            </h2>
        </div>

        {#if chartData?.series?.length}
            <div class="flex flex-wrap gap-3">
                {#each chartData.series as series (series.id)}
                    <div
                        class="inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400"
                    >
                        <span
                            class="size-3 rounded-full"
                            style={`background-color: ${series.color}`}
                        ></span>
                        {series.name}
                    </div>
                {/each}
            </div>
        {/if}
    </div>

    {#if loading}
        <Skeleton class="mt-6 h-[320px] w-full rounded-[1.5rem]" />
    {:else}
        <div
            class="relative mt-6 overflow-hidden rounded-[1.5rem] bg-slate-50 p-4 dark:bg-white/5"
        >
            <svg
                viewBox={`0 0 ${articlesChartFrame.width} ${articlesChartFrame.height}`}
                class="h-[320px] w-full"
                role="img"
                aria-label="График публикаций по дням"
            >
                {#each articlesSeries as bar, index (index)}
                    {#each bar.segments as segment (`${segment.name}-${segment.value}-${index}`)}
                        <rect
                            x={segment.x}
                            y={segment.y}
                            width={segment.width}
                            height={segment.height}
                            rx="6"
                            fill={segment.color}
                            role="img"
                            aria-label={`${bar.label}: ${segment.name}, ${formatNumber(segment.value)} публикаций`}
                            onmouseenter={() => {
                                showBarTooltip({
                                    label: `${bar.label} — ${segment.name}`,
                                    value: segment.value,
                                    left: segment.x + segment.width / 2,
                                    top: segment.y,
                                });
                            }}
                            onmouseleave={hideBarTooltip}
                        />
                    {/each}

                    {#if index % Math.max(1, Math.ceil(articlesSeries.length / 6)) === 0 || index === articlesSeries.length - 1}
                        <text
                            x={bar.center}
                            y={articlesChartFrame.height - 8}
                            text-anchor="middle"
                            class="fill-slate-400 text-[10px]"
                        >
                            {formatLabel(bar.label)}
                        </text>
                    {/if}
                {/each}
            </svg>

            {#if hoveredBar}
                <div
                    class="pointer-events-none absolute z-10 rounded-2xl bg-slate-950 px-3 py-2 text-xs text-white shadow-lg"
                    style={`left: calc(${(hoveredBar.left / articlesChartFrame.width) * 100}% - 3rem); top: calc(${(hoveredBar.top / articlesChartFrame.height) * 100}% - 3.75rem);`}
                >
                    <div class="font-semibold">
                        {formatNumber(hoveredBar.value)} публикаций
                    </div>
                    <div class="mt-1 text-slate-300">{hoveredBar.label}</div>
                </div>
            {/if}
        </div>
    {/if}
</article>
