<?php

namespace App\Filament\Support;

use Illuminate\Support\Str;

class AdminUiLabelResolver
{
    public static function field(?string $name): ?string
    {
        if (! is_string($name) || $name === '') {
            return null;
        }

        return self::fieldMatch($name) ?? self::fieldMatch(Str::afterLast($name, '.'));
    }

    private static function fieldMatch(string $name): ?string
    {
        return match ($name) {
            'article.title', 'article_id' => 'Статья',
            'article.category.name', 'rssFeed.category.name', 'category.name', 'category_id', 'category' => 'Рубрика',
            'article.subCategory.name', 'subCategory.name', 'sub_category_id' => 'Подкатегория',
            'rssFeed.title', 'rss_feed_id' => 'RSS-лента',
            'category_ids' => 'Интересующие рубрики',
            'title' => 'Заголовок',
            'name' => 'Название',
            'slug' => 'Слаг',
            'description' => 'Описание',
            'short_description' => 'Краткое описание',
            'full_description' => 'Полное описание',
            'icon' => 'Иконка',
            'color' => 'Цвет',
            'order' => 'Порядок',
            'rss_url' => 'URL RSS-ленты',
            'rss_key' => 'RSS-ключ',
            'rss_content' => 'RSS-контент',
            'meta_title' => 'SEO-заголовок',
            'meta_description' => 'SEO-описание',
            'canonical_url' => 'Канонический URL',
            'image_url' => 'URL изображения',
            'image_caption' => 'Подпись изображения',
            'source_url' => 'URL источника',
            'source_name' => 'Источник',
            'author' => 'Автор',
            'author_url' => 'URL автора',
            'tags', 'tags_summary' => 'Теги',
            'content_type' => 'Тип контента',
            'editor.name', 'editor_id' => 'Редактор',
            'importance', 'importance_display' => 'Важность',
            'status' => 'Статус',
            'published_at' => 'Дата публикации',
            'views_count', 'views_count_display' => 'Просмотры',
            'bookmarked_by_count', 'bookmarks_count_display' => 'Закладки',
            'related_articles_count' => 'Связи',
            'usage_count' => 'Использований',
            'sub_categories_count' => 'Подкатегорий',
            'rss_feeds_count' => 'RSS-лент',
            'articles_count', 'articles_count_cache' => 'Статей',
            'parse_logs_count' => 'Логов',
            'is_active' => 'Активно',
            'is_featured' => 'Рекомендуемое',
            'is_trending' => 'В тренде',
            'is_breaking' => 'Срочное',
            'is_pinned' => 'Закреплено',
            'is_editors_choice' => 'Выбор редакции',
            'is_sponsored' => 'Партнёрский материал',
            'show_in_menu' => 'Показывать в меню',
            'confirmed' => 'Подтверждён',
            'confirmed_at' => 'Подтверждён в',
            'unsubscribed_at' => 'Отписан в',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
            'email' => 'Эл. почта',
            'token' => 'Токен',
            'locale' => 'Локаль',
            'timezone' => 'Часовой пояс',
            'country_code' => 'Страна',
            'device_type' => 'Тип устройства',
            'referrer_type' => 'Источник перехода',
            'referrer_domain' => 'Домен-источник',
            'session_id' => 'Идентификатор сессии',
            'session_hash' => 'Хэш сессии',
            'ip_address' => 'IP-адрес',
            'ip_hash' => 'Хэш IP',
            'referer' => 'Полный адрес перехода',
            'user_agent' => 'User-Agent',
            'viewed_at' => 'Просмотрено',
            'url' => 'URL',
            'auto_publish' => 'Автопубликация',
            'auto_featured' => 'Автовыделение',
            'fetch_interval' => 'Интервал',
            'last_parsed_at', 'last_parsed_at_display' => 'Последний запуск',
            'next_parse_at', 'next_parse_at_display' => 'Следующий запуск',
            'last_run_new_count', 'last_run_new_count_display' => 'Новых за последний запуск',
            'consecutive_failures', 'consecutive_failures_display' => 'Сбоев подряд',
            'last_error' => 'Последняя ошибка',
            'measurable_type' => 'Тип сущности',
            'measurable_id' => 'ID сущности',
            'value' => 'Значение',
            'bucket_start', 'bucket_start_display' => 'Начало бакета',
            'bucket_date' => 'Дата бакета',
            'fingerprint' => 'Отпечаток',
            'triggered_by' => 'Источник запуска',
            'started_at' => 'Старт',
            'finished_at' => 'Финиш',
            'duration_ms' => 'Длительность',
            'success' => 'Успешно',
            'new_count', 'new_count_display' => 'Новые',
            'skip_count' => 'Пропущено',
            'error_count' => 'Ошибки',
            'total_items' => 'Всего элементов',
            'error_message' => 'Критическая ошибка',
            'image_preview' => 'Предпросмотр изображения',
            'seo_preview' => 'SEO-превью',
            default => null,
        };
    }
}
