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
            'editor_id' => null,
            'title' => $title,
            'slug' => null,
            'source_url' => fake()->boolean(70) ? fake()->url() : null,
            'source_guid' => fake()->boolean(70) ? fake()->uuid() : null,
            'image_url' => fake()->boolean(50) ? fake()->imageUrl(1200, 800) : null,
            'image_caption' => fake()->boolean(25) ? fake()->sentence(8, true) : null,
            'short_description' => fake()->optional()->text(200),
            'full_description' => fake()->optional()->paragraphs(3, true),
            'rss_content' => fake()->optional()->paragraphs(2, true),
            'author' => fake()->optional()->name(),
            'author_url' => fake()->boolean(30) ? fake()->url() : null,
            'source_name' => fake()->optional()->company(),
            'status' => fake()->randomElement(['draft', 'pending', 'published', 'archived']),
            'content_type' => fake()->randomElement(['news', 'article', 'opinion', 'analysis', 'interview']),
            'is_featured' => fake()->boolean(10),
            'is_breaking' => fake()->boolean(5),
            'is_pinned' => fake()->boolean(5),
            'is_editors_choice' => fake()->boolean(5),
            'is_sponsored' => fake()->boolean(2),
            'importance' => fake()->numberBetween(1, 10),
            'meta_title' => fake()->boolean(20) ? fake()->sentence(5, true) : null,
            'meta_description' => fake()->boolean(20) ? fake()->sentence(10, true) : null,
            'canonical_url' => fake()->boolean(10) ? fake()->url() : null,
            'structured_data' => null,
            'views_count' => fake()->numberBetween(0, 5000),
            'unique_views_count' => fake()->numberBetween(0, 3000),
            'shares_count' => fake()->numberBetween(0, 500),
            'bookmarks_count' => fake()->numberBetween(0, 300),
            'reading_time' => fake()->numberBetween(1, 15),
            'engagement_score' => fake()->randomFloat(2, 0, 5000),
            'published_at' => fake()->boolean(70) ? fake()->dateTimeBetween('-1 year', 'now') : null,
            'rss_parsed_at' => fake()->boolean(70) ? fake()->dateTimeBetween('-1 month', 'now') : null,
            'last_edited_at' => fake()->boolean(40) ? fake()->dateTimeBetween('-1 month', 'now') : null,
        ];
    }
}
