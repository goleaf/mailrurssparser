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
        <meta name="robots" content="{{ $robots ?? config('seo.robots.default', 'index, follow') }}">
        <link rel="canonical" href="{{ $canonicalUrl ?? request()->fullUrl() }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:locale" content="ru_RU">
        <meta property="og:title" content="{{ $metaTitle ?? config('app.name', 'Новостной Портал') }}">
        <meta property="og:description" content="{{ $metaDescription ?? 'Актуальные новости политики, экономики, общества и спорта' }}">
        <meta property="og:url" content="{{ $canonicalUrl ?? request()->fullUrl() }}">
        @if(filled($metaImage ?? null))
            <meta property="og:image" content="{{ $metaImage }}">
        @endif

        <meta name="twitter:card" content="{{ filled($metaImage ?? null) ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $metaTitle ?? config('app.name', 'Новостной Портал') }}">
        <meta name="twitter:description" content="{{ $metaDescription ?? 'Актуальные новости политики, экономики, общества и спорта' }}">
        @if(filled($metaImage ?? null))
            <meta name="twitter:image" content="{{ $metaImage }}">
        @endif

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

        @if(isset($structuredData) && filled($structuredData))
            @php
                $structuredDataPayload = is_string($structuredData)
                    ? json_decode($structuredData, true)
                    : $structuredData;
            @endphp

            @if(is_array($structuredDataPayload))
                <script type="application/ld+json">
                    @json($structuredDataPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                </script>
            @endif
        @endif
    </head>
    <body class="portal-shell h-full text-base-content antialiased">
        @hasSection('body')
            @php
                $primaryNav = [
                    ['key' => 'home', 'label' => 'Главная', 'route' => route('home')],
                    ['key' => 'search', 'label' => 'Поиск', 'route' => route('search')],
                    ['key' => 'stats', 'label' => 'Статистика', 'route' => route('stats')],
                    ['key' => 'about', 'label' => 'О проекте', 'route' => route('about')],
                    ['key' => 'contact', 'label' => 'Контакты', 'route' => route('contact')],
                    ['key' => 'privacy', 'label' => 'Приватность', 'route' => route('privacy')],
                ];
                $currentNav = $activeNav ?? '';
                $bookmarkTotal = (int) ($bookmarkCount ?? 0);
                $headerCategories = $navigationCategories ?? collect();
                $headerTags = $navigationTags ?? collect();
            @endphp

            <div class="min-h-screen">
                <header class="sticky top-0 z-40 border-b border-base-300/70 bg-base-100/72 backdrop-blur-2xl">
                    <div class="mx-auto max-w-screen-2xl px-4 py-3 sm:px-6 lg:px-10">
                        <div class="portal-header-shell px-4 py-4 sm:px-5 lg:px-6">
                            <div class="grid gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(24rem,0.9fr)] xl:items-center">
                                <div class="space-y-4">
                                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.28em] text-base-content/55">
                                        <span class="inline-flex items-center gap-2 rounded-full bg-red-500/10 px-3 py-1 text-red-600 dark:text-red-300">
                                            <span class="h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                                            Лента Mail.ru
                                        </span>
                                        <span class="rounded-full bg-sky-500/10 px-3 py-1 text-sky-700 dark:text-sky-300">
                                            {{ $headerCategories->count() }} рубрик
                                        </span>
                                        <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-emerald-700 dark:text-emerald-300">
                                            {{ $headerTags->count() }} тегов
                                        </span>
                                    </div>

                                    <div class="flex items-start gap-4">
                                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[1.4rem] bg-[linear-gradient(135deg,_rgba(2,132,199,0.95),_rgba(16,185,129,0.82))] text-lg font-black tracking-[0.22em] text-white shadow-lg shadow-sky-500/20">
                                            MR
                                        </div>

                                        <div class="space-y-2">
                                            <a class="block text-balance text-2xl font-black tracking-tight sm:text-3xl" href="{{ route('home') }}">
                                                {{ config('app.name', 'Новостной Портал') }}
                                            </a>
                                            <p class="max-w-2xl text-sm leading-6 text-base-content/65">
                                                Быстрые переходы по ленте, рубрикам, поиску и редакционным страницам в одном плотном меню.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                    <form action="{{ route('search') }}" class="order-2 sm:order-1" data-header-search>
                                        <label class="input input-md w-full rounded-[1.35rem] border-base-300/70 bg-base-100/85 shadow-sm">
                                            <svg class="h-4 w-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
                                            </svg>
                                            <input name="q" placeholder="Поиск по новостям, темам и авторам" type="search">
                                        </label>
                                    </form>

                                    <div class="order-1 flex items-center justify-end gap-2 sm:order-2">
                                        <x-mary-button
                                            class="btn-neutral btn-sm rounded-full border-0 bg-base-content text-base-100 shadow-sm hover:bg-base-content/90"
                                            icon="o-bookmark"
                                            :label="'Закладки'.($bookmarkTotal > 0 ? ' · '.$bookmarkTotal : '')"
                                            link="{{ route('bookmarks') }}"
                                            no-wire-navigate
                                        />

                                        <button
                                            aria-label="Переключить тему"
                                            class="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-base-300/70 bg-base-100/80 px-4 text-sm font-semibold text-base-content/80 shadow-sm transition hover:border-sky-400/50 hover:bg-sky-500/8 hover:text-base-content"
                                            data-theme-toggle
                                            type="button"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                                            </svg>
                                            <span class="hidden sm:inline">Тема</span>
                                        </button>
                                    </div>

                                    <div class="order-3 rounded-[1.65rem] border border-base-300/70 bg-[linear-gradient(135deg,_rgba(2,132,199,0.08),_rgba(255,255,255,0.68),_rgba(16,185,129,0.08))] px-4 py-4 sm:col-span-2 dark:bg-[linear-gradient(135deg,_rgba(2,132,199,0.14),_rgba(15,23,42,0.74),_rgba(16,185,129,0.1))]">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="space-y-1">
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-base-content/50">
                                                    Меню разделов
                                                </p>
                                                <p class="text-sm text-base-content/65">
                                                    Быстрые переходы по основным страницам портала.
                                                </p>
                                            </div>

                                            <span class="rounded-full border border-base-300/70 px-3 py-1 text-xs font-medium text-base-content/60">
                                                {{ count($primaryNav) }} направлений
                                            </span>
                                        </div>

                                        <nav class="-mx-1 mt-3 overflow-x-auto pb-1" data-primary-menu>
                                            <div class="flex min-w-max items-center gap-2 px-1">
                                                @foreach($primaryNav as $item)
                                                    <a
                                                        @class([
                                                            'portal-menu-chip',
                                                            'portal-menu-chip-active' => $currentNav === $item['key'],
                                                        ])
                                                        href="{{ $item['route'] }}"
                                                    >
                                                        <span class="text-[10px] uppercase tracking-[0.28em] opacity-70">
                                                            {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                                        </span>
                                                        <span>{{ $item['label'] }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </nav>
                                    </div>
                                </div>
                            </div>

                            @if($headerCategories->isNotEmpty())
                                <div class="mt-4 border-t border-base-300/60 pt-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-base-content/50">
                                                Рубрики и темы
                                            </p>
                                            <p class="text-sm text-base-content/65">
                                                Горизонтальное меню ключевых направлений редакции без лишней высоты.
                                            </p>
                                        </div>

                                        <a class="text-sm font-semibold text-sky-700 transition hover:text-sky-600 dark:text-sky-300 dark:hover:text-sky-200" href="{{ route('search') }}">
                                            Найти по теме
                                        </a>
                                    </div>

                                    <div class="-mx-1 mt-3 overflow-x-auto pb-1" data-category-menu>
                                        <div class="flex min-w-max items-center gap-2 px-1 snap-x snap-proximity">
                                            @foreach($headerCategories as $category)
                                                <a class="portal-category-chip snap-start" href="{{ route('category.show', ['slug' => $category->slug]) }}">
                                                    <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                                                    <span>{{ $category->name }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
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
