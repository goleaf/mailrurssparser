<?php

namespace App\Filament\Widgets\Charts;

use App\Models\Tag;
use Illuminate\Support\Facades\Cache;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class TopTagsChartWidget extends ApexChartWidget
{
    protected static ?string $chartId = 'top-tags-chart';

    protected static ?string $heading = 'Топ тегов';

    protected static ?string $subheading = '15 тегов с наибольшим количеством статей';

    protected static ?int $sort = 15;

    protected static ?int $contentHeight = 380;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getOptions(): array
    {
        /** @var array{labels: array<int, string>, values: array<int, int>} $payload */
        $payload = Cache::remember(
            'charts:top-tags',
            now()->addMinutes(60),
            function (): array {
                $tags = Tag::query()
                    ->withCount('articles')
                    ->orderByDesc('articles_count')
                    ->limit(15)
                    ->get();

                return [
                    'labels' => $tags->pluck('name')->all(),
                    'values' => $tags->map(fn (Tag $tag): int => (int) $tag->articles_count)->all(),
                ];
            },
        );

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 380,
                'toolbar' => [
                    'show' => false,
                ],
            ],
            'plotOptions' => [
                'bar' => [
                    'horizontal' => true,
                    'borderRadius' => 4,
                    'dataLabels' => [
                        'position' => 'top',
                    ],
                ],
            ],
            'series' => [
                [
                    'name' => 'Статьи',
                    'data' => $payload['values'],
                ],
            ],
            'xaxis' => [
                'categories' => $payload['labels'],
            ],
            'yaxis' => [
                'title' => [
                    'text' => '',
                ],
            ],
            'colors' => ['#8b5cf6'],
            'dataLabels' => [
                'enabled' => true,
            ],
            'grid' => [
                'borderColor' => '#e2e8f0',
                'xaxis' => [
                    'lines' => [
                        'show' => true,
                    ],
                ],
            ],
            'noData' => [
                'text' => 'Нет тегов для отображения',
            ],
        ];
    }
}
