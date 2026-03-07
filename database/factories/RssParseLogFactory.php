<?php

namespace Database\Factories;

use App\Models\RssFeed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RssParseLog>
 */
class RssParseLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-7 days', 'now');
        $duration = fake()->numberBetween(100, 5000);

        return [
            'rss_feed_id' => RssFeed::factory(),
            'started_at' => $startedAt,
            'finished_at' => (clone $startedAt)->modify("+{$duration} milliseconds"),
            'new_count' => fake()->numberBetween(0, 20),
            'skip_count' => fake()->numberBetween(0, 20),
            'error_count' => fake()->numberBetween(0, 3),
            'total_items' => fake()->numberBetween(1, 40),
            'duration_ms' => $duration,
            'success' => fake()->boolean(90),
            'error_message' => null,
            'item_errors' => [],
            'triggered_by' => fake()->randomElement(['scheduler', 'manual', 'api', 'filament']),
        ];
    }
}
