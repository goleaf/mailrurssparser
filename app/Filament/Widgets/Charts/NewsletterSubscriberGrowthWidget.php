<?php

namespace App\Filament\Widgets\Charts;

use App\Models\NewsletterSubscriber;
use Carbon\CarbonInterface;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class NewsletterSubscriberGrowthWidget extends ApexChartWidget
{
    use HasFiltersSchema;

    protected static ?string $chartId = 'newsletter-subscriber-growth';

    protected static ?string $heading = 'Рост подписчиков';

    protected static ?string $subheading = 'Подтверждённые подписки по дням и накопительным итогом';

    protected static ?int $sort = 16;

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
                    '30' => 'Последние 30 дней',
                    '90' => 'Последние 90 дней',
                    '180' => 'Последние 180 дней',
                ])
                ->default('90')
                ->native(false),
        ]);
    }

    public function updatedInteractsWithSchemas(string $statePath): void
    {
        $this->updateOptions();
    }

    protected function getOptions(): array
    {
        $period = (int) ($this->filters['period'] ?? 90);
        $rangeStart = now()->subDays($period - 1)->startOfDay();

        /** @var array{daily: array<string, int|string|null>, baseline: int} $payload */
        $payload = Cache::remember(
            "charts:newsletter-subscriber-growth:{$period}",
            now()->addMinutes(30),
            function () use ($rangeStart): array {
                $daily = NewsletterSubscriber::query()
                    ->whereNotNull('confirmed_at')
                    ->selectRaw('DATE(confirmed_at) as date, COUNT(*) as count')
                    ->where('confirmed_at', '>=', $rangeStart)
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('count', 'date')
                    ->all();

                $baseline = NewsletterSubscriber::query()
                    ->whereNotNull('confirmed_at')
                    ->where('confirmed_at', '<', $rangeStart)
                    ->count();

                return [
                    'daily' => $daily,
                    'baseline' => $baseline,
                ];
            },
        );

        $dates = $this->buildDateRange($rangeStart, $period);
        $dailyData = [];
        $cumulativeData = [];
        $runningTotal = (int) $payload['baseline'];

        foreach ($dates as $date) {
            $dayCount = (int) ($payload['daily'][$date] ?? 0);
            $runningTotal += $dayCount;
            $dailyData[] = $dayCount;
            $cumulativeData[] = $runningTotal;
        }

        return [
            'chart' => [
                'type' => 'area',
                'height' => 320,
                'toolbar' => [
                    'show' => true,
                ],
            ],
            'series' => [
                [
                    'name' => 'Всего подписчиков',
                    'data' => $cumulativeData,
                ],
                [
                    'name' => 'Новых за день',
                    'data' => $dailyData,
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
                [
                    'title' => [
                        'text' => 'Всего подписчиков',
                    ],
                    'min' => 0,
                ],
                [
                    'opposite' => true,
                    'title' => [
                        'text' => 'Новых за день',
                    ],
                    'min' => 0,
                ],
            ],
            'colors' => ['#10b981', '#6ee7b7'],
            'fill' => [
                'type' => 'gradient',
                'gradient' => [
                    'opacityFrom' => 0.55,
                    'opacityTo' => 0.1,
                    'stops' => [0, 95, 100],
                ],
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => [3, 2],
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
                'text' => 'Нет подтверждённых подписок за выбранный период',
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
