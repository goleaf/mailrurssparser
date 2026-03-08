<!DOCTYPE html>
<html lang="ru" class="h-full scroll-smooth">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="app-name" content="{{ config('app.name') }}">
        <meta name="app-url" content="{{ config('app.url') }}">

        <title inertia>{{ config('app.name', 'Новостной Портал') }} — Последние новости</title>
        <meta
            name="description"
            content="Актуальные новости политики, экономики, общества и спорта"
        >
        <meta name="robots" content="index, follow">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:locale" content="ru_RU">

        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#1D4ED8">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://news.mail.ru">

        <link rel="icon" type="image/png" href="/favicon.png">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">

        <script>
            (() => {
                const storedDarkMode = localStorage.getItem('darkMode');
                const storedAppearance = localStorage.getItem('appearance');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = storedDarkMode === 'true'
                    || (storedDarkMode === null
                        && (storedAppearance === 'dark'
                            || (storedAppearance !== 'light' && storedAppearance !== 'dark' && prefersDark)));

                document.documentElement.classList.toggle('dark', isDark);
                document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
            })();
        </script>

        @vite(['resources/js/app.ts'])

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
                "publishingPrinciples": "{{ config('app.url') }}/#/about"
            }
        </script>
    </head>
    <body class="h-full bg-gray-50 text-gray-900 antialiased transition-colors duration-200 dark:bg-gray-900 dark:text-white">
        @hasSection('body')
            @yield('body')
        @else
            <div id="app"></div>
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
