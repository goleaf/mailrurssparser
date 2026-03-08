<?php

return [
    'types' => [
        'build-js' => [
            'roots' => [
                ['path' => 'build/assets', 'recursive' => true],
            ],
            'extensions' => ['js'],
            'reference_source' => 'vite-manifest',
            'reason' => 'Not present in public/build/manifest.json.',
        ],
        'build-css' => [
            'roots' => [
                ['path' => 'build/assets', 'recursive' => true],
            ],
            'extensions' => ['css'],
            'reference_source' => 'vite-manifest',
            'reason' => 'Not present in public/build/manifest.json.',
        ],
        'build-map' => [
            'roots' => [
                ['path' => 'build/assets', 'recursive' => true],
            ],
            'extensions' => ['map'],
            'reference_source' => 'vite-manifest',
            'reason' => 'Not present in public/build/manifest.json.',
        ],
        'build-media' => [
            'roots' => [
                ['path' => 'build/assets', 'recursive' => true],
            ],
            'extensions' => ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'woff', 'woff2', 'ttf', 'eot'],
            'reference_source' => 'vite-manifest',
            'reason' => 'Not present in public/build/manifest.json.',
        ],
        'public-static' => [
            'roots' => [
                ['path' => '', 'recursive' => false],
                ['path' => 'icons', 'recursive' => true],
            ],
            'extensions' => ['css', 'gif', 'ico', 'jpeg', 'jpg', 'js', 'json', 'map', 'png', 'svg', 'txt', 'webp', 'xml'],
            'reference_source' => 'project-files',
            'reason' => 'No matching project reference was found.',
        ],
    ],
    'protected_files' => [
        '.htaccess',
        'apple-touch-icon.png',
        'build/manifest.json',
        'favicon.ico',
        'favicon.png',
        'favicon.svg',
        'icons/icon-192.png',
        'icons/icon-512.png',
        'index.php',
        'manifest.json',
        'robots.txt',
        'sw.js',
    ],
    'reference_paths' => [
        app_path(),
        base_path('bootstrap'),
        base_path('config'),
        resource_path(),
        base_path('routes'),
        public_path('manifest.json'),
        public_path('sw.js'),
    ],
    'reference_extensions' => ['css', 'js', 'json', 'md', 'php', 'svelte', 'ts', 'txt'],
    'backup_path' => storage_path('app/private/asset-cleaner'),
    'table_limit' => 50,
];
