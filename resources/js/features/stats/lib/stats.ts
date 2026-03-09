import type { ComponentType, SvelteComponent } from 'svelte';
import type {
    StatsCategoryBreakdownItem,
    StatsChartPayload,
    StatsFeedPerformance,
    StatsPopularRow,
} from '@/features/portal';

export const chartPeriods = [
    { key: '7d', label: '7 дней' },
    { key: '30d', label: '30 дней' },
    { key: '90d', label: '90 дней' },
] as const;

export type ChartPeriod = (typeof chartPeriods)[number]['key'];

export const popularPeriods = [
    { key: 'today', label: 'Сегодня' },
    { key: 'week', label: 'Неделя' },
    { key: 'month', label: 'Месяц' },
] as const;

export type PopularPeriod = (typeof popularPeriods)[number]['key'];

export const lineChartFrame = {
    width: 760,
    height: 280,
    padding: {
        top: 24,
        right: 20,
        bottom: 34,
        left: 18,
    },
} as const;

export const articlesChartFrame = {
    width: 760,
    height: 280,
    padding: {
        top: 18,
        right: 20,
        bottom: 34,
        left: 18,
    },
} as const;

export interface TrendSummary {
    direction: 'up' | 'down' | 'flat';
    change: number | null;
    label: string;
}

export interface HoveredPoint {
    label: string;
    value: number;
    left: number;
    top: number;
}

export interface LineChartPoint {
    x: number;
    y: number;
    value: number;
    label: string;
}

export interface LineChartMetrics {
    width: number;
    height: number;
    maxValue: number;
    minValue: number;
    points: LineChartPoint[];
    polyline: string;
    yTicks: number[];
}

export interface ArticleBarSegment {
    x: number;
    y: number;
    width: number;
    height: number;
    value: number;
    name: string;
    color: string;
}

export interface ArticleBar {
    label: string;
    total: number;
    center: number;
    segments: ArticleBarSegment[];
}

export interface StatsOverviewCard {
    key: string;
    title: string;
    value: number;
    subtitle: string;
    trend: TrendSummary;
    icon: ComponentType<SvelteComponent<{ class?: string }>>;
}

export type StatsCategoryBreakdownView = StatsCategoryBreakdownItem & {
    top_article_href: string | null;
};

export type StatsPopularRowView = StatsPopularRow & {
    article_href: string;
};

export function buildTrendSummary(data: number[]): TrendSummary {
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

    const change = Number((((current - previous) / previous) * 100).toFixed(1));

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

export function buildLineChartMetrics(
    chartData: StatsChartPayload | null,
): LineChartMetrics {
    const values = chartData?.data ?? [];
    const labels = chartData?.labels ?? [];
    const width =
        lineChartFrame.width -
        lineChartFrame.padding.left -
        lineChartFrame.padding.right;
    const height =
        lineChartFrame.height -
        lineChartFrame.padding.top -
        lineChartFrame.padding.bottom;
    const maxValue = Math.max(...values, 1);
    const minValue = Math.min(...values, 0);
    const range = Math.max(maxValue - minValue, 1);
    const slot = labels.length > 1 ? width / (labels.length - 1) : width / 2;

    const points = values.map((value, index) => {
        const x =
            lineChartFrame.padding.left +
            (labels.length > 1 ? index * slot : width / 2);
        const y =
            lineChartFrame.padding.top +
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
}

export function buildArticlesSeries(
    chartData: StatsChartPayload | null,
): ArticleBar[] {
    const labels = chartData?.labels ?? [];
    const totals = chartData?.data ?? [];
    const series = chartData?.series?.length
        ? chartData.series
        : [
              {
                  id: 0,
                  name: 'Все категории',
                  color: '#2563EB',
                  data: totals,
              },
          ];

    const chartWidth =
        articlesChartFrame.width -
        articlesChartFrame.padding.left -
        articlesChartFrame.padding.right;
    const chartHeight =
        articlesChartFrame.height -
        articlesChartFrame.padding.top -
        articlesChartFrame.padding.bottom;
    const slotWidth = labels.length > 0 ? chartWidth / labels.length : 0;
    const barWidth = Math.min(22, Math.max(10, slotWidth * 0.55));
    const maxTotal = Math.max(...totals, 1);

    return labels.map((label, index) => {
        let currentY = articlesChartFrame.padding.top + chartHeight;
        const x =
            articlesChartFrame.padding.left +
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
}

export function formatNumber(value: number | null | undefined): string {
    return new Intl.NumberFormat('ru-RU').format(value ?? 0);
}

export function formatLabel(label: string): string {
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

export function formatRelativeDate(value?: string | null): string {
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

export function tagSizeClass(usageCount: number): string {
    if (usageCount >= 50) {
        return 'text-base font-bold';
    }

    if (usageCount >= 20) {
        return 'text-sm font-semibold';
    }

    return 'text-xs font-medium';
}

export function feedStatus(feed: StatsFeedPerformance): {
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
