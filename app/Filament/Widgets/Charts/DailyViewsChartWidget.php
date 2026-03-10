<?php

namespace App\Filament\Widgets\Charts;

use App\Models\ArticleView;
use Carbon\CarbonInterface;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class DailyViewsChartWidget extends ApexChartWidget
{
    use HasFiltersSchema;

    protected static ?string $chartId = 'daily-views-chart';

    protected static ?string $heading = 'Ежедневные просмотры';

    protected static ?string $subheading = 'Текущий период против предыдущего';

    protected static ?int $sort = 11;

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
                    '30' => 'Последние 30 дней',
                    '90' => 'Последние 90 дней',
                ])
                ->default('30')
                ->native(false),
        ]);
    }

    public function updatedInteractsWithSchemas(string $statePath): void
    {
        $this->updateOptions();
    }

    protected function getOptions(): array
    {
        $period = (int) ($this->filters['period'] ?? 30);
        $currentStart = now()->subDays($period - 1)->startOfDay();
        $previousStart = $currentStart->copy()->subDays($period);
        $previousEnd = $currentStart->copy()->subSecond();

        /** @var array{current: array<string, int|string|null>, previous: array<string, int|string|null>} $payload */
        $payload = Cache::remember(
            "charts:daily-views:{$period}",
            now()->addMinutes(5),
            function () use ($currentStart, $previousEnd, $previousStart): array {
                $current = ArticleView::query()
                    ->selectRaw('DATE(viewed_at) as date, COUNT(*) as count')
                    ->where('viewed_at', '>=', $currentStart)
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('count', 'date')
                    ->all();

                $previous = ArticleView::query()
                    ->selectRaw('DATE(viewed_at) as date, COUNT(*) as count')
                    ->whereBetween('viewed_at', [$previousStart, $previousEnd])
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('count', 'date')
                    ->all();

                return [
                    'current' => $current,
                    'previous' => $previous,
                ];
            },
        );

        $dates = $this->buildDateRange($currentStart, $period);
        $currentData = $dates->map(
            fn (string $date): int => (int) ($payload['current'][$date] ?? 0),
        );
        $previousData = $dates->map(function (string $date) use ($payload, $period): int {
            $comparisonDate = Carbon::parse($date)->subDays($period)->toDateString();

            return (int) ($payload['previous'][$comparisonDate] ?? 0);
        });

        return [
            'chart' => [
                'type' => 'line',
                'height' => 320,
                'toolbar' => [
                    'show' => true,
                ],
                'zoom' => [
                    'enabled' => true,
                ],
            ],
            'series' => [
                [
                    'name' => 'Текущий период',
                    'data' => $currentData->values()->all(),
                ],
                [
                    'name' => 'Предыдущий период',
                    'data' => $previousData->values()->all(),
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
                    'text' => 'Просмотры',
                ],
                'min' => 0,
                'forceNiceScale' => true,
            ],
            'colors' => ['#2563eb', '#94a3b8'],
            'stroke' => [
                'curve' => 'smooth',
                'width' => [3, 2],
                'dashArray' => [0, 6],
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
            'legend' => [
                'position' => 'top',
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
                'text' => 'Нет просмотров за выбранный период',
            ],
        ];
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
