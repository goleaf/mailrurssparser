<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RssFeed>
 */
class RssFeedFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'title' => fake()->words(3, true),
            'url' => fake()->unique()->url(),
            'source_name' => (string) config('rss.source_name', ''),
            'language' => 'ru',
            'is_active' => true,
            'auto_publish' => true,
            'auto_featured' => false,
            'fetch_interval' => fake()->randomElement([5, 10, 15, 30]),
            'last_parsed_at' => fake()->boolean(60) ? fake()->dateTime() : null,
            'next_parse_at' => fake()->boolean(60) ? fake()->dateTimeBetween('now', '+1 day') : null,
            'articles_parsed_total' => fake()->numberBetween(0, 1000),
            'last_run_new_count' => fake()->numberBetween(0, 50),
            'last_run_skip_count' => fake()->numberBetween(0, 50),
            'last_run_error_count' => fake()->numberBetween(0, 3),
            'consecutive_failures' => fake()->numberBetween(0, 2),
            'last_error' => null,
            'extra_settings' => null,
        ];
    }
}
