<!DOCTYPE html>
<html lang="ru" class="h-full scroll-smooth" data-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="app-name" content="{{ config('app.name') }}">
        <meta name="app-url" content="{{ config('app.url') }}">

        <title>{{ $metaTitle ?? config('app.name', 'Новостной Портал') }}</title>
        <meta name="description" content="{{ $metaDescription ?? 'Актуальные новости политики, экономики, общества и спорта' }}">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ $canonicalUrl ?? request()->fullUrl() }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:locale" content="ru_RU">
        <meta property="og:title" content="{{ $metaTitle ?? config('app.name', 'Новостной Портал') }}">
        <meta property="og:description" content="{{ $metaDescription ?? 'Актуальные новости политики, экономики, общества и спорта' }}">
        <meta property="og:url" content="{{ $canonicalUrl ?? request()->fullUrl() }}">

        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#1D4ED8">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="{{ config('rss.feed_origin') }}">

        <link rel="icon" type="image/png" href="/favicon.png">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">

        <script>
            (() => {
                const storedTheme = localStorage.getItem('portal-theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const theme = storedTheme === 'dark' || storedTheme === 'light'
                    ? storedTheme
                    : (prefersDark ? 'dark' : 'light');
                const isDark = theme === 'dark';

                document.documentElement.classList.toggle('dark', isDark);
                document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
                document.documentElement.dataset.theme = theme;
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @hasSection('head')
            @yield('head')
        @endif

        @hasstack('head')
            @stack('head')
        @endif

        <script type="application/ld+json">
            {
                "@@context": "https://schema.org",
                "@@type": "NewsMediaOrganization",
                "name": "{{ config('app.name') }}",
                "url": "{{ config('app.url') }}",
                "sameAs": [],
                "publishingPrinciples": "{{ route('about') }}"
            }
        </script>

        @if(isset($structuredData) && $structuredData)
            <script type="application/ld+json">
                @json($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            </script>
        @endif
    </head>
    <body class="portal-shell h-full text-base-content antialiased">
        @hasSection('body')
            <div class="min-h-screen">
                <header class="sticky top-0 z-40 border-b border-base-300/70 bg-base-100/85 backdrop-blur-xl">
                    <div class="mx-auto flex max-w-screen-2xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-10">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <a class="text-2xl font-black tracking-tight" href="{{ route('home') }}">
                                    {{ config('app.name', 'Новостной Портал') }}
                                </a>
                                <p class="text-sm text-base-content/60">
                                    Blade + Mary UI для публичной витрины новостей
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <form action="{{ route('search') }}" class="hidden lg:block">
                                    <label class="input input-sm rounded-full border-base-300/70">
                                        <svg class="h-4 w-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
                                        </svg>
                                        <input name="q" placeholder="Поиск по новостям" type="search">
                                    </label>
                                </form>

                                <x-mary-button
                                    class="btn-ghost btn-sm rounded-full"
                                    icon="o-bookmark"
                                    :label="'Закладки'.((int) ($bookmarkCount ?? 0) > 0 ? ' · '.(int) $bookmarkCount : '')"
                                    link="{{ route('bookmarks') }}"
                                    no-wire-navigate
                                />
                                <x-mary-button
                                    class="btn-ghost btn-sm rounded-full"
                                    icon="o-moon"
                                    label="Тема"
                                    no-wire-navigate
                                    type="button"
                                    data-theme-toggle
                                />
                            </div>
                        </div>

                        <nav class="flex flex-wrap items-center gap-2">
                            @php
                                $primaryNav = [
                                    ['key' => 'home', 'label' => 'Главная', 'route' => route('home')],
                                    ['key' => 'search', 'label' => 'Поиск', 'route' => route('search')],
                                    ['key' => 'stats', 'label' => 'Статистика', 'route' => route('stats')],
                                    ['key' => 'about', 'label' => 'О проекте', 'route' => route('about')],
                                    ['key' => 'contact', 'label' => 'Контакты', 'route' => route('contact')],
                                    ['key' => 'privacy', 'label' => 'Приватность', 'route' => route('privacy')],
                                ];
                            @endphp

                            @foreach($primaryNav as $item)
                                <a
                                    class="{{ ($activeNav ?? '') === $item['key'] ? 'btn btn-primary btn-sm rounded-full' : 'btn btn-ghost btn-sm rounded-full' }}"
                                    href="{{ $item['route'] }}"
                                >
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </nav>

                        @if(($navigationCategories ?? collect())->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach($navigationCategories as $category)
                                    <a class="badge badge-outline rounded-full px-4 py-4" href="{{ route('category.show', ['slug' => $category->slug]) }}">
                                        {{ $category->name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </header>

                @if(session('status'))
                    <div class="mx-auto max-w-screen-2xl px-4 pt-6 sm:px-6 lg:px-10">
                        <div class="alert alert-success rounded-3xl">
                            <span>{{ session('status') }}</span>
                        </div>
                    </div>
                @endif

                @yield('body')

                <footer class="mt-16 border-t border-base-300/70 bg-base-100/60">
                    <div class="mx-auto grid max-w-screen-2xl gap-6 px-4 py-10 text-sm text-base-content/65 sm:px-6 lg:grid-cols-[1.2fr_0.8fr] lg:px-10">
                        <div class="space-y-3">
                            <p class="text-base font-bold text-base-content">{{ config('app.name', 'Новостной Портал') }}</p>
                            <p class="max-w-2xl leading-7">
                                Публичный портал объединяет главную ленту, рубрики, поиск, статьи, статистику и закладки в одном аккуратном интерфейсе на Blade и Mary UI.
                            </p>
                        </div>

                        <div class="space-y-3">
                            <p class="font-bold text-base-content">Темы и разделы</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach(($navigationTags ?? collect())->take(8) as $tag)
                                    <a class="badge badge-ghost rounded-full px-4 py-4" href="{{ route('tag.show', ['slug' => $tag->slug]) }}">
                                        #{{ $tag->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        @else
            <div class="mx-auto flex min-h-screen max-w-screen-2xl items-center justify-center px-6 py-16">
                <div class="text-center">
                    <h1 class="text-3xl font-black">Публичная витрина</h1>
                    <p class="mt-3 text-base-content/70">Контент не был передан в layout.</p>
                </div>
            </div>
        @endif

        <noscript>
            <div style="text-align: center; padding: 40px">
                <h1>Для работы сайта необходим JavaScript</h1>
                <p>Включите JavaScript в настройках браузера.</p>
            </div>
        </noscript>

        @hasstack('scripts')
            @stack('scripts')
        @endif
    </body>
</html>
