<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ArticleView>
 */
class ArticleViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'ip_address' => fake()->ipv4(),
            'session_id' => fake()->optional()->uuid(),
            'user_agent' => fake()->optional()->userAgent(),
            'referer' => fake()->optional()->url(),
            'viewed_at' => fake()->dateTimeBetween('-2 days', 'now'),
        ];
    }
}
