@extends('layouts.app')

@section('body')
    <div class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-10">
        <div class="portal-hero">
            <x-mary-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'link' => route('home')],
                    ['label' => 'Поиск'],
                ]"
                :no-wire-navigate="true"
            />

            <div class="mt-5 grid gap-8 lg:grid-cols-[1fr_320px]">
                <div class="space-y-4">
                    <h1 class="text-4xl font-black">Поиск по порталу</h1>
                    <p class="max-w-3xl text-base leading-8 text-base-content/75">
                        Поиск по заголовкам, описаниям, авторам и источникам с быстрой выдачей по актуальным публикациям.
                    </p>

                    <form action="{{ route('search') }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]" method="GET">
                        <label class="form-control w-full">
                            <span class="sr-only">Поисковый запрос</span>
                            <input
                                class="input input-lg w-full rounded-2xl border-base-300/70"
                                name="q"
                                placeholder="Например: экономика, импорт, интервью"
                                type="search"
                                value="{{ $query }}"
                            >
                        </label>

                        <x-mary-button class="btn-primary btn-lg" icon="o-magnifying-glass" label="Найти" type="submit" />
                    </form>
                </div>

                <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                    <x-slot:title>Популярные темы</x-slot:title>
                    <x-slot:subtitle>Часто выбираемые теги</x-slot:subtitle>

                    <div class="flex flex-wrap gap-2">
                        @foreach($navigationTags as $tag)
                            <a class="badge badge-ghost rounded-full px-4 py-4" href="{{ route('tag.show', ['slug' => $tag->slug]) }}">
                                #{{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                </x-mary-card>
            </div>
        </div>

        <div class="mt-8 space-y-6">
            @if($query === '')
                <div class="portal-surface rounded-[1.75rem] p-8 text-center">
                    <h2 class="text-2xl font-black">Введите запрос</h2>
                    <p class="mt-3 text-base-content/70">Поиск начинается с двух символов и помогает быстро найти нужные материалы по всей ленте.</p>
                </div>
            @elseif($results && $results->count() > 0)
                <x-mary-header subtitle="Материалы, которые соответствуют введённому запросу." title="Найдено: {{ $results->total() }}" />

                <div class="grid gap-5 lg:grid-cols-2">
                    @foreach($results as $article)
                        <x-public.article-card :article="$article" :bookmarked-ids="$bookmarkedArticleIds" />
                    @endforeach
                </div>

                <div class="pt-2">
                    {{ $results->links() }}
                </div>
            @else
                <div class="portal-surface rounded-[1.75rem] p-8">
                    <h2 class="text-2xl font-black">Ничего не найдено</h2>
                    <p class="mt-3 text-base-content/70">
                        Попробуйте уточнить запрос или перейти в похожие рубрики и теги.
                    </p>

                    <div class="mt-6 grid gap-6 lg:grid-cols-2">
                        <x-mary-card class="rounded-[1.5rem] border border-base-300/70">
                            <x-slot:title>Похожие рубрики</x-slot:title>
                            <div class="flex flex-wrap gap-2">
                                @forelse($suggestedCategories as $category)
                                    <a class="badge badge-outline rounded-full px-4 py-4" href="{{ route('category.show', ['slug' => $category->slug]) }}">
                                        {{ $category->name }}
                                    </a>
                                @empty
                                    <span class="text-sm text-base-content/60">Подходящих рубрик не найдено.</span>
                                @endforelse
                            </div>
                        </x-mary-card>

                        <x-mary-card class="rounded-[1.5rem] border border-base-300/70">
                            <x-slot:title>Похожие теги</x-slot:title>
                            <div class="flex flex-wrap gap-2">
                                @forelse($suggestedTags as $tag)
                                    <a class="badge badge-ghost rounded-full px-4 py-4" href="{{ route('tag.show', ['slug' => $tag->slug]) }}">
                                        #{{ $tag->name }}
                                    </a>
                                @empty
                                    <span class="text-sm text-base-content/60">Подходящих тегов не найдено.</span>
                                @endforelse
                            </div>
                        </x-mary-card>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
