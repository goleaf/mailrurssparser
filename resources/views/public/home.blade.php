@extends('layouts.app')

@section('body')
    <div class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-10">
        <div class="portal-hero">
            <div class="grid gap-8 lg:grid-cols-[1.2fr_0.8fr]">
                <div class="space-y-5">
                    <x-mary-breadcrumbs :items="[['label' => 'Главная']]" :no-wire-navigate="true" />

                    <div class="space-y-4">
                        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-sky-700 dark:text-sky-300">
                            Живая повестка
                        </p>
                        <h1 class="max-w-4xl text-balance text-4xl font-black leading-tight sm:text-5xl">
                            Простая новостная витрина с лентой, срочными материалами и статистикой редакции.
                        </h1>
                        <p class="max-w-2xl text-base leading-8 text-base-content/75">
                            Главная страница собирает главные материалы, рубрики, теги и закладки в одной понятной витрине с быстрыми переходами по редакционной повестке.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <x-mary-button class="btn-primary" icon="o-chart-bar" label="Открыть статистику" link="{{ route('stats') }}" no-wire-navigate />
                        <x-mary-button class="btn-ghost" icon="o-bookmark" :label="'Закладки'.($bookmarkCount > 0 ? ' · '.$bookmarkCount : '')" link="{{ route('bookmarks') }}" no-wire-navigate />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-mary-stat
                        color="text-sky-600 dark:text-sky-300"
                        description="Опубликовано"
                        icon="o-newspaper"
                        title="Материалов"
                        value="{{ number_format($statsOverview['articles']['total'] ?? 0, 0, ',', ' ') }}"
                    />
                    <x-mary-stat
                        color="text-red-500"
                        description="За последние 24 часа"
                        icon="o-bolt"
                        title="Срочных"
                        value="{{ number_format($statsOverview['articles']['breaking'] ?? 0, 0, ',', ' ') }}"
                    />
                    <x-mary-stat
                        color="text-emerald-600 dark:text-emerald-300"
                        description="Все просмотры"
                        icon="o-eye"
                        title="Внимание"
                        value="{{ number_format($statsOverview['views']['total'] ?? 0, 0, ',', ' ') }}"
                    />
                    <x-mary-stat
                        color="text-slate-700 dark:text-slate-200"
                        description="Активных RSS"
                        icon="o-signal"
                        title="Источники"
                        value="{{ number_format($statsOverview['feeds']['active'] ?? 0, 0, ',', ' ') }}"
                    />
                </div>
            </div>
        </div>

        <div class="mt-8 grid gap-8 xl:grid-cols-[1fr_340px]">
            <div class="space-y-8">
                @if($featuredArticles->isNotEmpty())
                    <section class="space-y-4">
                        <x-mary-header subtitle="Редакционная подборка и материалы с высоким приоритетом." title="Главный фокус" />
                        <div class="grid gap-6 md:grid-cols-2">
                            @foreach($featuredArticles as $article)
                                <x-public.article-card :article="$article" :bookmarked-ids="$bookmarkedArticleIds" />
                            @endforeach
                        </div>
                    </section>
                @endif

                @if($breakingArticles->isNotEmpty())
                    <section class="space-y-4">
                        <x-mary-header subtitle="Материалы, которым система и редакция дали срочный статус." title="Срочная повестка" />
                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach($breakingArticles as $article)
                                <x-public.article-card :article="$article" :bookmarked-ids="$bookmarkedArticleIds" :show-excerpt="false" />
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="space-y-4">
                    <x-mary-header subtitle="Полная лента публикаций с сортировкой и пагинацией на сервере." title="Последние материалы" />
                    <div class="grid gap-5 lg:grid-cols-2">
                        @foreach($latestArticles as $article)
                            <x-public.article-card :article="$article" :bookmarked-ids="$bookmarkedArticleIds" />
                        @endforeach
                    </div>

                    <div class="pt-2">
                        {{ $latestArticles->links() }}
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                    <x-slot:title>Рубрики</x-slot:title>
                    <x-slot:subtitle>Основные направления ленты</x-slot:subtitle>

                    <div class="flex flex-wrap gap-2">
                        @foreach($navigationCategories as $category)
                            <a class="badge badge-lg badge-outline rounded-full px-4 py-4" href="{{ route('category.show', ['slug' => $category->slug]) }}">
                                {{ $category->name }}
                            </a>
                        @endforeach
                    </div>
                </x-mary-card>

                <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                    <x-slot:title>Трендовые теги</x-slot:title>
                    <x-slot:subtitle>Быстрые переходы в самые активные темы</x-slot:subtitle>

                    <div class="flex flex-wrap gap-2">
                        @foreach($navigationTags as $tag)
                            <a class="badge badge-ghost rounded-full px-4 py-4" href="{{ route('tag.show', ['slug' => $tag->slug]) }}">
                                #{{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                </x-mary-card>

                @if($editorsChoice->isNotEmpty())
                    <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                        <x-slot:title>Выбор редакции</x-slot:title>
                        <x-slot:subtitle>Материалы, которые стоит прочитать в первую очередь</x-slot:subtitle>

                        <div class="space-y-4">
                            @foreach($editorsChoice as $article)
                                <div class="space-y-2 border-b border-base-300/60 pb-4 last:border-b-0 last:pb-0">
                                    <a class="block text-base font-bold leading-7 hover:text-sky-600 dark:hover:text-sky-300" href="{{ route('articles.show', ['slug' => $article->slug]) }}">
                                        {{ $article->title }}
                                    </a>
                                    <p class="text-sm text-base-content/60">
                                        {{ $article->published_at?->translatedFormat('d M Y, H:i') }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </x-mary-card>
                @endif
            </aside>
        </div>
    </div>
@endsection
