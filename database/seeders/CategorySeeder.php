<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\RssFeed;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $feeds = config('rss.feeds', []);

        foreach ($feeds as $index => $feed) {
            $category = Category::updateOrCreate(
                ['slug' => $feed['category_slug']],
                [
                    'name' => $feed['category_name'],
                    'slug' => $feed['category_slug'],
                    'rss_url' => $feed['url'],
                    'rss_key' => $feed['category_slug'],
                    'color' => $feed['category_color'],
                    'icon' => $feed['category_icon'],
                    'is_active' => true,
                    'order' => $index,
                ],
            );

            RssFeed::updateOrCreate(
                ['url' => $feed['url']],
                [
                    'category_id' => $category->id,
                    'title' => $feed['title'],
                    'url' => $feed['url'],
                    'is_active' => true,
                ],
            );

            $this->command?->line('✅ Created category: '.$category->name);
        }
    }
}
