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
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'session_hash' => hash('sha256', (string) fake()->uuid()),
            'country_code' => fake()->countryCode(),
            'timezone' => fake()->timezone(),
            'locale' => fake()->randomElement(['en', 'ru', 'de', 'fr', 'pl']),
            'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'referrer_type' => fake()->randomElement(['direct', 'search', 'social', 'internal']),
            'referrer_domain' => fake()->optional()->domainName(),
            'ip_address' => fake()->ipv4(),
            'session_id' => fake()->optional()->uuid(),
            'user_agent' => fake()->optional()->userAgent(),
            'referer' => fake()->optional()->url(),
            'viewed_at' => fake()->dateTimeBetween('-2 days', 'now'),
        ];
    }
}
