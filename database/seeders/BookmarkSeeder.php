<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Bookmark;
use Illuminate\Database\Seeder;

class BookmarkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Article::query()
            ->latest('published_at')
            ->limit(20)
            ->get()
            ->each(function (Article $article): void {
                Bookmark::factory()
                    ->count(3)
                    ->forArticle($article)
                    ->create();

                $article->forceFill([
                    'bookmarks_count' => $article->bookmarkedBy()->count(),
                ])->save();
            });
    }
}
