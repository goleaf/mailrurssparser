<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;

class SubCategorySeeder extends Seeder
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

        $categories->each(function (Category $category): void {
            $missing = max(0, 20 - $category->subCategories()->count());

            if ($missing === 0) {
                return;
            }

            SubCategory::factory()
                ->count($missing)
                ->forCategory($category)
                ->create();
        });
    }
}
