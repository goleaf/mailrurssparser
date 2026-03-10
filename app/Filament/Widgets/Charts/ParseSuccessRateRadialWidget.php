<?php

namespace App\Filament\Widgets\Charts;

use App\Models\RssParseLog;
use Illuminate\Support\Facades\Cache;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class ParseSuccessRateRadialWidget extends ApexChartWidget
{
    protected static ?string $chartId = 'parse-success-rate-radial';

    protected static ?string $heading = 'Успешность RSS-парсинга';

    protected static ?string $subheading = 'За последние 7 дней';

    protected static ?int $sort = 17;

    protected static ?int $contentHeight = 280;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getOptions(): array
    {
        /** @var array{rate: int, color: string} $payload */
        $payload = Cache::remember(
            'charts:parse-success-rate-radial',
            now()->addMinutes(5),
            function (): array {
                $rangeStart = now()->subDays(6)->startOfDay();
                $total = RssParseLog::query()
                    ->whereNotNull('started_at')
                    ->where('started_at', '>=', $rangeStart)
                    ->count();
                $successful = RssParseLog::query()
                    ->whereNotNull('started_at')
                    ->where('started_at', '>=', $rangeStart)
                    ->where('success', true)
                    ->count();

                $rate = $total > 0
                    ? (int) round(($successful / $total) * 100)
                    : 0;
                $color = $rate >= 90
                    ? '#16a34a'
                    : ($rate >= 70 ? '#f59e0b' : '#ef4444');

                return [
                    'rate' => $rate,
                    'color' => $color,
                ];
            },
        );

        return [
            'chart' => [
                'type' => 'radialBar',
                'height' => 280,
                'offsetY' => -10,
            ],
            'series' => [$payload['rate']],
            'plotOptions' => [
                'radialBar' => [
                    'startAngle' => -135,
                    'endAngle' => 135,
                    'hollow' => [
                        'size' => '65%',
                    ],
                    'track' => [
                        'background' => '#e2e8f0',
                        'strokeWidth' => '100%',
                    ],
                    'dataLabels' => [
                        'name' => [
                            'fontSize' => '14px',
                            'offsetY' => -10,
                        ],
                        'value' => [
                            'fontSize' => '28px',
                            'offsetY' => 5,
                        ],
                    ],
                ],
            ],
            'colors' => [$payload['color']],
            'labels' => ['Успешность'],
            'stroke' => [
                'lineCap' => 'round',
            ],
        ];
    }
}
