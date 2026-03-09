<script lang="ts">
    import Activity from 'lucide-svelte/icons/activity';
    import BarChart3 from 'lucide-svelte/icons/bar-chart-3';
    import Eye from 'lucide-svelte/icons/eye';
    import Newspaper from 'lucide-svelte/icons/newspaper';
    import Radio from 'lucide-svelte/icons/radio';
    import TrendingDown from 'lucide-svelte/icons/trending-down';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Skeleton from '@/components/ui/skeleton/Skeleton.svelte';
    import * as api from '@/lib/api';
    import type {
        StatsCategoryBreakdownItem as CategoryBreakdownItem,
        StatsChartPayload as ChartPayload,
        StatsFeedPerformance as FeedPerformance,
        StatsOverview as Overview,
        StatsPopularRow as PopularRow,
    } from '@/lib/api';
    import { cn } from '@/lib/utils';

    type TrendSummary = {
        direction: 'up' | 'down' | 'flat';
        change: number | null;
        label: string;
    };

    type HoveredPoint = {
        label: string;
        value: number;
        left: number;
        top: number;
    };

    type ArticleBarSegment = {
        x: number;
        y: number;
        width: number;
        height: number;
        value: number;
        name: string;
        color: string;
    };

    type ArticleBar = {
        label: string;
        total: number;
        center: number;
        segments: ArticleBarSegment[];
    };

    const chartPeriods = [
        { key: '7d', label: '7 дней' },
        { key: '30d', label: '30 дней' },
        { key: '90d', label: '90 дней' },
    ] as const;

    const popularPeriods = [
        { key: 'today', label: 'Сегодня' },
        { key: 'week', label: 'Неделя' },
        { key: 'month', label: 'Месяц' },
    ] as const;

    const lineChartWidth = 760;
    const lineChartHeight = 280;
    const lineChartPadding = {
        top: 24,
        right: 20,
        bottom: 34,
        left: 18,
    };
    const articlesChartWidth = 760;
    const articlesChartHeight = 280;
    const articlesChartPadding = {
        top: 18,
        right: 20,
        bottom: 34,
        left: 18,
    };

    let overview = $state<Overview | null>(null);
    let chartData = $state<ChartPayload | null>(null);
    let articlesChart = $state<ChartPayload | null>(null);
    let popular = $state<PopularRow[]>([]);
    let categoryBreakdown = $state<CategoryBreakdownItem[]>([]);
    let feeds = $state<FeedPerformance[]>([]);
    let loading = $state(true);
    let error = $state<string | null>(null);
    let chartPeriod = $state<(typeof chartPeriods)[number]['key']>('30d');
    let popularPeriod = $state<(typeof popularPeriods)[number]['key']>('week');
    let hoveredPoint = $state<HoveredPoint | null>(null);
    let hoveredBar = $state<HoveredPoint | null>(null);
    let comparisonChart = $state<ChartPayload | null>(null);
    let loadedChartPeriod = $state<(typeof chartPeriods)[number]['key'] | null>(
        null,
    );
    let loadedPopularPeriod = $state<
        (typeof popularPeriods)[number]['key'] | null
    >(null);
    let viewsRequestId = 0;
    let popularRequestId = 0;

    const lineChartValues = $derived(chartData?.data ?? []);
    const lineChartLabels = $derived(chartData?.labels ?? []);
    const chartTotal = $derived(
        lineChartValues.reduce((sum, value) => sum + value, 0),
    );

    const lineMetrics = $derived.by(() => {
        const values = lineChartValues;
        const labels = lineChartLabels;
        const width =
            lineChartWidth - lineChartPadding.left - lineChartPadding.right;
        const height =
            lineChartHeight - lineChartPadding.top - lineChartPadding.bottom;
        const maxValue = Math.max(...values, 1);
        const minValue = Math.min(...values, 0);
        const range = Math.max(maxValue - minValue, 1);
        const slot =
            labels.length > 1 ? width / (labels.length - 1) : width / 2;

        const points = values.map((value, index) => {
            const x =
                lineChartPadding.left +
                (labels.length > 1 ? index * slot : width / 2);
            const y =
                lineChartPadding.top +
                height -
                ((value - minValue) / range) * height;

            return { x, y, value, label: labels[index] ?? '' };
        });

        return {
            width,
            height,
            maxValue,
            minValue,
            points,
            polyline: points.map((point) => `${point.x},${point.y}`).join(' '),
            yTicks: [maxValue, Math.round((maxValue + minValue) / 2), minValue],
        };
    });

    const articlesSeries = $derived.by((): ArticleBar[] => {
        const labels = articlesChart?.labels ?? [];
        const totals = articlesChart?.data ?? [];
        const series = articlesChart?.series?.length
            ? articlesChart.series
            : [
                  {
                      id: 0,
                      name: 'Все категории',
                      color: '#2563EB',
                      data: totals,
                  },
              ];

        const chartWidth =
            articlesChartWidth -
            articlesChartPadding.left -
            articlesChartPadding.right;
        const chartHeight =
            articlesChartHeight -
            articlesChartPadding.top -
            articlesChartPadding.bottom;
        const slotWidth = labels.length > 0 ? chartWidth / labels.length : 0;
        const barWidth = Math.min(22, Math.max(10, slotWidth * 0.55));
        const maxTotal = Math.max(...totals, 1);

        return labels.map((label, index) => {
            let currentY = articlesChartPadding.top + chartHeight;
            const x =
                articlesChartPadding.left +
                index * slotWidth +
                (slotWidth - barWidth) / 2;

            const segments = series
                .map((item) => {
                    const value = item.data[index] ?? 0;

                    if (value <= 0) {
                        return null;
                    }

                    const height = (value / maxTotal) * chartHeight;
                    currentY -= height;

                    return {
                        x,
                        y: currentY,
                        width: barWidth,
                        height,
                        value,
                        name: item.name,
                        color: item.color,
                    };
                })
                .filter(
                    (segment): segment is ArticleBarSegment => segment !== null,
                );

            return {
                label,
                total: totals[index] ?? 0,
                center: x + barWidth / 2,
                segments,
            };
        });
    });

    const cardTrends = $derived.by(() => {
        const articlesTrend = buildTrendSummary(articlesChart?.data ?? []);
        const viewsTrend = buildTrendSummary(
            comparisonChart?.data ?? chartData?.data ?? [],
        );

        return [
            {
                key: 'articles_total',
                title: 'Всего статей',
                value: overview?.articles.total ?? 0,
                subtitle: 'Опубликовано на портале',
                trend: articlesTrend,
                icon: Newspaper,
            },
            {
                key: 'articles_today',
                title: 'Статей сегодня',
                value: overview?.articles.today ?? 0,
                subtitle: 'Новые публикации за день',
                trend: articlesTrend,
                icon: Activity,
            },
            {
                key: 'views_total',
                title: 'Всего просмотров',
                value: overview?.views.total ?? 0,
                subtitle: 'Накопленный трафик',
                trend: viewsTrend,
                icon: Eye,
            },
            {
                key: 'views_today',
                title: 'Просмотров сегодня',
                value: overview?.views.today ?? 0,
                subtitle: 'Активность за текущий день',
                trend: viewsTrend,
                icon: BarChart3,
            },
        ];
    });

    function buildTrendSummary(data: number[]): TrendSummary {
        if (data.length < 2) {
            return {
                direction: 'flat',
                change: null,
                label: 'Нет сравнения',
            };
        }

        const current = data[data.length - 1] ?? 0;
        const previous = data[data.length - 2] ?? 0;

        if (previous === 0) {
            return {
                direction: current > 0 ? 'up' : 'flat',
                change: null,
                label: 'Без базы сравнения',
            };
        }

        const change = Number(
            (((current - previous) / previous) * 100).toFixed(1),
        );

        if (change > 0) {
            return {
                direction: 'up',
                change,
                label: `+${change}% к вчера`,
            };
        }

        if (change < 0) {
            return {
                direction: 'down',
                change,
                label: `${change}% к вчера`,
            };
        }

        return {
            direction: 'flat',
            change,
            label: 'Без изменений',
        };
    }

    function formatNumber(value: number | null | undefined): string {
        return new Intl.NumberFormat('ru-RU').format(value ?? 0);
    }

    function formatLabel(label: string): string {
        if (label.includes(':00')) {
            return label.slice(5);
        }

        if (/^\d{4}-\d{2}$/.test(label)) {
            return `Нед ${label.slice(-2)}`;
        }

        const date = new Date(`${label}T00:00:00`);

        if (Number.isNaN(date.getTime())) {
            return label;
        }

        return new Intl.DateTimeFormat('ru-RU', {
            day: 'numeric',
            month: 'short',
        }).format(date);
    }

    function formatRelativeDate(value?: string | null): string {
        if (!value) {
            return 'Нет данных';
        }

        const date = new Date(value);
        const diffMs = Date.now() - date.getTime();
        const diffMinutes = Math.max(1, Math.round(diffMs / 60000));

        if (diffMinutes < 60) {
            return `${diffMinutes} мин назад`;
        }

        const diffHours = Math.round(diffMinutes / 60);

        if (diffHours < 24) {
            return `${diffHours} ч назад`;
        }

        return new Intl.DateTimeFormat('ru-RU', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    function tagSizeClass(usageCount: number): string {
        if (usageCount >= 50) {
            return 'text-base font-bold';
        }

        if (usageCount >= 20) {
            return 'text-sm font-semibold';
        }

        return 'text-xs font-medium';
    }

    function feedStatus(feed: FeedPerformance): {
        dot: string;
        label: string;
    } {
        if ((feed.last_run?.error_count ?? 0) > 0) {
            return { dot: 'bg-amber-500', label: 'Есть ошибки' };
        }

        if ((feed.last_run?.new_count ?? 0) > 0) {
            return { dot: 'bg-emerald-500', label: 'Обновляется' };
        }

        return { dot: 'bg-slate-400', label: 'Спокойно' };
    }

    function navigateToTag(slug: string): void {
        if (typeof window === 'undefined') {
            return;
        }

        window.location.hash = `/tag/${slug}`;
    }

    async function loadOverviewData(): Promise<void> {
        loading = true;
        error = null;

        try {
            const [
                overviewResponse,
                viewsChartResponse,
                articlesChartResponse,
                comparisonResponse,
                popularResponse,
                feedsResponse,
                breakdownResponse,
            ] = await Promise.all([
                api.getStats(),
                api.getStatsChart('views', chartPeriod),
                api.getStatsChart('articles', '30d'),
                api.getStatsChart('views', '30d'),
                api.getPopular({ period: popularPeriod, limit: 10 }),
                api.getFeedsPerformance(),
                api.getCategoryBreakdown(),
            ]);

            overview = overviewResponse.data;
            chartData = viewsChartResponse.data;
            articlesChart = articlesChartResponse.data;
            comparisonChart = comparisonResponse.data;
            popular = popularResponse.data;
            feeds = feedsResponse.data;
            categoryBreakdown = breakdownResponse.data;
            loadedChartPeriod = chartPeriod;
            loadedPopularPeriod = popularPeriod;
        } catch {
            error = 'Не удалось загрузить статистику.';
        } finally {
            loading = false;
        }
    }

    async function reloadViewsChart(
        period: (typeof chartPeriods)[number]['key'],
    ): Promise<void> {
        const requestId = ++viewsRequestId;
        const response = await api.getStatsChart('views', period);

        if (requestId !== viewsRequestId) {
            return;
        }

        chartData = response.data;
        loadedChartPeriod = period;
    }

    async function reloadPopular(
        period: (typeof popularPeriods)[number]['key'],
    ): Promise<void> {
        const requestId = ++popularRequestId;
        const response = await api.getPopular({
            period,
            limit: 10,
        });

        if (requestId !== popularRequestId) {
            return;
        }

        popular = response.data;
        loadedPopularPeriod = period;
    }

    onMount(() => {
        void loadOverviewData();
    });

    $effect(() => {
        const period = chartPeriod;

        if (!overview || period === loadedChartPeriod) {
            return;
        }

        let cancelled = false;

        void (async () => {
            try {
                if (!cancelled) {
                    await reloadViewsChart(period);
                }
            } catch {
                if (!cancelled) {
                    error = 'Не удалось обновить график просмотров.';
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    });

    $effect(() => {
        const period = popularPeriod;

        if (!overview || period === loadedPopularPeriod) {
            return;
        }

        let cancelled = false;

        void (async () => {
            try {
                if (!cancelled) {
                    await reloadPopular(period);
                }
            } catch {
                if (!cancelled) {
                    error = 'Не удалось обновить таблицу популярных статей.';
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    });

    $effect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const resetHover = (): void => {
            hoveredPoint = null;
            hoveredBar = null;
        };

        window.addEventListener('scroll', resetHover, { passive: true });

        return () => {
            window.removeEventListener('scroll', resetHover);
        };
    });
</script>

<AppHead title="Статистика портала" />

<div
    class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.16),_transparent_30%),linear-gradient(to_bottom,_#f8fbff,_#eef2ff)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.18),_transparent_30%),linear-gradient(to_bottom,_#020617,_#111827)] sm:px-6 lg:px-8"
>
    <div class="mx-auto max-w-7xl space-y-8">
        <section
            class="relative overflow-hidden rounded-[2.3rem] border border-slate-200/80 bg-white/90 p-6 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.35)] backdrop-blur dark:border-white/10 dark:bg-slate-950/80 sm:p-8"
        >
            <div
                class="absolute right-0 top-0 h-40 w-40 rounded-full bg-sky-200/50 blur-3xl dark:bg-sky-500/20"
            ></div>
            <div
                class="absolute bottom-0 left-0 h-32 w-32 rounded-full bg-amber-200/50 blur-3xl dark:bg-amber-500/10"
            ></div>
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div class="max-w-3xl">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/50 dark:text-sky-300"
                    >
                        <Radio class="size-4" />
                        Живая аналитика
                    </div>
                    <h1
                        class="mt-5 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-4xl"
                    >
                        Пульс редакции и поведение аудитории
                    </h1>
                    <p
                        class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base"
                    >
                        Единая панель с публикациями, просмотрами, долей рубрик
                        и работой RSS-лент. Подходит и для быстрого обзора, и
                        для анализа ритма новостей.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <div
                            class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                        >
                            {formatNumber(overview?.articles.total ?? 0)} материалов
                        </div>
                        <div
                            class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                        >
                            {formatNumber(overview?.views.total ?? 0)} просмотров
                        </div>
                        <div
                            class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                        >
                            {formatNumber(overview?.feeds.active ?? 0)} активных лент
                        </div>
                    </div>
                </div>

                {#if overview?.last_parse}
                    <div
                        class="rounded-[1.75rem] border border-slate-200 bg-slate-50 px-5 py-4 dark:border-white/10 dark:bg-white/5"
                    >
                        <div
                            class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400"
                        >
                            Последний парсинг
                        </div>
                        <div
                            class="mt-2 text-sm font-medium text-slate-900 dark:text-white"
                        >
                            {formatRelativeDate(overview.last_parse)}
                        </div>
                    </div>
                {/if}
            </div>
        </section>

        {#if error && !loading}
            <section
                class="rounded-[1.75rem] border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200"
            >
                {error}
            </section>
        {/if}

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
                {#each cardTrends as card (card.key)}
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

                        <p
                            class="mt-4 text-sm text-slate-500 dark:text-slate-400"
                        >
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

        <section
            class="grid gap-8 xl:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]"
        >
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
                                class={cn(
                                    'rounded-full px-4 py-2 text-sm font-medium transition',
                                    chartPeriod === period.key
                                        ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                                )}
                                onclick={() => {
                                    chartPeriod = period.key;
                                }}
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
                            viewBox={`0 0 ${lineChartWidth} ${lineChartHeight}`}
                            class="h-[320px] w-full"
                            role="img"
                            aria-label="График просмотров"
                        >
                            {#each lineMetrics.yTicks as tick (tick)}
                                {@const y =
                                    lineChartPadding.top +
                                    lineMetrics.height -
                                    ((tick - lineMetrics.minValue) /
                                        Math.max(
                                            lineMetrics.maxValue -
                                                lineMetrics.minValue,
                                            1,
                                        )) *
                                        lineMetrics.height}
                                <line
                                    x1={lineChartPadding.left}
                                    x2={lineChartWidth - lineChartPadding.right}
                                    y1={y}
                                    y2={y}
                                    stroke="rgba(148, 163, 184, 0.22)"
                                    stroke-dasharray="4 6"
                                />
                                <text
                                    x={lineChartPadding.left}
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
                                            hoveredPoint = {
                                                label: point.label,
                                                value: point.value,
                                                left: point.x,
                                                top: point.y,
                                            };
                                        }}
                                        onmouseleave={() => {
                                            hoveredPoint = null;
                                        }}
                                    />
                                </g>
                            {/each}

                            {#each lineMetrics.points as point, index (index)}
                                {#if index % Math.max(1, Math.ceil(lineMetrics.points.length / 6)) === 0 || index === lineMetrics.points.length - 1}
                                    <text
                                        x={point.x}
                                        y={lineChartHeight - 8}
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
                                style={`left: calc(${(hoveredPoint.left / lineChartWidth) * 100}% - 3rem); top: calc(${(hoveredPoint.top / lineChartHeight) * 100}% - 3.75rem);`}
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

            <article
                class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900"
            >
                <div
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                >
                    Категории
                </div>
                <h2
                    class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
                >
                    Доля публикаций
                </h2>

                {#if loading}
                    <div class="mt-6 space-y-4">
                        {#each Array.from({ length: 5 }) as _, index (index)}
                            <div>
                                <Skeleton class="h-4 w-28" />
                                <Skeleton
                                    class="mt-3 h-3 w-full rounded-full"
                                />
                            </div>
                        {/each}
                    </div>
                {:else}
                    <div class="mt-6 space-y-4">
                        {#each categoryBreakdown as item (item.id)}
                            <div>
                                <div
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <div
                                        class="font-medium text-slate-900 dark:text-white"
                                    >
                                        {item.name}
                                    </div>
                                    <div
                                        class="text-slate-500 dark:text-slate-400"
                                    >
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
                                {#if item.top_article}
                                    <a
                                        href={`/#/articles/${item.top_article.slug}`}
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
        </section>

        <section
            class="grid gap-8 xl:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]"
        >
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

                    {#if articlesChart?.series?.length}
                        <div class="flex flex-wrap gap-3">
                            {#each articlesChart.series as series (series.id)}
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
                            viewBox={`0 0 ${articlesChartWidth} ${articlesChartHeight}`}
                            class="h-[320px] w-full"
                            role="img"
                            aria-label="График публикаций по дням"
                        >
                            {#each articlesSeries as bar, index (index)}
                                {#each bar.segments as segment (segment.name + segment.value + index)}
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
                                            hoveredBar = {
                                                label: `${bar.label} — ${segment.name}`,
                                                value: segment.value,
                                                left:
                                                    segment.x +
                                                    segment.width / 2,
                                                top: segment.y,
                                            };
                                        }}
                                        onmouseleave={() => {
                                            hoveredBar = null;
                                        }}
                                    />
                                {/each}

                                {#if index % Math.max(1, Math.ceil(articlesSeries.length / 6)) === 0 || index === articlesSeries.length - 1}
                                    <text
                                        x={bar.center}
                                        y={articlesChartHeight - 8}
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
                                style={`left: calc(${(hoveredBar.left / articlesChartWidth) * 100}% - 3rem); top: calc(${(hoveredBar.top / articlesChartHeight) * 100}% - 3.75rem);`}
                            >
                                <div class="font-semibold">
                                    {formatNumber(hoveredBar.value)} публикаций
                                </div>
                                <div class="mt-1 text-slate-300">
                                    {hoveredBar.label}
                                </div>
                            </div>
                        {/if}
                    </div>
                {/if}
            </article>

            <article
                class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900"
            >
                <div
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                >
                    Теги
                </div>
                <h2
                    class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
                >
                    Тренды редакции
                </h2>

                {#if loading}
                    <div class="mt-6 flex flex-wrap gap-3">
                        {#each Array.from({ length: 12 }) as _, index (index)}
                            <Skeleton class="h-9 w-24 rounded-full" />
                        {/each}
                    </div>
                {:else}
                    <div class="mt-6 flex flex-wrap gap-3">
                        {#each overview?.trending_tags ?? [] as tag (tag.id)}
                            <button
                                type="button"
                                class={cn(
                                    'rounded-full px-3 py-2 text-white transition hover:opacity-90',
                                    tagSizeClass(tag.usage_count ?? 0),
                                )}
                                style={`background-color: ${tag.color ?? '#6B7280'}`}
                                onclick={() => {
                                    navigateToTag(tag.slug);
                                }}
                            >
                                #{tag.name}
                            </button>
                        {/each}
                    </div>
                {/if}
            </article>
        </section>

        <section
            class="grid gap-8 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]"
        >
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
                                class={cn(
                                    'rounded-full px-4 py-2 text-sm font-medium transition',
                                    popularPeriod === period.key
                                        ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                                )}
                                onclick={() => {
                                    popularPeriod = period.key;
                                }}
                            >
                                {period.label}
                            </button>
                        {/each}
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full table-auto text-sm">
                        <thead class="text-left text-slate-400">
                            <tr
                                class="border-b border-slate-200 dark:border-white/10"
                            >
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
                                {#each Array.from( { length: 5 }, ) as _, index (index)}
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
                                        <td class="py-4"
                                            ><Skeleton class="h-4 w-12" /></td
                                        >
                                    </tr>
                                {/each}
                            {:else}
                                {#each popular as row, index (row.article_id)}
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
                                                href={`/#/articles/${row.slug}`}
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
                                                        : ''}{row.change_percent}%
                                                    к прошлому периоду
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
                                        <td
                                            class="py-4 text-slate-600 dark:text-slate-300"
                                        >
                                            {formatNumber(row.bookmarks_count)}
                                        </td>
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>
            </article>

            <article
                class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900"
            >
                <div
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
                >
                    RSS-ленты
                </div>
                <h2
                    class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
                >
                    Статус парсинга
                </h2>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full table-auto text-sm">
                        <thead class="text-left text-slate-400">
                            <tr
                                class="border-b border-slate-200 dark:border-white/10"
                            >
                                <th class="pb-3 pr-4 font-medium">Лента</th>
                                <th class="pb-3 pr-4 font-medium"
                                    >Последний запуск</th
                                >
                                <th class="pb-3 pr-4 font-medium">Сегодня</th>
                                <th class="pb-3 pr-4 font-medium">Всего</th>
                                <th class="pb-3 font-medium">Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#if loading}
                                {#each Array.from( { length: 5 }, ) as _, index (index)}
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
                                        <td class="py-4"
                                            ><Skeleton class="h-4 w-20" /></td
                                        >
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
                                                {feed.category ??
                                                    'Без категории'}
                                            </div>
                                        </td>
                                        <td
                                            class="py-4 pr-4 text-slate-600 dark:text-slate-300"
                                        >
                                            {formatRelativeDate(
                                                feed.last_run?.started_at,
                                            )}
                                        </td>
                                        <td
                                            class="py-4 pr-4 text-slate-600 dark:text-slate-300"
                                        >
                                            {formatNumber(
                                                feed.today_articles_count,
                                            )}
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
        </section>
    </div>
</div>
