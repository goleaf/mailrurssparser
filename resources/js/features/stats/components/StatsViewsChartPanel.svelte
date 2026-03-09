<script lang="ts">
    import { createEventDispatcher, onMount } from 'svelte';
    import Skeleton from '@/components/ui/skeleton/Skeleton.svelte';
    import type { StatsChartPayload } from '@/features/portal';
    import {
        buildLineChartMetrics,
        chartPeriods,
        formatLabel,
        formatNumber,
        lineChartFrame,
    } from '@/features/stats/lib/stats';
    import type { ChartPeriod, HoveredPoint } from '@/features/stats/lib/stats';
    import { cn } from '@/lib/utils';

    interface Props {
        loading: boolean;
        chartData: StatsChartPayload | null;
        selectedPeriod: ChartPeriod;
    }

    const dispatch = createEventDispatcher<{
        periodchange: ChartPeriod;
    }>();

    let { loading, chartData, selectedPeriod }: Props = $props();

    let hoveredPoint = $state<HoveredPoint | null>(null);

    const lineMetrics = $derived.by(() => buildLineChartMetrics(chartData));
    const chartTotal = $derived(
        (chartData?.data ?? []).reduce((sum, value) => sum + value, 0),
    );

    function handlePeriodClick(event: Event): void {
        const period = (event.currentTarget as HTMLButtonElement).dataset
            .period as ChartPeriod | undefined;

        if (!period || period === selectedPeriod) {
            return;
        }

        dispatch('periodchange', period);
    }

    function showPointTooltip(point: HoveredPoint): void {
        hoveredPoint = point;
    }

    function hidePointTooltip(): void {
        hoveredPoint = null;
    }

    onMount(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const resetHover = (): void => {
            hoveredPoint = null;
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
                Просмотры
            </div>
            <h2
                class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
            >
                Динамика за период
            </h2>
        </div>

        <div class="flex flex-wrap gap-2">
            {#each chartPeriods as period (period.key)}
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

    {#if loading}
        <Skeleton class="mt-6 h-[320px] w-full rounded-[1.5rem]" />
    {:else}
        <div
            class="relative mt-6 overflow-hidden rounded-[1.5rem] bg-slate-50 p-4 dark:bg-white/5"
        >
            <svg
                viewBox={`0 0 ${lineChartFrame.width} ${lineChartFrame.height}`}
                class="h-[320px] w-full"
                role="img"
                aria-label="График просмотров"
            >
                {#each lineMetrics.yTicks as tick (tick)}
                    {@const y =
                        lineChartFrame.padding.top +
                        lineMetrics.height -
                        ((tick - lineMetrics.minValue) /
                            Math.max(
                                lineMetrics.maxValue - lineMetrics.minValue,
                                1,
                            )) *
                            lineMetrics.height}
                    <line
                        x1={lineChartFrame.padding.left}
                        x2={lineChartFrame.width - lineChartFrame.padding.right}
                        y1={y}
                        y2={y}
                        stroke="rgba(148, 163, 184, 0.22)"
                        stroke-dasharray="4 6"
                    />
                    <text
                        x={lineChartFrame.padding.left}
                        y={y - 6}
                        class="fill-slate-400 text-[10px]"
                    >
                        {formatNumber(tick)}
                    </text>
                {/each}

                <polyline
                    fill="none"
                    stroke="url(#viewsGradient)"
                    stroke-width="4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    points={lineMetrics.polyline}
                />

                <defs>
                    <linearGradient
                        id="viewsGradient"
                        x1="0%"
                        x2="100%"
                        y1="0%"
                        y2="0%"
                    >
                        <stop offset="0%" stop-color="#0EA5E9" />
                        <stop offset="100%" stop-color="#2563EB" />
                    </linearGradient>
                </defs>

                {#each lineMetrics.points as point (point.label + point.value)}
                    <g>
                        <circle
                            cx={point.x}
                            cy={point.y}
                            r="6"
                            fill="#fff"
                            stroke="#0EA5E9"
                            stroke-width="3"
                            role="img"
                            aria-label={`${formatLabel(point.label)}: ${formatNumber(point.value)} просмотров`}
                            onmouseenter={() => {
                                showPointTooltip({
                                    label: point.label,
                                    value: point.value,
                                    left: point.x,
                                    top: point.y,
                                });
                            }}
                            onmouseleave={hidePointTooltip}
                        />
                    </g>
                {/each}

                {#each lineMetrics.points as point, index (index)}
                    {#if index % Math.max(1, Math.ceil(lineMetrics.points.length / 6)) === 0 || index === lineMetrics.points.length - 1}
                        <text
                            x={point.x}
                            y={lineChartFrame.height - 8}
                            text-anchor="middle"
                            class="fill-slate-400 text-[10px]"
                        >
                            {formatLabel(point.label)}
                        </text>
                    {/if}
                {/each}
            </svg>

            {#if hoveredPoint}
                <div
                    class="pointer-events-none absolute z-10 rounded-2xl bg-slate-950 px-3 py-2 text-xs text-white shadow-lg"
                    style={`left: calc(${(hoveredPoint.left / lineChartFrame.width) * 100}% - 3rem); top: calc(${(hoveredPoint.top / lineChartFrame.height) * 100}% - 3.75rem);`}
                >
                    <div class="font-semibold">
                        {formatNumber(hoveredPoint.value)} просмотров
                    </div>
                    <div class="mt-1 text-slate-300">
                        {formatLabel(hoveredPoint.label)}
                    </div>
                </div>
            {/if}
        </div>
    {/if}

    <div class="mt-5 text-sm text-slate-500 dark:text-slate-400">
        Всего за выбранный период: <span
            class="font-semibold text-slate-900 dark:text-white"
            >{formatNumber(chartTotal)}</span
        >
    </div>
</article>
