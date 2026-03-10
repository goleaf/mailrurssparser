<?php

namespace App\Filament\Widgets\Charts;

use App\Models\Article;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Facades\Cache;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class CategoryBreakdownChartWidget extends ApexChartWidget
{
    use HasFiltersSchema;

    protected static ?string $chartId = 'category-breakdown-chart';

    protected static ?string $heading = 'Распределение по рубрикам';

    protected static ?string $subheading = 'Доля публикаций по рубрикам';

    protected static ?int $sort = 12;

    protected static ?int $contentHeight = 360;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label('Период')
                ->options([
                    'all' => 'За всё время',
                    '30' => 'Последние 30 дней',
                    '7' => 'Последние 7 дней',
                ])
                ->default('all')
                ->native(false),
        ]);
    }

    public function updatedInteractsWithSchemas(string $statePath): void
    {
        $this->updateOptions();
    }

    protected function getOptions(): array
    {
        $period = (string) ($this->filters['period'] ?? 'all');

        /** @var array{labels: array<int, string>, series: array<int, int>, colors: array<int, string>} $payload */
        $payload = Cache::remember(
            "charts:category-breakdown:{$period}",
            now()->addMinutes(30),
            function () use ($period): array {
                $query = Article::query()
                    ->published()
                    ->selectRaw('category_id, COUNT(*) as aggregate_count')
                    ->with('category')
                    ->groupBy('category_id')
                    ->orderByDesc('aggregate_count');

                if ($period !== 'all') {
                    $query->where('published_at', '>=', now()->subDays((int) $period)->startOfDay());
                }

                $rows = $query->get();

                return [
                    'labels' => $rows->map(
                        fn (Article $article): string => (string) ($article->category?->name ?? 'Без рубрики'),
                    )->all(),
                    'series' => $rows->map(
                        fn (Article $article): int => (int) $article->aggregate_count,
                    )->all(),
                    'colors' => $rows->map(
                        fn (Article $article): string => (string) ($article->category?->color ?? '#64748b'),
                    )->all(),
                ];
            },
        );

        return [
            'chart' => [
                'type' => 'donut',
                'height' => 360,
            ],
            'series' => $payload['series'],
            'labels' => $payload['labels'],
            'colors' => $payload['colors'],
            'legend' => [
                'position' => 'bottom',
            ],
            'dataLabels' => [
                'enabled' => true,
            ],
            'plotOptions' => [
                'pie' => [
                    'donut' => [
                        'size' => '65%',
                        'labels' => [
                            'show' => true,
                            'total' => [
                                'show' => true,
                                'label' => 'Статей',
                            ],
                        ],
                    ],
                ],
            ],
            'noData' => [
                'text' => 'Нет публикаций для отображения',
            ],
        ];
    }
}
