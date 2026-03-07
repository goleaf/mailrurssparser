<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#1D4ED8">
        <meta name="apple-mobile-web-app-capable" content="yes">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.json">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

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
        @inertiaHead
    </head>
    <body class="bg-white font-sans antialiased text-slate-900 dark:bg-gray-900 dark:text-white">
        @inertia
    </body>
</html>
