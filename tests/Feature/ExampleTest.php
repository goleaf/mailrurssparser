<?php

use App\Models\Article;
use App\Models\Category;

test('home page renders the blade news portal', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertViewIs('public.home')
        ->assertSeeText('Живая повестка')
        ->assertSeeText('Простая новостная витрина с лентой, срочными материалами и статистикой редакции.');
});

test('public category page renders server-side article content', function () {
    $category = Category::factory()->create([
        'name' => 'Мир',
        'slug' => 'world',
    ]);

    $article = Article::factory()
        ->published()
        ->forCategory($category)
        ->create([
            'title' => 'Главная мировая новость',
            'slug' => 'global-headline',
            'short_description' => 'Краткое описание мировой новости.',
        ]);

    $response = $this->get(route('category.show', ['slug' => $category->slug]));

    $response->assertOk()
        ->assertViewIs('public.category')
        ->assertSeeText($category->name)
        ->assertSeeText($article->title);
});

test('unknown public path returns the blade not found page', function () {
    $response = $this->get('/missing-public-page');

    $response->assertNotFound()
        ->assertViewIs('public.not-found')
        ->assertSeeText('Страница не найдена');
});
