<?php

return [
    'feeds' => [
        [
            'url' => 'https://news.mail.ru/rss/',
            'title' => 'Все новости',
            'category_slug' => 'all',
            'category_name' => 'Все новости',
            'category_color' => '#6B7280',
            'category_icon' => '📰',
        ],
        [
            'url' => 'https://news.mail.ru/rss/main/',
            'title' => 'Главные новости',
            'category_slug' => 'main',
            'category_name' => 'Главные новости',
            'category_color' => '#1D4ED8',
            'category_icon' => '⭐',
        ],
        [
            'url' => 'https://news.mail.ru/rss/politics/',
            'title' => 'Политика',
            'category_slug' => 'politics',
            'category_name' => 'Политика',
            'category_color' => '#DC2626',
            'category_icon' => '🏛️',
        ],
        [
            'url' => 'https://news.mail.ru/rss/economics/',
            'title' => 'Экономика',
            'category_slug' => 'economics',
            'category_name' => 'Экономика',
            'category_color' => '#16A34A',
            'category_icon' => '💹',
        ],
        [
            'url' => 'https://news.mail.ru/rss/society/',
            'title' => 'Общество',
            'category_slug' => 'society',
            'category_name' => 'Общество',
            'category_color' => '#9333EA',
            'category_icon' => '👥',
        ],
        [
            'url' => 'https://news.mail.ru/rss/incident/',
            'title' => 'Происшествия',
            'category_slug' => 'incident',
            'category_name' => 'Происшествия',
            'category_color' => '#EA580C',
            'category_icon' => '🚨',
        ],
        [
            'url' => 'https://news.mail.ru/rss/svo/',
            'title' => 'СВО',
            'category_slug' => 'svo',
            'category_name' => 'СВО',
            'category_color' => '#B45309',
            'category_icon' => '🎖️',
        ],
        [
            'url' => 'https://news.mail.ru/rss/sport/',
            'title' => 'Спорт',
            'category_slug' => 'sport',
            'category_name' => 'Спорт',
            'category_color' => '#0891B2',
            'category_icon' => '⚽',
        ],
    ],

    'parser' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (compatible; NewsPortalBot/1.0; +http://localhost)',
        'max_items_per_feed' => 50,
        'duplicate_window_hours' => 72,
    ],

    'image' => [
        'extract_from_enclosure' => true,
        'extract_from_media_namespace' => true,
        'extract_from_description_img' => true,
        'fallback_placeholder' => null,
    ],

    'article' => [
        'auto_short_description' => true,
        'short_description_length' => 300,
        'auto_reading_time' => true,
        'words_per_minute' => 200,
        'default_status' => 'published',
        'default_author' => 'Редакция',
    ],
];
