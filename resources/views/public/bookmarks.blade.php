@extends('layouts.app')

@section('body')
    <div class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-10">
        <div class="portal-hero">
            <x-mary-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'link' => route('home')],
                    ['label' => 'Закладки'],
                ]"
                :no-wire-navigate="true"
            />

            <div class="mt-5 flex flex-wrap items-end justify-between gap-6">
                <div class="space-y-3">
                    <x-mary-badge class="badge-ghost border-0">Локально для текущего браузера</x-mary-badge>
                    <h1 class="text-4xl font-black">Сохранённые материалы</h1>
                    <p class="max-w-3xl text-base leading-8 text-base-content/75">
                        Закладки хранятся для текущего браузера и устройства, чтобы можно было быстро вернуться к важным публикациям.
                    </p>
                </div>

                <x-mary-stat
                    color="text-sky-600 dark:text-sky-300"
                    description="Текущая сессия"
                    icon="o-bookmark"
                    title="Сохранено"
                    value="{{ number_format($articles->count(), 0, ',', ' ') }}"
                />
            </div>
        </div>

        <div class="mt-8 space-y-5">
            @forelse($articles as $article)
                <x-public.article-card :article="$article" :bookmarked-ids="$bookmarkedArticleIds" />
            @empty
                <div class="portal-surface rounded-[1.75rem] p-8 text-center">
                    <h2 class="text-2xl font-black">Закладок пока нет</h2>
                    <p class="mt-3 text-base-content/70">Откройте любую статью или карточку материала и сохраните её кнопкой «В закладки».</p>
                    <div class="mt-6">
                        <x-mary-button class="btn-primary" label="Вернуться на главную" link="{{ route('home') }}" no-wire-navigate />
                    </div>
                </div>
            @endforelse
        </div>
    </div>
@endsection
