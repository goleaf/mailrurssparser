<?php

namespace App\Filament\Widgets\Charts;

use App\Models\Article;
use App\Models\Category;
use Carbon\CarbonInterface;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class ArticlePublicationTrendWidget extends ApexChartWidget
{
    use HasFiltersSchema;

    protected static ?string $chartId = 'article-publication-trend';

    protected static ?string $heading = 'Динамика публикаций';

    protected static ?string $subheading = 'Количество опубликованных статей по дням';

    protected static ?int $sort = 10;

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
            Select::make('category_id')
                ->label('Рубрика')
                ->options($this->getCategoryOptions())
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
        $period = (int) ($this->filters['period'] ?? 30);
        $categoryId = filled($this->filters['category_id'] ?? null)
            ? (int) $this->filters['category_id']
            : null;
        $rangeStart = now()->subDays($period - 1)->startOfDay();

        /** @var array<string, int|string|null> $rows */
        $rows = Cache::remember(
            "charts:article-publication-trend:{$period}:".($categoryId ?? 'all'),
            now()->addMinutes(15),
            function () use ($categoryId, $rangeStart): array {
                $query = Article::query()
                    ->published()
                    ->selectRaw('DATE(published_at) as date, COUNT(*) as count')
                    ->whereNotNull('published_at')
                    ->where('published_at', '>=', $rangeStart)
                    ->groupBy('date')
                    ->orderBy('date');

                if ($categoryId !== null) {
                    $query->where('category_id', $categoryId);
                }

                return $query->pluck('count', 'date')->all();
            },
        );

        $dates = $this->buildDateRange($rangeStart, $period);
        $series = $dates->map(
            fn (string $date): int => (int) ($rows[$date] ?? 0),
        );

        return [
            'chart' => [
                'type' => 'area',
                'height' => 320,
                'toolbar' => [
                    'show' => true,
                ],
                'zoom' => [
                    'enabled' => true,
                ],
                'animations' => [
                    'enabled' => true,
                ],
            ],
            'series' => [
                [
                    'name' => 'Статьи',
                    'data' => $series->values()->all(),
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
                    'text' => 'Статьи',
                ],
                'min' => 0,
                'forceNiceScale' => true,
            ],
            'fill' => [
                'type' => 'gradient',
                'gradient' => [
                    'shadeIntensity' => 1,
                    'opacityFrom' => 0.55,
                    'opacityTo' => 0.12,
                    'stops' => [0, 95, 100],
                ],
            ],
            'colors' => ['#2563eb'],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 3,
            ],
            'dataLabels' => [
                'enabled' => false,
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
                'text' => 'Нет публикаций за выбранный период',
            ],
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function getCategoryOptions(): array
    {
        return ['' => 'Все рубрики'] + Category::query()
            ->orderBy('name')
            ->pluck('name', 'id')
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
