<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Seeder;

class NewsletterSubscriberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::query()->limit(6)->pluck('id')->all();
        $missing = max(0, 20 - NewsletterSubscriber::query()->count());

        if ($missing > 0) {
            NewsletterSubscriber::factory()
                ->count($missing)
                ->confirmed()
                ->state(function () use ($categories): array {
                    $selectedCategoryIds = collect($categories)
                        ->shuffle()
                        ->take(random_int(1, max(1, min(3, count($categories)))))
                        ->values()
                        ->all();

                    return [
                        'category_ids' => $selectedCategoryIds !== [] ? $selectedCategoryIds : null,
                    ];
                })
                ->create();
        }

        NewsletterSubscriber::factory()
            ->count(5)
            ->unconfirmed()
            ->create();

        NewsletterSubscriber::factory()
            ->count(5)
            ->unsubscribed()
            ->create();
    }
}
