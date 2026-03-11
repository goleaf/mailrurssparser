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

    $firstSubCategory = SubCategory::factory()->forCategory($category)->create([
        'name' => 'Глобальное',
        'slug' => 'globalnoe',
    ]);
    $secondSubCategory = SubCategory::factory()->forCategory($category)->create([
        'name' => 'Локальное',
        'slug' => 'lokalnoe',
    ]);

    $article = Article::factory()
        ->published()
        ->forCategory($category)
        ->create([
            'title' => 'Главная мировая новость',
            'slug' => 'global-headline',
            'image_url' => 'https://example.com/images/global-headline.jpg',
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
        ->assertSeeText($article->short_description)
        ->assertSeeText($pinnedArticle->title)
        ->assertSeeText('Материалов в рубрике')
        ->assertSeeText('Активных подрубрик')
        ->assertSeeText('Закреплено сейчас')
        ->assertSee('xl:grid-cols-2', false)
        ->assertSee($article->image_url, false)
        ->assertSee('sm:flex-row', false)
        ->assertSee('object-cover', false)
        ->assertSeeText('Дата')
        ->assertSeeText('Чтение')
        ->assertSeeText('Просмотры')
        ->assertSee('whitespace-nowrap', false)
        ->assertSeeText('Открыть')
        ->assertSee(route('category.show', ['slug' => $category->slug, 'sub' => $firstSubCategory->slug]), false)
        ->assertSee(route('category.show', ['slug' => $category->slug, 'sub' => $secondSubCategory->slug]), false);
});

test('public category page filters articles by sub category query string', function () {
    $category = Category::factory()->create([
        'name' => 'Общество',
        'slug' => 'society',
    ]);

    $localSubCategory = SubCategory::factory()->forCategory($category)->create([
        'name' => 'Жизнь',
        'slug' => 'zhizn',
    ]);
    $citySubCategory = SubCategory::factory()->forCategory($category)->create([
        'name' => 'Город',
        'slug' => 'gorod',
    ]);

    Article::factory()
        ->published()
        ->forSubCategory($localSubCategory)
        ->create([
            'title' => 'Материал про жизнь',
            'slug' => 'material-pro-zhizn',
        ]);

    Article::factory()
        ->published()
        ->forSubCategory($citySubCategory)
        ->create([
            'title' => 'Материал про город',
            'slug' => 'material-pro-gorod',
        ]);

    $response = $this->get(route('category.show', ['slug' => $category->slug, 'sub' => $localSubCategory->slug]));

    $response->assertOk()
        ->assertSeeText('Материал про жизнь')
        ->assertDontSeeText('Материал про город');
});

test('public pagination summary is localized to russian', function () {
    $category = Category::factory()->create([
        'name' => 'Экономика',
        'slug' => 'economics',
    ]);

    Article::factory()
        ->count(13)
        ->published()
        ->forCategory($category)
        ->create();

    $response = $this->get(route('category.show', ['slug' => $category->slug]));

    $response->assertOk()
        ->assertSeeTextInOrder([
            'Показаны с',
            '1',
            'по',
            '12',
            'из',
            '13',
            'результатов',
        ])
        ->assertSeeText('Вперёд');
});

test('unknown public path returns the blade not found page', function () {
    $response = $this->get('/missing-public-page');

    $response->assertNotFound()
        ->assertViewIs('public.not-found')
        ->assertSeeText('Страница не найдена');
});
