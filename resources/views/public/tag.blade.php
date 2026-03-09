@extends('layouts.app')

@section('body')
    <div class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-10">
        <div class="portal-hero">
            <x-mary-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'link' => route('home')],
                    ['label' => '#'.$tag->name],
                ]"
                :no-wire-navigate="true"
            />

            <div class="mt-5 flex flex-wrap items-end justify-between gap-6">
                <div class="space-y-3">
                    <x-mary-badge class="badge-ghost border-0">Тег</x-mary-badge>
                    <h1 class="text-4xl font-black">#{{ $tag->name }}</h1>
                    <p class="max-w-3xl text-base leading-8 text-base-content/75">
                        {{ $tag->description ?: 'Материалы, объединённые общей темой и собранные в удобную тематическую подборку.' }}
                    </p>
                </div>

                <x-mary-stat
                    color="text-emerald-600 dark:text-emerald-300"
                    description="Использование в материалах"
                    icon="o-hashtag"
                    title="Упоминаний"
                    value="{{ number_format((int) $tag->usage_count, 0, ',', ' ') }}"
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
                <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                    <x-slot:title>Другие теги</x-slot:title>
                    <x-slot:subtitle>Популярные темы для следующего перехода</x-slot:subtitle>

                    <div class="flex flex-wrap gap-2">
                        @foreach($relatedTags as $relatedTag)
                            <a class="badge badge-outline rounded-full px-4 py-4" href="{{ route('tag.show', ['slug' => $relatedTag->slug]) }}">
                                #{{ $relatedTag->name }}
                            </a>
                        @endforeach
                    </div>
                </x-mary-card>
            </aside>
        </div>
    </div>
@endsection
