<?php

namespace App\Filament\Support;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class AdminUiIconResolver
{
    public static function field(?string $name): string|BackedEnum|null
    {
        if (! is_string($name) || $name === '') {
            return null;
        }

        return self::fieldMatch($name) ?? self::fieldMatch(Str::afterLast($name, '.'));
    }

    public static function section(?string $heading): string|BackedEnum|null
    {
        if (! is_string($heading) || $heading === '') {
            return null;
        }

        return match ($heading) {
            'Информация' => Heroicon::OutlinedInformationCircle,
            'Настройки' => Heroicon::OutlinedAdjustmentsHorizontal,
            'Подкатегория' => Heroicon::OutlinedFolderOpen,
            'Подписчик' => Heroicon::OutlinedEnvelope,
            'Статус и атрибуция' => Heroicon::OutlinedCheckCircle,
            'Метрика' => Heroicon::OutlinedChartBarSquare,
            'Лента', 'Обзор ленты', 'Переопределения ленты' => Heroicon::OutlinedRss,
            'Состояние (только чтение)', 'Состояние парсера' => Heroicon::OutlinedClock,
            'Лог запуска', 'Запуск и источник' => Heroicon::OutlinedPlayCircle,
            'Результат', 'Итог обработки' => Heroicon::OutlinedChartBarSquare,
            'Контекст пользователя', 'Сессия и устройство' => Heroicon::OutlinedUserCircle,
            'Просмотр', 'Просмотр материала' => Heroicon::OutlinedEye,
            'Маршрут перехода' => Heroicon::OutlinedGlobeAlt,
            'Закладка', 'Сводка закладки' => Heroicon::OutlinedBookmark,
            'Профиль подписчика' => Heroicon::OutlinedUserCircle,
            'Статус подписки', 'Публикация' => Heroicon::OutlinedCheckCircle,
            'География и атрибуция' => Heroicon::OutlinedGlobeEuropeAfrica,
            'Агрегация и значение' => Heroicon::OutlinedChartBarSquare,
            'Служебные данные' => Heroicon::OutlinedFingerPrint,
            'Ошибки и диагностика' => Heroicon::OutlinedExclamationTriangle,
            default => null,
        };
    }

    public static function tab(?string $label): string|BackedEnum|null
    {
        if (! is_string($label) || $label === '') {
            return null;
        }

        return match ($label) {
            'Контент' => Heroicon::OutlinedDocumentText,
            'Медиа и источник' => Heroicon::OutlinedPhoto,
            'Теги и Классификация' => Heroicon::OutlinedTag,
            'Публикация' => Heroicon::OutlinedCheckCircle,
            'SEO' => Heroicon::OutlinedMagnifyingGlass,
            default => null,
        };
    }

    /**
     * @return array{on: string|BackedEnum, off: string|BackedEnum}
     */
    public static function toggle(?string $name): array
    {
        return match ($name) {
            'is_active' => [
                'on' => Heroicon::OutlinedSignal,
                'off' => Heroicon::OutlinedSignalSlash,
            ],
            'is_featured', 'auto_featured', 'is_trending', 'is_editors_choice' => [
                'on' => Heroicon::OutlinedSparkles,
                'off' => Heroicon::OutlinedXCircle,
            ],
            default => [
                'on' => Heroicon::OutlinedCheckCircle,
                'off' => Heroicon::OutlinedXCircle,
            ],
        };
    }

    private static function fieldMatch(string $name): string|BackedEnum|null
    {
        return match ($name) {
            'article.title', 'article_id', 'title' => Heroicon::OutlinedNewspaper,
            'rssFeed.title', 'rss_feed_id', 'rss_url', 'last_parsed_at_display', 'next_parse_at_display' => Heroicon::OutlinedRss,
            'rss_content', 'full_description', 'error_message', 'meta_title', 'meta_description' => Heroicon::OutlinedDocumentText,
            'category.name', 'category_id', 'category_ids' => Heroicon::OutlinedFolder,
            'subCategory.name', 'sub_category_id' => Heroicon::OutlinedFolderOpen,
            'tags', 'tag' => Heroicon::OutlinedTag,
            'name' => Heroicon::OutlinedIdentification,
            'slug', 'canonical_url', 'author_url', 'referer', 'source_url' => Heroicon::OutlinedLink,
            'color' => Heroicon::OutlinedSwatch,
            'icon' => Heroicon::OutlinedSparkles,
            'description', 'short_description' => Heroicon::OutlinedChatBubbleLeft,
            'image_url', 'uploaded_image', 'image_caption' => Heroicon::OutlinedPhoto,
            'source_name', 'referrer_domain', 'url' => Heroicon::OutlinedGlobeAlt,
            'author', 'editor_id' => Heroicon::OutlinedUser,
            'content_type' => Heroicon::OutlinedDocumentText,
            'importance', 'importance_display', 'value' => Heroicon::OutlinedChartBarSquare,
            'status' => Heroicon::OutlinedCheckCircle,
            'published_at', 'confirmed_at', 'unsubscribed_at', 'bucket_start', 'bucket_date' => Heroicon::OutlinedCalendarDays,
            'views_count_display', 'viewed_at' => Heroicon::OutlinedEye,
            'bookmarks_count_display', 'reading_time_display', 'duration_ms', 'fetch_interval', 'timezone', 'created_at', 'updated_at' => Heroicon::OutlinedClock,
            'rss_parsed_at_display' => Heroicon::OutlinedRss,
            'last_edited_at_display' => Heroicon::OutlinedPencilSquare,
            'email' => Heroicon::OutlinedEnvelope,
            'token', 'key', 'rss_key' => Heroicon::OutlinedKey,
            'ip_address', 'country_code' => Heroicon::OutlinedMapPin,
            'ip_hash', 'session_hash', 'fingerprint' => Heroicon::OutlinedFingerPrint,
            'session_id', 'measurable_id' => Heroicon::OutlinedIdentification,
            'device_type', 'user_agent' => Heroicon::OutlinedComputerDesktop,
            'referrer_type', 'locale' => Heroicon::OutlinedGlobeEuropeAfrica,
            'measurable_type' => Heroicon::OutlinedCube,
            'order' => Heroicon::OutlinedArrowsUpDown,
            'show_in_menu' => Heroicon::OutlinedBars3,
            'usage_count', 'articles_parsed_total_display', 'total_items' => Heroicon::OutlinedClipboardDocumentList,
            'is_trending' => Heroicon::OutlinedArrowTrendingUp,
            'last_run_new_count_display', 'new_count' => Heroicon::OutlinedDocumentPlus,
            'skip_count' => Heroicon::OutlinedMinusCircle,
            'error_count', 'last_error', 'consecutive_failures_display' => Heroicon::OutlinedExclamationTriangle,
            'item_errors' => Heroicon::OutlinedQueueList,
            'triggered_by' => Heroicon::OutlinedBolt,
            'started_at' => Heroicon::OutlinedPlayCircle,
            'finished_at' => Heroicon::OutlinedFlag,
            default => null,
        };
    }
}
