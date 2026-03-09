<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\SubCategory;

test('home page renders the blade news portal', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertViewIs('public.home')
        ->assertSeeText('Живая повестка')
        ->assertSeeText('Простая новостная витрина с лентой, срочными материалами и статистикой редакции.')
        ->assertSeeText('Меню разделов')
        ->assertSeeText('Быстрые переходы по основным страницам портала.')
        ->assertSee('data-primary-menu', false);
});

test('public category page renders server-side article content', function () {
    $category = Category::factory()->create([
        'name' => 'Мир',
        'slug' => 'world',
    ]);

    SubCategory::factory()->count(2)->forCategory($category)->create();

    $article = Article::factory()
        ->published()
        ->forCategory($category)
        ->create([
            'title' => 'Главная мировая новость',
            'slug' => 'global-headline',
            'short_description' => 'Краткое описание мировой новости.',
        ]);

    $pinnedArticle = Article::factory()
        ->published()
        ->forCategory($category)
        ->create([
            'title' => 'Закреплённый мировой материал',
            'slug' => 'pinned-global-headline',
            'short_description' => 'Краткое описание закреплённого материала.',
            'is_pinned' => true,
        ]);

    $response = $this->get(route('category.show', ['slug' => $category->slug]));

    $response->assertOk()
        ->assertViewIs('public.category')
        ->assertSeeText($category->name)
        ->assertSeeText($article->title)
        ->assertSeeText($pinnedArticle->title)
        ->assertSeeText('Материалов в рубрике')
        ->assertSeeText('Активных подрубрик')
        ->assertSeeText('Закреплено сейчас')
        ->assertSee('xl:grid-cols-2', false)
        ->assertSeeText('Открыть');
});

test('unknown public path returns the blade not found page', function () {
    $response = $this->get('/missing-public-page');

    $response->assertNotFound()
        ->assertViewIs('public.not-found')
        ->assertSeeText('Страница не найдена');
});
