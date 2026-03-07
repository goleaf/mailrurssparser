<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tag>
 */
class TagFactory extends Factory
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
            'color' => '#6B7280',
            'description' => fake()->boolean(40) ? fake()->sentence(10, true) : null,
            'usage_count' => fake()->numberBetween(0, 100),
            'is_trending' => fake()->boolean(10),
            'is_featured' => fake()->boolean(15),
        ];
    }
}
