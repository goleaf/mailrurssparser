<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6, true);

        return [
            'category_id' => Category::factory(),
            'sub_category_id' => null,
            'rss_feed_id' => null,
            'title' => $title,
            'slug' => null,
            'source_url' => fake()->boolean(70) ? fake()->url() : null,
            'source_guid' => fake()->boolean(70) ? fake()->uuid() : null,
            'image_url' => fake()->boolean(50) ? fake()->imageUrl(1200, 800) : null,
            'short_description' => fake()->optional()->text(200),
            'full_description' => fake()->optional()->paragraphs(3, true),
            'rss_content' => fake()->optional()->paragraphs(2, true),
            'author' => fake()->optional()->name(),
            'source_name' => fake()->optional()->company(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'is_featured' => fake()->boolean(10),
            'is_breaking' => fake()->boolean(5),
            'views_count' => fake()->numberBetween(0, 5000),
            'reading_time' => fake()->numberBetween(1, 15),
            'published_at' => fake()->boolean(70) ? fake()->dateTimeBetween('-1 year', 'now') : null,
            'rss_parsed_at' => fake()->boolean(70) ? fake()->dateTimeBetween('-1 month', 'now') : null,
        ];
    }
}
