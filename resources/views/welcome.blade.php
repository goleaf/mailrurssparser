@extends('layouts.app')

@section('body')
    <main class="mx-auto flex min-h-screen max-w-3xl items-center justify-center px-6 py-16">
        <div class="text-center">
            <h1 class="text-3xl font-black">{{ config('app.name', 'Новостной Портал') }}</h1>
            <p class="mt-3 text-base-content/70">Публичный портал теперь рендерится через Blade-маршруты.</p>
            <div class="mt-6">
                <a class="btn btn-primary" href="{{ route('home') }}">Перейти на главную</a>
            </div>
        </div>
    </main>
@endsection
