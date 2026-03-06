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
            'is_active' => true,
            'last_parsed_at' => fake()->boolean(60) ? fake()->dateTime() : null,
            'articles_parsed_total' => fake()->numberBetween(0, 1000),
            'last_run_new_count' => fake()->numberBetween(0, 50),
            'last_run_skip_count' => fake()->numberBetween(0, 50),
            'last_error' => null,
        ];
    }
}
