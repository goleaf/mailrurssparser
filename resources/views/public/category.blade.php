@extends('layouts.app')

@section('body')
    <div class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-10">
        <div class="portal-hero">
            <x-mary-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'link' => route('home')],
                    ['label' => $category->name],
                ]"
                :no-wire-navigate="true"
            />

            <div class="mt-5 grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(17rem,20rem)] xl:items-start">
                <div class="space-y-3">
                    <x-mary-badge class="badge-outline border" style="border-color: {{ $category->color ?: '#0284c7' }}; color: {{ $category->color ?: '#0284c7' }};">
                        Рубрика
                    </x-mary-badge>
                    <h1 class="text-3xl font-black leading-tight sm:text-4xl">{{ $category->name }}</h1>
                    <p class="max-w-3xl text-sm leading-7 text-base-content/75 sm:text-base">
                        {{ $category->description ?: 'Все опубликованные материалы этой рубрики с серверной пагинацией и актуальными подрубриками.' }}
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
                    <div class="portal-surface rounded-[1.5rem] px-4 py-4">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.2em] text-sky-700 dark:text-sky-300">
                            Материалов в рубрике
                        </p>
                        <p class="mt-2 text-2xl font-black leading-none">
                            {{ number_format($articles->total(), 0, ',', ' ') }}
                        </p>
                        <p class="mt-1 text-xs text-base-content/60">
                            Опубликовано в активной ленте
                        </p>
                    </div>

                    <div class="portal-surface rounded-[1.5rem] px-4 py-4">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">
                            Активных подрубрик
                        </p>
                        <p class="mt-2 text-2xl font-black leading-none">
                            {{ number_format($category->activeSubCategories->count(), 0, ',', ' ') }}
                        </p>
                        <p class="mt-1 text-xs text-base-content/60">
                            Быстрые переходы внутри темы
                        </p>
                    </div>

                    <div class="portal-surface rounded-[1.5rem] px-4 py-4">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.2em] text-amber-700 dark:text-amber-300">
                            Закреплено сейчас
                        </p>
                        <p class="mt-2 text-2xl font-black leading-none">
                            {{ number_format($pinnedArticles->count(), 0, ',', ' ') }}
                        </p>
                        <p class="mt-1 text-xs text-base-content/60">
                            Материалы, удерживаемые наверху рубрики
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 grid gap-8 xl:grid-cols-[1fr_320px]">
            <div class="space-y-5">
                <div class="grid gap-4 xl:grid-cols-2">
                    @foreach($articles as $article)
                        <x-public.article-card :article="$article" :bookmarked-ids="$bookmarkedArticleIds" :compact="true" />
                    @endforeach
                </div>

                <div class="pt-2">
                    {{ $articles->links() }}
                </div>
            </div>

            <aside class="space-y-4">
                @if($pinnedArticles->isNotEmpty())
                    <section class="portal-surface rounded-[1.5rem] p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-black">Закреплено</h2>
                                <p class="text-sm text-base-content/60">Сжатая подборка материалов в верхней части рубрики</p>
                            </div>
                            <span class="badge badge-outline rounded-full px-3 py-3">
                                {{ $pinnedArticles->count() }}
                            </span>
                        </div>

                        <div class="mt-4 space-y-3">
                            @foreach($pinnedArticles as $article)
                                <a class="block rounded-[1.25rem] border border-base-300/70 px-4 py-3 transition hover:border-sky-300 hover:bg-sky-50/60 dark:hover:bg-sky-950/20" href="{{ route('articles.show', ['slug' => $article->slug]) }}">
                                    <div class="text-xs uppercase tracking-[0.18em] text-base-content/55">
                                        {{ $article->published_at?->translatedFormat('d M Y, H:i') }}
                                    </div>
                                    <div class="mt-2 line-clamp-2 text-sm font-bold leading-6">{{ $article->title }}</div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if($category->activeSubCategories->isNotEmpty())
                    <section class="portal-surface rounded-[1.5rem] p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-black">Подрубрики</h2>
                                <p class="text-sm text-base-content/60">Переходы по вложенным направлениям без лишней высоты</p>
                            </div>
                            <span class="badge badge-ghost rounded-full px-3 py-3">
                                {{ $category->activeSubCategories->count() }}
                            </span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach($category->activeSubCategories as $subCategory)
                                <span class="badge badge-ghost rounded-full px-3 py-3 text-xs font-medium">
                                    {{ $subCategory->name }}
                                </span>
                            @endforeach
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </div>
@endsection
