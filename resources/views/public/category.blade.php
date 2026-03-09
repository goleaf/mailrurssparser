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

            <div class="mt-5 flex flex-wrap items-end justify-between gap-6">
                <div class="space-y-3">
                    <x-mary-badge class="badge-outline border" style="border-color: {{ $category->color ?: '#0284c7' }}; color: {{ $category->color ?: '#0284c7' }};">
                        Рубрика
                    </x-mary-badge>
                    <h1 class="text-4xl font-black">{{ $category->name }}</h1>
                    <p class="max-w-3xl text-base leading-8 text-base-content/75">
                        {{ $category->description ?: 'Все опубликованные материалы этой рубрики с серверной пагинацией и актуальными подрубриками.' }}
                    </p>
                </div>

                <x-mary-stat
                    color="text-sky-600 dark:text-sky-300"
                    description="В активной ленте"
                    icon="o-rectangle-stack"
                    title="Материалов"
                    value="{{ number_format($articles->total(), 0, ',', ' ') }}"
                />
            </div>
        </div>

        <div class="mt-8 grid gap-8 xl:grid-cols-[1fr_320px]">
            <div class="space-y-5">
                @foreach($articles as $article)
                    <x-public.article-card :article="$article" :bookmarked-ids="$bookmarkedArticleIds" />
                @endforeach

                <div class="pt-2">
                    {{ $articles->links() }}
                </div>
            </div>

            <aside class="space-y-6">
                @if($pinnedArticles->isNotEmpty())
                    <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                        <x-slot:title>Закреплено</x-slot:title>
                        <x-slot:subtitle>Материалы, удерживаемые в верхней части рубрики</x-slot:subtitle>

                        <div class="space-y-4">
                            @foreach($pinnedArticles as $article)
                                <a class="block rounded-2xl border border-base-300/70 p-4 hover:border-sky-300 hover:bg-sky-50/60 dark:hover:bg-sky-950/20" href="{{ route('articles.show', ['slug' => $article->slug]) }}">
                                    <div class="text-sm text-base-content/60">{{ $article->published_at?->translatedFormat('d M Y, H:i') }}</div>
                                    <div class="mt-2 text-base font-bold leading-7">{{ $article->title }}</div>
                                </a>
                            @endforeach
                        </div>
                    </x-mary-card>
                @endif

                @if($category->activeSubCategories->isNotEmpty())
                    <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                        <x-slot:title>Подрубрики</x-slot:title>
                        <x-slot:subtitle>Навигация внутри направления</x-slot:subtitle>

                        <div class="flex flex-wrap gap-2">
                            @foreach($category->activeSubCategories as $subCategory)
                                <span class="badge badge-ghost rounded-full px-4 py-4">
                                    {{ $subCategory->name }}
                                </span>
                            @endforeach
                        </div>
                    </x-mary-card>
                @endif
            </aside>
        </div>
    </div>
@endsection
