<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultSourceName = (string) config('rss.source_name', '');

        Config::collection('rss.feeds', [])
            ->filter(fn (mixed $feed): bool => is_array($feed))
            ->values()
            ->each(function (array $feed, int $index) use ($defaultSourceName): void {
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

                [$subCategoryName, $subCategorySlug] = $this->resolveConfiguredSubCategory($feed, $category->name);
                $subCategory = null;

                if ($subCategoryName !== null) {
                    $subCategory = SubCategory::updateOrCreate(
                        [
                            'category_id' => $category->id,
                            'slug' => $subCategorySlug,
                        ],
                        [
                            'name' => $subCategoryName,
                            'description' => null,
                            'is_active' => true,
                            'order' => 0,
                        ],
                    );
                }

                $existingFeed = RssFeed::query()->where('url', $feed['url'])->first();
                $extraSettings = array_replace(
                    is_array($existingFeed?->extra_settings) ? $existingFeed->extra_settings : [],
                    array_filter([
                        'sub_category_name' => $subCategory?->name,
                        'sub_category_slug' => $subCategory?->slug,
                    ]),
                );

                $feedModel = RssFeed::updateOrCreate(
                    ['url' => $feed['url']],
                    [
                        'category_id' => $category->id,
                        'title' => $feed['title'],
                        'url' => $feed['url'],
                        'source_name' => (string) ($feed['source_name'] ?? $defaultSourceName),
                        'is_active' => true,
                        'extra_settings' => $extraSettings !== [] ? $extraSettings : null,
                    ],
                );

                Article::query()
                    ->where('rss_feed_id', $feedModel->id)
                    ->update([
                        'category_id' => $category->id,
                        'sub_category_id' => $subCategory?->id,
                        'source_name' => $feedModel->source_name !== '' ? $feedModel->source_name : null,
                    ]);

                Article::query()
                    ->where('rss_feed_id', $feedModel->id)
                    ->where('author', 'like', '%Mail%')
                    ->update([
                        'author' => (string) config('rss.article.default_author', 'Редакция'),
                    ]);

                $this->command?->line('✅ Created category: '.$category->name);
            });
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveConfiguredSubCategory(array $feed, string $categoryName): array
    {
        $name = trim((string) ($feed['sub_category_name'] ?? ''));
        $slug = trim((string) ($feed['sub_category_slug'] ?? ''));

        if ($name === '' && is_string($feed['title'] ?? null) && str_contains($feed['title'], ':')) {
            [$feedCategoryName, $feedSubCategoryName] = array_map('trim', explode(':', (string) $feed['title'], 2));

            if (
                $feedSubCategoryName !== ''
                && Str::lower($feedCategoryName) === Str::lower($categoryName)
            ) {
                $name = $feedSubCategoryName;
            }
        }

        if ($name === '' && $slug === '') {
            return [null, null];
        }

        if ($slug === '' && $name !== '') {
            $slug = Str::slug($name);
        }

        return [$name !== '' ? $name : null, $slug !== '' ? $slug : null];
    }
}
