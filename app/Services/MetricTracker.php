<?php

namespace App\Services;

use App\Models\Metric;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MetricTracker
{
    public function record(
        TrackedMetric|string $metric,
        int $value = 1,
        ?Model $measurable = null,
        ?CarbonInterface $recordedAt = null,
        ?string $category = null,
    ): void {
        if ($value < 1) {
            return;
        }

        $this->recordMany([
            [
                'metric' => $metric,
                'value' => $value,
                'measurable' => $measurable,
                'recorded_at' => $recordedAt,
                'category' => $category,
            ],
        ]);
    }

    /**
     * @param  array<int, array{
     *     metric: TrackedMetric|string,
     *     value?: int,
     *     measurable?: Model|null,
     *     recorded_at?: CarbonInterface|null,
     *     category?: string|null
     * }>  $entries
     */
    public function recordMany(array $entries): void
    {
        collect($entries)
            ->map(fn (array $entry): array => $this->normalizeEntry($entry))
            ->filter(fn (array $entry): bool => $entry['value'] > 0)
            ->groupBy('fingerprint')
            ->map(function (Collection $group): array {
                $entry = $group->first();
                $entry['value'] = (int) $group->sum('value');

                return $entry;
            })
            ->each(fn (array $entry): bool => $this->persist($entry));
    }

    /**
     * @param  array{
     *     metric: TrackedMetric|string,
     *     value?: int,
     *     measurable?: Model|null,
     *     recorded_at?: CarbonInterface|null,
     *     category?: string|null
     * }  $entry
     * @return array{
     *     name: string,
     *     category: string|null,
     *     measurable_type: string|null,
     *     measurable_id: int|string|null,
     *     bucket_start: CarbonInterface,
     *     bucket_date: string,
     *     fingerprint: string,
     *     value: int
     * }
     */
    private function normalizeEntry(array $entry): array
    {
        $trackedMetric = $entry['metric'] instanceof TrackedMetric ? $entry['metric'] : null;
        $name = $trackedMetric?->value ?? (string) $entry['metric'];
        $category = array_key_exists('category', $entry)
            ? $entry['category']
            : $trackedMetric?->category();
        $recordedAt = ($entry['recorded_at'] ?? now())->startOfHour();
        $measurable = $entry['measurable'] ?? null;
        $measurableType = $measurable?->getMorphClass();
        $measurableId = $measurable?->getKey();

        return [
            'name' => $name,
            'category' => $category,
            'measurable_type' => $measurableType,
            'measurable_id' => $measurableId,
            'bucket_start' => $recordedAt,
            'bucket_date' => $recordedAt->toDateString(),
            'fingerprint' => $this->fingerprint(
                $name,
                $category,
                $measurableType,
                $measurableId,
                $recordedAt,
            ),
            'value' => max(0, (int) ($entry['value'] ?? 1)),
        ];
    }

    /**
     * @param  array{
     *     name: string,
     *     category: string|null,
     *     measurable_type: string|null,
     *     measurable_id: int|string|null,
     *     bucket_start: CarbonInterface,
     *     bucket_date: string,
     *     fingerprint: string,
     *     value: int
     * }  $entry
     */
    private function persist(array $entry): bool
    {
        $updated = Metric::query()
            ->where('fingerprint', $entry['fingerprint'])
            ->increment('value', $entry['value'], ['updated_at' => now()]);

        if ($updated > 0) {
            return true;
        }

        try {
            Metric::query()->create($entry);

            return true;
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            Metric::query()
                ->where('fingerprint', $entry['fingerprint'])
                ->increment('value', $entry['value'], ['updated_at' => now()]);

            return true;
        }
    }

    private function fingerprint(
        string $name,
        ?string $category,
        ?string $measurableType,
        int|string|null $measurableId,
        CarbonInterface $bucketStart,
    ): string {
        return hash('sha1', implode('|', [
            $name,
            $category ?? '-',
            $measurableType ?? '-',
            $measurableId ?? '-',
            $bucketStart->format('Y-m-d H:00:00'),
        ]));
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'constraint');
    }
}
