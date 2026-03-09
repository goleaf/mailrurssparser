<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->optional()->name(),
            'category_ids' => fake()->boolean(50) ? [fake()->numberBetween(1, 5)] : null,
            'token' => Str::random(64),
            'confirmed' => fake()->boolean(70),
            'confirmed_at' => fake()->boolean(70) ? fake()->dateTimeBetween('-1 month', 'now') : null,
            'unsubscribed_at' => null,
            'ip_address' => fake()->ipv4(),
            'country_code' => fake()->optional()->countryCode(),
            'timezone' => fake()->optional()->timezone(),
            'locale' => fake()->optional()->randomElement(['en', 'ru', 'de', 'fr', 'pl']),
        ];
    }

    /**
     * @param  list<int>  $categoryIds
     */
    public function withCategories(array $categoryIds): static
    {
        return $this->state(fn (): array => [
            'category_ids' => $categoryIds,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'confirmed' => true,
            'confirmed_at' => fake()->dateTimeBetween('-30 days', '-1 hour'),
            'unsubscribed_at' => null,
        ]);
    }

    public function unconfirmed(): static
    {
        return $this->state(fn (): array => [
            'confirmed' => false,
            'confirmed_at' => null,
            'unsubscribed_at' => null,
        ]);
    }

    public function unsubscribed(): static
    {
        return $this->state(fn (): array => [
            'confirmed' => true,
            'confirmed_at' => fake()->dateTimeBetween('-30 days', '-2 days'),
            'unsubscribed_at' => fake()->dateTimeBetween('-48 hours', 'now'),
        ]);
    }
}
