<?php

namespace App\Filament\Widgets\Charts;

use App\Models\RssFeed;
use App\Models\RssParseLog;
use Carbon\CarbonInterface;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class RssFeedParseActivityWidget extends ApexChartWidget
{
    use HasFiltersSchema;

    protected static ?string $chartId = 'rss-feed-parse-activity';

    protected static ?string $heading = 'Активность RSS-парсинга';

    protected static ?string $subheading = 'Успешные и неуспешные запуски по дням';

    protected static ?int $sort = 13;

    protected static ?int $contentHeight = 320;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label('Период')
                ->options([
                    '7' => 'Последние 7 дней',
                    '14' => 'Последние 14 дней',
                    '30' => 'Последние 30 дней',
                ])
                ->default('14')
                ->native(false),
            Select::make('rss_feed_id')
                ->label('Лента')
                ->options($this->getFeedOptions())
                ->default('')
                ->native(false),
        ]);
    }

    public function updatedInteractsWithSchemas(string $statePath): void
    {
        $this->updateOptions();
    }

    protected function getOptions(): array
    {
        $period = (int) ($this->filters['period'] ?? 14);
        $feedId = filled($this->filters['rss_feed_id'] ?? null)
            ? (int) $this->filters['rss_feed_id']
            : null;
        $rangeStart = now()->subDays($period - 1)->startOfDay();

        /** @var array<int, array{date: string, success: bool, count: int}> $rows */
        $rows = Cache::remember(
            "charts:rss-feed-parse-activity:{$period}:".($feedId ?? 'all'),
            now()->addMinutes(5),
            function () use ($feedId, $rangeStart): array {
                $query = RssParseLog::query()
                    ->selectRaw('DATE(started_at) as date, success, COUNT(*) as count')
                    ->whereNotNull('started_at')
                    ->where('started_at', '>=', $rangeStart)
                    ->groupBy('date', 'success')
                    ->orderBy('date');

                if ($feedId !== null) {
                    $query->where('rss_feed_id', $feedId);
                }

                return $query->get()
                    ->map(fn (RssParseLog $log): array => [
                        'date' => (string) $log->date,
                        'success' => (bool) $log->success,
                        'count' => (int) $log->count,
                    ])
                    ->all();
            },
        );

        $dates = $this->buildDateRange($rangeStart, $period);
        $rowsByDate = collect($rows)->groupBy('date');
        $successData = $dates->map(
            fn (string $date): int => (int) collect($rowsByDate->get($date, []))
                ->where('success', true)
                ->sum('count'),
        );
        $failedData = $dates->map(
            fn (string $date): int => (int) collect($rowsByDate->get($date, []))
                ->where('success', false)
                ->sum('count'),
        );

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 320,
                'stacked' => true,
                'toolbar' => [
                    'show' => true,
                ],
            ],
            'series' => [
                [
                    'name' => 'Успешно',
                    'data' => $successData->values()->all(),
                ],
                [
                    'name' => 'С ошибками',
                    'data' => $failedData->values()->all(),
                ],
            ],
            'xaxis' => [
                'categories' => $dates->values()->all(),
                'type' => 'datetime',
                'labels' => [
                    'format' => 'dd MMM',
                    'datetimeUTC' => false,
                ],
            ],
            'yaxis' => [
                'title' => [
                    'text' => 'Запуски',
                ],
                'min' => 0,
                'forceNiceScale' => true,
            ],
            'colors' => ['#16a34a', '#ef4444'],
            'dataLabels' => [
                'enabled' => false,
            ],
            'legend' => [
                'position' => 'top',
            ],
            'plotOptions' => [
                'bar' => [
                    'borderRadius' => 4,
                    'columnWidth' => '72%',
                ],
            ],
            'tooltip' => [
                'x' => [
                    'format' => 'dd MMM yyyy',
                ],
            ],
            'grid' => [
                'borderColor' => '#e2e8f0',
                'strokeDashArray' => 4,
            ],
            'noData' => [
                'text' => 'Нет запусков парсинга за выбранный период',
            ],
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function getFeedOptions(): array
    {
        return ['' => 'Все ленты'] + RssFeed::query()
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    protected function buildDateRange(CarbonInterface $rangeStart, int $period): Collection
    {
        return collect(range(0, $period - 1))
            ->map(fn (int $offset): string => $rangeStart->copy()->addDays($offset)->toDateString());
    }
}
