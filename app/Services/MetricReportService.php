<?php

namespace App\Services;

use App\Models\Metric;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MetricReportService
{
    public function total(TrackedMetric|string $metric, int $hours = 24): int
    {
        return $this->totals([$metric], $hours)[$this->metricName($metric)] ?? 0;
    }

    /**
     * @param  array<int, TrackedMetric|string>  $metrics
     * @return array<string, int>
     */
    public function totals(array $metrics, int $hours = 24): array
    {
        $names = collect($metrics)
            ->pluck(fn (TrackedMetric|string $metric): string => $this->metricName($metric))
            ->values();
        $totals = Metric::query()
            ->selectRaw('name, SUM(value) as aggregate')
            ->whereIn('name', $names)
            ->where('bucket_start', '>=', $this->startBucket($hours))
            ->groupBy('name')
            ->pluck('aggregate', 'name');

        return $names
            ->pluck(
                fn (string $name): int => (int) ($totals[$name] ?? 0),
                fn (string $name): string => $name,
            )
            ->all();
    }

    /**
     * @param  array<int, TrackedMetric|string>  $metrics
     * @return array{
     *     labels: list<string>,
     *     hours: int,
     *     from: string,
     *     to: string,
     *     series: list<array{
     *         key: string,
     *         label: string,
     *         category: string|null,
     *         total: int,
     *         data: list<int>
     *     }>
     * }
     */
    public function timeline(array $metrics, int $hours = 24): array
    {
        $hours = min(max($hours, 1), 168);
        $start = $this->startBucket($hours);
        $buckets = collect(range(0, $hours - 1))
            ->map(fn (int $offset): CarbonImmutable => $start->addHours($offset));
        $names = collect($metrics)
            ->pluck(fn (TrackedMetric|string $metric): string => $this->metricName($metric))
            ->values();
        $rows = Metric::query()
            ->selectRaw('name, bucket_start, SUM(value) as aggregate')
            ->whereIn('name', $names)
            ->where('bucket_start', '>=', $start)
            ->groupBy('name', 'bucket_start')
            ->orderBy('bucket_start')
            ->get()
            ->groupBy('name');

        return [
            'labels' => $buckets
                ->map(fn (CarbonImmutable $bucket): string => $bucket->format('Y-m-d H:00'))
                ->all(),
            'hours' => $hours,
            'from' => $start->toIso8601String(),
            'to' => $buckets->last()?->endOfHour()->toIso8601String() ?? $start->endOfHour()->toIso8601String(),
            'series' => collect($metrics)
                ->map(function (TrackedMetric|string $metric) use ($buckets, $rows): array {
                    $trackedMetric = $metric instanceof TrackedMetric ? $metric : null;
                    $name = $this->metricName($metric);
                    $points = collect($rows->get($name, []))
                        ->pluck(
                            fn (Metric $row): int => (int) ($row->aggregate ?? $row->value),
                            fn (Metric $row): string => CarbonImmutable::parse($row->bucket_start)->format('Y-m-d H:00'),
                        );

                    return [
                        'key' => $name,
                        'label' => $trackedMetric?->label() ?? Str::headline(str_replace('_', ' ', $name)),
                        'category' => $trackedMetric?->category(),
                        'total' => (int) $points->sum(),
                        'data' => $buckets
                            ->map(fn (CarbonImmutable $bucket): int => (int) ($points[$bucket->format('Y-m-d H:00')] ?? 0))
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return array<int, array{model: TModel, total: int}>
     */
    public function topMeasurables(
        TrackedMetric|string $metric,
        string $modelClass,
        int $hours = 168,
        int $limit = 5,
    ): array {
        /** @var TModel $prototype */
        $prototype = new $modelClass;
        $metricName = $this->metricName($metric);
        $rows = Metric::query()
            ->selectRaw('measurable_id, SUM(value) as aggregate')
            ->where('name', $metricName)
            ->where('measurable_type', $prototype->getMorphClass())
            ->whereNotNull('measurable_id')
            ->where('bucket_start', '>=', $this->startBucket($hours))
            ->groupBy('measurable_id')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get();
        $models = $modelClass::query()
            ->whereIn($prototype->getKeyName(), $rows->pluck('measurable_id'))
            ->get()
            ->keyBy($prototype->getKeyName());

        return $rows
            ->map(function (Metric $row) use ($models): ?array {
                $model = $models->get($row->measurable_id);

                if ($model === null) {
                    return null;
                }

                return [
                    'model' => $model,
                    'total' => (int) ($row->aggregate ?? $row->value),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function metricName(TrackedMetric|string $metric): string
    {
        return $metric instanceof TrackedMetric ? $metric->value : $metric;
    }

    private function startBucket(int $hours): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfHour()->subHours(max($hours, 1) - 1);
    }
}
