<?php

namespace App\Filament\Widgets;

use App\Models\ArticleView;
use Filament\Widgets\ChartWidget;

class ViewsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Просмотры за 30 дней';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $points = ArticleView::query()
            ->selectRaw("strftime('%Y-%m-%d', viewed_at) as date, COUNT(*) as count")
            ->where('viewed_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        return [
            'datasets' => [
                [
                    'label' => 'Просмотры',
                    'data' => array_map('intval', $points->values()->all()),
                    'borderColor' => '#1d4ed8',
                    'backgroundColor' => 'rgba(29, 78, 216, 0.15)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $points->keys()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'elements' => [
                'point' => [
                    'radius' => 3,
                    'hoverRadius' => 5,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => true,
                    ],
                ],
                'y' => [
                    'grid' => [
                        'display' => true,
                    ],
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
