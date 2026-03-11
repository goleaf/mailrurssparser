<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillArticles();
        $this->backfillCategories();
    }

    public function down(): void
    {
        //
    }

    private function backfillArticles(): void
    {
        DB::table('articles')
            ->select(['id', 'meta_title', 'meta_description', 'canonical_url', 'image_url'])
            ->orderBy('id')
            ->chunkById(250, function ($articles): void {
                $timestamp = now();
                $rows = [];

                foreach ($articles as $article) {
                    $title = $this->nullableString($article->meta_title);
                    $description = $this->nullableString($article->meta_description);
                    $canonicalUrl = $this->nullableString($article->canonical_url);
                    $image = $this->nullableString($article->image_url);

                    if ($title === null && $description === null && $canonicalUrl === null && $image === null) {
                        continue;
                    }

                    $rows[] = [
                        'model_type' => 'App\\Models\\Article',
                        'model_id' => $article->id,
                        'title' => $title,
                        'description' => $description,
                        'image' => $image,
                        'author' => null,
                        'robots' => null,
                        'canonical_url' => $canonicalUrl,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                if ($rows !== []) {
                    DB::table('seo')->insertOrIgnore($rows);
                }
            });
    }

    private function backfillCategories(): void
    {
        DB::table('categories')
            ->select(['id', 'meta_title', 'meta_description'])
            ->orderBy('id')
            ->chunkById(250, function ($categories): void {
                $timestamp = now();
                $rows = [];

                foreach ($categories as $category) {
                    $title = $this->nullableString($category->meta_title);
                    $description = $this->nullableString($category->meta_description);

                    if ($title === null && $description === null) {
                        continue;
                    }

                    $rows[] = [
                        'model_type' => 'App\\Models\\Category',
                        'model_id' => $category->id,
                        'title' => $title,
                        'description' => $description,
                        'image' => null,
                        'author' => null,
                        'robots' => null,
                        'canonical_url' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                if ($rows !== []) {
                    DB::table('seo')->insertOrIgnore($rows);
                }
            });
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
};
