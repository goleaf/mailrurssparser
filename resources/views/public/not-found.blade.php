@extends('layouts.app')

@section('body')
    <div class="mx-auto flex min-h-screen max-w-5xl items-center px-4 py-16 sm:px-6 lg:px-10">
        <div class="portal-hero w-full text-center">
            <x-mary-badge class="badge-error badge-soft border-0">404</x-mary-badge>
            <h1 class="mt-5 text-4xl font-black sm:text-5xl">Страница не найдена</h1>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-8 text-base-content/75">
                Похоже, ссылка устарела или страница была перемещена. Используйте главную страницу или серверный поиск.
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <x-mary-button class="btn-primary" icon="o-home" label="На главную" link="{{ route('home') }}" no-wire-navigate />
                <x-mary-button class="btn-ghost" icon="o-magnifying-glass" label="Открыть поиск" link="{{ route('search') }}" no-wire-navigate />
            </div>
        </div>
    </div>
@endsection
