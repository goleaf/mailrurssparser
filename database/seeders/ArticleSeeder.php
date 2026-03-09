<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::query()
            ->with(['subCategories', 'rssFeeds'])
            ->get();

        if ($categories->isEmpty()) {
            $categories = Category::factory()->count(5)->create();
        }

        $editors = User::query()
            ->whereNotNull('email_verified_at')
            ->get();

        if ($editors->count() < 5) {
            $editors = $editors->concat(
                User::factory()
                    ->count(5 - $editors->count())
                    ->create(['email_verified_at' => now()]),
            );
        }

        $tags = Tag::query()->get();

        if ($tags->count() < 20) {
            $tags = $tags->concat(Tag::factory()->count(20 - $tags->count())->create());
        }

        Article::withoutSyncingToSearch(function () use ($categories, $editors, $tags): void {
            $categories->each(function (Category $category) use ($editors, $tags): void {
                $missing = max(0, 20 - $category->articles()->count());

                if ($missing === 0) {
                    return;
                }

                $subCategories = $category->subCategories;
                $feeds = $category->rssFeeds;

                Article::factory()
                    ->count($missing)
                    ->published()
                    ->forCategory($category)
                    ->state(function () use ($subCategories, $feeds, $editors): array {
                        $subCategory = $subCategories->isNotEmpty() ? $subCategories->random() : null;
                        $feed = $feeds->isNotEmpty() ? $feeds->random() : null;
                        $editor = $editors->random();

                        return [
                            'sub_category_id' => $subCategory?->id,
                            'rss_feed_id' => $feed?->id,
                            'editor_id' => $editor->id,
                            'source_name' => filled($feed?->source_name) ? $feed->source_name : fake()->company(),
                            'last_edited_at' => now()->subMinutes(fake()->numberBetween(5, 720)),
                        ];
                    })
                    ->create()
                    ->each(function (Article $article) use ($tags): void {
                        $article->tags()->sync(
                            $tags->random(random_int(3, min(6, $tags->count())))
                                ->pluck('id')
                                ->all(),
                        );
                    });
            });
        });

        Category::query()->each(function (Category $category): void {
            $category->forceFill([
                'articles_count_cache' => $category->articles()->count(),
            ])->save();
        });

        Tag::query()->each(function (Tag $tag): void {
            $tag->forceFill([
                'usage_count' => $tag->articles()->count(),
            ])->save();
        });

        $articlesByCategory = Article::query()
            ->select(['id', 'category_id'])
            ->get()
            ->groupBy('category_id');

        Article::query()
            ->select(['id', 'category_id'])
            ->get()
            ->each(function (Article $article) use ($articlesByCategory): void {
                $relatedIds = ($articlesByCategory->get($article->category_id) ?? collect())
                    ->reject(fn (Article $candidate): bool => $candidate->id === $article->id)
                    ->shuffle()
                    ->take(3)
                    ->pluck('id')
                    ->all();

                if ($relatedIds === []) {
                    return;
                }

                $article->relatedArticles()->syncWithoutDetaching(
                    collect($relatedIds)->mapWithKeys(fn (int $relatedId): array => [
                        $relatedId => [
                            'score' => random_int(60, 95),
                            'shared_tags_count' => random_int(1, 4),
                            'shared_terms_count' => random_int(2, 6),
                            'same_category' => true,
                            'same_sub_category' => random_int(0, 1) === 1,
                            'same_content_type' => random_int(0, 1) === 1,
                            'same_author' => random_int(0, 1) === 1,
                            'same_source' => random_int(0, 1) === 1,
                        ],
                    ])->all(),
                );
            });
    }
}
