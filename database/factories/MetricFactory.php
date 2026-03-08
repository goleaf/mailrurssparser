<?php

namespace Database\Factories;

use App\Models\Metric;
use App\Services\TrackedMetric;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Metric>
 */
class MetricFactory extends Factory
{
    protected $model = Metric::class;

    public function definition(): array
    {
        $metric = fake()->randomElement(TrackedMetric::cases());
        $bucketStart = CarbonImmutable::instance(
            fake()->dateTimeBetween('-2 days', 'now'),
        )->startOfHour();

        return [
            'name' => $metric->value,
            'category' => $metric->category(),
            'measurable_type' => null,
            'measurable_id' => null,
            'bucket_start' => $bucketStart,
            'bucket_date' => $bucketStart->toDateString(),
            'fingerprint' => hash('sha1', implode('|', [
                $metric->value,
                $metric->category(),
                '-',
                '-',
                $bucketStart->format('Y-m-d H:00:00'),
            ])),
            'value' => fake()->numberBetween(1, 50),
        ];
    }

    public function forMeasurable(Model $model): static
    {
        return $this->state(function (array $attributes) use ($model): array {
            $bucketStart = CarbonImmutable::parse($attributes['bucket_start'] ?? now())->startOfHour();

            return [
                'measurable_type' => $model->getMorphClass(),
                'measurable_id' => $model->getKey(),
                'fingerprint' => hash('sha1', implode('|', [
                    $attributes['name'],
                    $attributes['category'] ?? '-',
                    $model->getMorphClass(),
                    $model->getKey(),
                    $bucketStart->format('Y-m-d H:00:00'),
                ])),
            ];
        });
    }
}
