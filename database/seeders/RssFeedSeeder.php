<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\RssFeed;
use Illuminate\Database\Seeder;

class RssFeedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::query()->get();

        if ($categories->isEmpty()) {
            $categories = Category::factory()->count(5)->create();
        }

        $missing = max(0, 20 - RssFeed::query()->count());

        if ($missing === 0) {
            return;
        }

        $perCategory = max(1, (int) ceil($missing / max(1, $categories->count())));

        $categories->each(function (Category $category) use ($perCategory): void {
            RssFeed::factory()
                ->count($perCategory)
                ->forCategory($category)
                ->active()
                ->create([
                    'source_name' => $category->name,
                ]);
        });
    }
}
