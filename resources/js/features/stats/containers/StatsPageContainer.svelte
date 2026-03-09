<script lang="ts">
    import Activity from 'lucide-svelte/icons/activity';
    import BarChart3 from 'lucide-svelte/icons/bar-chart-3';
    import Eye from 'lucide-svelte/icons/eye';
    import Newspaper from 'lucide-svelte/icons/newspaper';
    import { onMount } from 'svelte';
    import {
        AppHead,
        articleUrl,
        tagUrl,
        visitPublic,
    } from '@/features/portal';
    import * as api from '@/features/portal';
    import type {
        StatsCategoryBreakdownItem as CategoryBreakdownItem,
        StatsChartPayload as ChartPayload,
        StatsFeedPerformance as FeedPerformance,
        StatsOverview as Overview,
        StatsPopularRow as PopularRow,
    } from '@/features/portal';
    import StatsArticlesChartPanel from '@/features/stats/components/StatsArticlesChartPanel.svelte';
    import StatsCategoryBreakdownPanel from '@/features/stats/components/StatsCategoryBreakdownPanel.svelte';
    import StatsFeedStatusTable from '@/features/stats/components/StatsFeedStatusTable.svelte';
    import StatsHeroPanel from '@/features/stats/components/StatsHeroPanel.svelte';
    import StatsOverviewGrid from '@/features/stats/components/StatsOverviewGrid.svelte';
    import StatsPopularTable from '@/features/stats/components/StatsPopularTable.svelte';
    import StatsTrendingTagsPanel from '@/features/stats/components/StatsTrendingTagsPanel.svelte';
    import StatsViewsChartPanel from '@/features/stats/components/StatsViewsChartPanel.svelte';
    import { buildTrendSummary } from '@/features/stats/lib/stats';
    import type {
        ChartPeriod,
        PopularPeriod,
        StatsCategoryBreakdownView,
        StatsOverviewCard,
        StatsPopularRowView,
    } from '@/features/stats/lib/stats';

    let overview = $state<Overview | null>(null);
    let chartData = $state<ChartPayload | null>(null);
    let articlesChart = $state<ChartPayload | null>(null);
    let popular = $state<PopularRow[]>([]);
    let categoryBreakdown = $state<CategoryBreakdownItem[]>([]);
    let feeds = $state<FeedPerformance[]>([]);
    let loading = $state(true);
    let error = $state<string | null>(null);
    let chartPeriod = $state<ChartPeriod>('30d');
    let popularPeriod = $state<PopularPeriod>('week');
    let comparisonChart = $state<ChartPayload | null>(null);
    let loadedChartPeriod = $state<ChartPeriod | null>(null);
    let loadedPopularPeriod = $state<PopularPeriod | null>(null);
    let viewsRequestId = 0;
    let popularRequestId = 0;

    const cardTrends = $derived.by((): StatsOverviewCard[] => {
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

    const categoryBreakdownItems = $derived.by(
        (): StatsCategoryBreakdownView[] =>
            categoryBreakdown.map((item) => ({
                ...item,
                top_article_href: item.top_article
                    ? articleUrl(item.top_article.slug)
                    : null,
            })),
    );

    const popularRows = $derived.by((): StatsPopularRowView[] =>
        popular.map((row) => ({
            ...row,
            article_href: articleUrl(row.slug),
        })),
    );

    function navigateToTag(slug: string): void {
        visitPublic(tagUrl(slug));
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

    async function reloadViewsChart(period: ChartPeriod): Promise<void> {
        const requestId = ++viewsRequestId;
        const response = await api.getStatsChart('views', period);

        if (requestId !== viewsRequestId) {
            return;
        }

        chartData = response.data;
        loadedChartPeriod = period;
    }

    async function reloadPopular(period: PopularPeriod): Promise<void> {
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

    function handleChartPeriodChange(event: CustomEvent<ChartPeriod>): void {
        chartPeriod = event.detail;
    }

    function handlePopularPeriodChange(
        event: CustomEvent<PopularPeriod>,
    ): void {
        popularPeriod = event.detail;
    }

    function handleTagSelect(event: CustomEvent<string>): void {
        navigateToTag(event.detail);
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
</script>

<AppHead title="Статистика портала" />

<div
    class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.16),_transparent_30%),linear-gradient(to_bottom,_#f8fbff,_#eef2ff)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.18),_transparent_30%),linear-gradient(to_bottom,_#020617,_#111827)] sm:px-6 lg:px-8"
>
    <div class="mx-auto max-w-7xl space-y-8">
        <StatsHeroPanel {overview} />

        {#if error && !loading}
            <section
                class="rounded-[1.75rem] border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200"
            >
                {error}
            </section>
        {/if}

        <StatsOverviewGrid {loading} cards={cardTrends} />

        <section
            class="grid gap-8 xl:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]"
        >
            <StatsViewsChartPanel
                {loading}
                {chartData}
                selectedPeriod={chartPeriod}
                on:periodchange={handleChartPeriodChange}
            />
            <StatsCategoryBreakdownPanel
                {loading}
                items={categoryBreakdownItems}
            />
        </section>

        <section
            class="grid gap-8 xl:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]"
        >
            <StatsArticlesChartPanel {loading} chartData={articlesChart} />
            <StatsTrendingTagsPanel
                {loading}
                tags={overview?.trending_tags ?? []}
                on:tagselect={handleTagSelect}
            />
        </section>

        <section
            class="grid gap-8 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]"
        >
            <StatsPopularTable
                {loading}
                rows={popularRows}
                selectedPeriod={popularPeriod}
                on:periodchange={handlePopularPeriodChange}
            />
            <StatsFeedStatusTable {loading} {feeds} />
        </section>
    </div>
</div>
