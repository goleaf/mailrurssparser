<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'rss_url' => fake()->boolean(40) ? fake()->url() : null,
            'rss_key' => fake()->boolean(40) ? fake()->unique()->word() : null,
            'color' => '#3B82F6',
            'icon' => fake()->boolean(20) ? fake()->randomElement(['newspaper', 'tag', 'rss']) : null,
            'description' => fake()->optional()->paragraph(),
            'order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
