<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\RssFeed;
use App\Models\SubCategory;
use App\Models\User;
use App\Services\ArticleContentType;
use App\Services\ArticleStatus;
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
            'status' => fake()->randomElement(ArticleStatus::values()),
            'content_type' => fake()->randomElement(ArticleContentType::values()),
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

    public function forCategory(Category|int $category): static
    {
        $categoryId = $category instanceof Category ? $category->getKey() : $category;

        return $this->state(fn (): array => [
            'category_id' => $categoryId,
        ]);
    }

    public function forSubCategory(SubCategory|int $subCategory): static
    {
        return $this->state(function () use ($subCategory): array {
            if ($subCategory instanceof SubCategory) {
                return [
                    'category_id' => $subCategory->category_id,
                    'sub_category_id' => $subCategory->getKey(),
                ];
            }

            return [
                'sub_category_id' => $subCategory,
            ];
        });
    }

    public function forFeed(RssFeed|int $feed): static
    {
        return $this->state(function () use ($feed): array {
            if ($feed instanceof RssFeed) {
                return [
                    'category_id' => $feed->category_id,
                    'rss_feed_id' => $feed->getKey(),
                    'source_name' => $feed->source_name !== '' ? $feed->source_name : fake()->company(),
                ];
            }

            return [
                'rss_feed_id' => $feed,
            ];
        });
    }

    public function editedBy(User|int $user): static
    {
        $editorId = $user instanceof User ? $user->getKey() : $user;

        return $this->state(fn (): array => [
            'editor_id' => $editorId,
            'last_edited_at' => now()->subMinutes(fake()->numberBetween(5, 720)),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Published->value,
            'published_at' => fake()->dateTimeBetween('-30 days', '-5 minutes'),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Draft->value,
            'published_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Pending->value,
            'published_at' => now()->addHours(fake()->numberBetween(1, 48)),
        ]);
    }
}
