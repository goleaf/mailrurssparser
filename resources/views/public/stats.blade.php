@extends('layouts.app')

@section('body')
    <div class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-10">
        <div class="portal-hero">
            <x-mary-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'link' => route('home')],
                    ['label' => 'Статистика'],
                ]"
                :no-wire-navigate="true"
            />

            <div class="mt-5 space-y-4">
                <x-mary-header
                    subtitle="Публикации, просмотры и состояние RSS-источников в серверной сводке."
                    title="Панель метрик публичного портала"
                />
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-mary-stat color="text-sky-600 dark:text-sky-300" description="Вся опубликованная база" icon="o-newspaper" title="Материалов" value="{{ number_format($overview['articles']['total'] ?? 0, 0, ',', ' ') }}" />
                <x-mary-stat color="text-red-500" description="Просмотры за сегодня" icon="o-eye" title="Сегодня" value="{{ number_format($overview['views']['today'] ?? 0, 0, ',', ' ') }}" />
                <x-mary-stat color="text-emerald-600 dark:text-emerald-300" description="Уникальные читатели за день" icon="o-user-group" title="Уникальные" value="{{ number_format($overview['views']['unique_today'] ?? 0, 0, ',', ' ') }}" />
                <x-mary-stat color="text-slate-700 dark:text-slate-200" description="Последний успешный парсинг" icon="o-signal" title="RSS" value="{{ $overview['last_parse'] ? \Illuminate\Support\Carbon::parse($overview['last_parse'])->translatedFormat('d M, H:i') : 'Нет данных' }}" />
            </div>
        </div>

        <div class="mt-8 grid gap-8 xl:grid-cols-[1fr_360px]">
            <div class="space-y-8">
                <section class="space-y-4">
                    <x-mary-header subtitle="Материалы с максимальным числом просмотров." title="Популярные публикации" />
                    <div class="grid gap-5 lg:grid-cols-2">
                        @foreach($popularArticles as $article)
                            <x-public.article-card :article="$article" :bookmarked-ids="$bookmarkedArticleIds" />
                        @endforeach
                    </div>
                </section>

                <section class="space-y-4">
                    <x-mary-header subtitle="Сколько материалов даёт каждая активная рубрика." title="Распределение по рубрикам" />
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach($topCategories as $category)
                            <x-mary-card class="portal-surface rounded-[1.5rem]" shadow>
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <a class="text-lg font-black hover:text-sky-600 dark:hover:text-sky-300" href="{{ route('category.show', ['slug' => $category->slug]) }}">
                                            {{ $category->name }}
                                        </a>
                                        <p class="mt-2 text-sm text-base-content/60">Активная рубрика новостной ленты.</p>
                                    </div>

                                    <x-mary-stat
                                        class="max-w-[9rem]"
                                        color="text-sky-600 dark:text-sky-300"
                                        icon="o-rectangle-stack"
                                        title="Материалов"
                                        value="{{ number_format((int) $category->published_count, 0, ',', ' ') }}"
                                    />
                                </div>
                            </x-mary-card>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                    <x-slot:title>Теги недели</x-slot:title>
                    <x-slot:subtitle>Темы с самой высокой активностью</x-slot:subtitle>

                    <div class="flex flex-wrap gap-2">
                        @foreach($navigationTags as $tag)
                            <a class="badge badge-ghost rounded-full px-4 py-4" href="{{ route('tag.show', ['slug' => $tag->slug]) }}">
                                #{{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                </x-mary-card>

                <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                    <x-slot:title>RSS-источники</x-slot:title>
                    <x-slot:subtitle>Краткая сводка по подключённым лентам</x-slot:subtitle>

                    <div class="space-y-4">
                        @foreach($feedPerformance as $feed)
                            <div class="rounded-2xl border border-base-300/70 p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <div class="text-base font-bold">{{ $feed->title }}</div>
                                        <div class="mt-1 text-sm text-base-content/60">{{ $feed->category?->name ?: 'Без рубрики' }}</div>
                                    </div>
                                    <x-mary-badge class="badge-ghost border-0">
                                        {{ number_format((int) $feed->articles_count, 0, ',', ' ') }}
                                    </x-mary-badge>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-4 text-sm text-base-content/60">
                                    <span>Сегодня: {{ number_format((int) $feed->today_articles_count, 0, ',', ' ') }}</span>
                                    <span>Последний запуск: {{ $feed->last_parsed_at?->translatedFormat('d M, H:i') ?: 'Нет данных' }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-mary-card>
            </aside>
        </div>
    </div>
@endsection
