<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubCategory>
 */
class SubCategoryFactory extends Factory
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
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->paragraph(),
            'is_active' => true,
            'order' => fake()->numberBetween(0, 20),
        ];
    }

    public function forCategory(Category|int $category): static
    {
        $categoryId = $category instanceof Category ? $category->getKey() : $category;

        return $this->state(fn (): array => [
            'category_id' => $categoryId,
        ]);
    }
}
