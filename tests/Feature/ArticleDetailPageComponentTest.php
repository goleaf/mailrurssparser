<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;

it('renders the blade article page without the legacy metadata footer block', function () {
    $category = Category::factory()->create([
        'name' => 'Технологии',
        'slug' => 'technology',
    ]);
    $tag = Tag::factory()->create([
        'name' => 'ИИ',
        'slug' => 'ai',
    ]);

    $article = Article::factory()
        ->published()
        ->forCategory($category)
        ->create([
            'title' => 'Новая серверная статья',
            'slug' => 'new-server-rendered-article',
            'short_description' => 'Краткое описание новой статьи.',
            'full_description' => '<p>Основной текст статьи.</p>',
            'source_name' => 'Пример источника',
            'source_url' => 'https://example.com/original-article',
            'views_count' => 1234,
            'reading_time' => 5,
            'is_breaking' => false,
        ]);

    $article->tags()->attach($tag);

    $response = $this->get(route('articles.show', ['slug' => $article->slug]));

    $response->assertOk()
        ->assertViewIs('public.article')
        ->assertSeeText($article->title)
        ->assertSeeText('Оригинал источника')
        ->assertDontSeeText('Метаданные статьи')
        ->assertDontSeeText('RSS парсинг')
        ->assertDontSeeText('RSS-лента');
});
