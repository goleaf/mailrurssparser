<?php

namespace App\Filament\Resources\RssFeeds\Schemas;

use App\Filament\Support\AdminUiIconResolver;
use App\Models\RssFeed;
use App\Services\ArticleContentType;
use App\Services\ArticleStatus;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

class RssFeedForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Лента')
                    ->icon(AdminUiIconResolver::section('Лента'))
                    ->description('Паспорт источника, расписание обработки и публикационная стратегия для конкретной RSS-ленты.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('category_id')
                            ->label('Рубрика')
                            ->prefixIcon(AdminUiIconResolver::field('category_id'))
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->placeholder('Выберите рубрику'),
                        TextInput::make('title')
                            ->label('Название ленты')
                            ->prefixIcon(AdminUiIconResolver::field('title'))
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Например, Экономика Mail.ru'),
                        TextInput::make('url')
                            ->prefixIcon(AdminUiIconResolver::field('url'))
                            ->url()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('https://news.mail.ru/rss/90/'),
                        TextInput::make('source_name')
                            ->label('Отображаемый источник')
                            ->prefixIcon(AdminUiIconResolver::field('source_name'))
                            ->required()
                            ->default((string) config('rss.source_name', ''))
                            ->maxLength(255)
                            ->placeholder('Mail.ru Новости'),
                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Активна')
                                    ->default(true),
                                Toggle::make('auto_publish')
                                    ->label('Автопубликация')
                                    ->default(true),
                                Toggle::make('auto_featured')
                                    ->label('Автовыделение')
                                    ->default(false),
                            ]),
                        TextInput::make('fetch_interval')
                            ->label('Интервал обновления')
                            ->prefixIcon(AdminUiIconResolver::field('fetch_interval'))
                            ->numeric()
                            ->integer()
                            ->default(15)
                            ->suffix('мин')
                            ->helperText('Через сколько минут лента снова попадёт в очередь обработки.'),
                    ])
                    ->columns(2),
                Section::make('Переопределения ленты')
                    ->icon(AdminUiIconResolver::section('Переопределения ленты'))
                    ->columnSpanFull()
                    ->description('Переопределите значения, которые RSS-парсер подставляет в импортируемые статьи.')
                    ->schema([
                        Repeater::make('extra_settings_rows')
                            ->label('Структурированные переопределения')
                            ->default([])
                            ->defaultItems(0)
                            ->table([
                                TableColumn::make('Параметр')
                                    ->markAsRequired()
                                    ->width('220px'),
                                TableColumn::make('Значение')
                                    ->markAsRequired()
                                    ->alignment(Alignment::Start),
                            ])
                            ->compact()
                            ->schema([
                                Select::make('key')
                                    ->label('Параметр')
                                    ->required()
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->options(self::extraSettingOptions())
                                    ->prefixIcon(AdminUiIconResolver::field('key')),
                                TextInput::make('value')
                                    ->label('Значение')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->prefixIcon(AdminUiIconResolver::field('value'))
                                    ->placeholder(fn (Get $get): ?string => self::extraSettingPlaceholder($get('key')))
                                    ->helperText(fn (Get $get): ?string => self::extraSettingHelperText($get('key'))),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                $key = $state['key'] ?? null;

                                if (! is_string($key) || $key === '') {
                                    return null;
                                }

                                return self::extraSettingOptions()[$key] ?? $key;
                            }),
                    ]),
                Section::make('Состояние (только чтение)')
                    ->icon(AdminUiIconResolver::section('Состояние (только чтение)'))
                    ->columnSpanFull()
                    ->description('Поля обновляются после запуска парсинга и помогают быстро понять, где лента выпала из графика.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('last_parsed_at_display')
                                    ->label('Последний запуск')
                                    ->content(fn (?RssFeed $record): string => $record?->last_parsed_at?->format('d.m.Y H:i') ?? '—'),
                                Placeholder::make('next_parse_at_display')
                                    ->label('Следующий запуск')
                                    ->content(fn (?RssFeed $record): string => $record?->next_parse_at?->format('d.m.Y H:i') ?? '—'),
                                Placeholder::make('articles_parsed_total_display')
                                    ->label('Импортировано статей')
                                    ->content(fn (?RssFeed $record): string => (string) ($record?->articles_parsed_total ?? 0)),
                                Placeholder::make('last_run_new_count_display')
                                    ->label('Новых за последний запуск')
                                    ->content(fn (?RssFeed $record): string => (string) ($record?->last_run_new_count ?? 0)),
                                Placeholder::make('consecutive_failures_display')
                                    ->label('Сбоев подряд')
                                    ->content(fn (?RssFeed $record): string => (string) ($record?->consecutive_failures ?? 0)),
                            ]),
                        Textarea::make('last_error')
                            ->rows(3)
                            ->disabled()
                            ->saved(false)
                            ->columnSpanFull()
                            ->extraInputAttributes(['class' => 'text-danger-600']),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function extraSettingOptions(): array
    {
        return [
            'status' => 'Статус статьи',
            'content_type' => 'Тип материала',
            'source_name' => 'Название источника',
            'default_author' => 'Автор по умолчанию',
            'sub_category_name' => 'Название подкатегории',
            'sub_category_slug' => 'Слаг подкатегории',
            'short_description_length' => 'Длина краткого описания',
            'source_page_enabled' => 'Обогащение со страницы источника',
            'source_page_min_body_characters' => 'Минимальная длина текста источника',
            'source_page_title_selector' => 'CSS-селекторы заголовка',
            'source_page_subtitle_selector' => 'CSS-селекторы подзаголовка',
            'source_page_article_selector' => 'CSS-селекторы статьи',
            'source_page_author_selector' => 'CSS-селекторы автора',
            'source_page_image_selector' => 'CSS-селекторы изображения',
            'source_page_remove_selectors' => 'CSS-селекторы очистки',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<int, array{key: string, value: string}>
     */
    public static function rowsFromExtraSettings(?array $settings): array
    {
        return collect($settings ?? [])
            ->map(function (mixed $value, mixed $key): ?array {
                if (! is_string($key) || $key === '') {
                    return null;
                }

                return [
                    'key' => $key,
                    'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $rows
     * @return array<string, mixed>
     */
    public static function extraSettingsFromRows(?array $rows): array
    {
        return collect($rows ?? [])
            ->reduce(function (array $settings, array $row): array {
                $key = $row['key'] ?? null;
                $value = $row['value'] ?? null;

                if (! is_string($key) || $key === '') {
                    return $settings;
                }

                $normalizedValue = self::normalizeExtraSettingValue($key, $value);

                if ($normalizedValue === null) {
                    return $settings;
                }

                $settings[$key] = $normalizedValue;

                return $settings;
            }, []);
    }

    private static function extraSettingPlaceholder(?string $key): ?string
    {
        return match ($key) {
            'status' => 'draft / pending / published',
            'content_type' => 'news / analysis / opinion',
            'source_name' => 'Пользовательский источник',
            'default_author' => 'Автор ленты',
            'sub_category_name' => 'Отрасли',
            'sub_category_slug' => 'otrasli',
            'short_description_length' => '300',
            'source_page_enabled' => '1',
            'source_page_min_body_characters' => '180',
            'source_page_title_selector' => 'h1, .article__title',
            'source_page_subtitle_selector' => '.article__subtitle, .article__lead',
            'source_page_article_selector' => '[article-item-type="html"], article, main',
            'source_page_author_selector' => '[rel="author"], .article__author',
            'source_page_image_selector' => 'meta[property="og:image"], article figure img',
            'source_page_remove_selectors' => '.share, .related, .advert',
            default => null,
        };
    }

    private static function extraSettingHelperText(?string $key): ?string
    {
        return match ($key) {
            'status' => 'Допустимые значения: '.implode(', ', ArticleStatus::values()),
            'content_type' => 'Допустимые значения: '.implode(', ', ArticleContentType::values()),
            'short_description_length' => 'Сохраняет положительное целое число символов.',
            'sub_category_name' => 'Назначает импортируемые статьи в подкатегорию внутри рубрики ленты.',
            'sub_category_slug' => 'Использует указанный слаг при поиске или создании подкатегории.',
            'source_page_enabled' => 'Используйте 1 или 0 для включения парсинга оригинальной страницы материала из RSS.',
            'source_page_min_body_characters' => 'Минимальная длина очищенного текста перед заменой RSS-контента HTML-версией источника.',
            'source_page_title_selector',
            'source_page_subtitle_selector',
            'source_page_article_selector',
            'source_page_author_selector',
            'source_page_image_selector',
            'source_page_remove_selectors' => 'CSS-селекторы через запятую. Значения ленты применяются раньше глобальных.',
            default => null,
        };
    }

    private static function normalizeExtraSettingValue(string $key, mixed $value): string|int|null
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return match ($key) {
            'status' => in_array($value, ArticleStatus::values(), true) ? $value : null,
            'content_type' => in_array($value, ArticleContentType::values(), true) ? $value : null,
            'short_description_length' => max(1, (int) $value),
            'source_page_enabled' => in_array(strtolower($value), ['1', '0', 'true', 'false', 'yes', 'no', 'on', 'off'], true)
                ? (int) in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true)
                : null,
            'source_page_min_body_characters' => max(40, (int) $value),
            'source_page_title_selector',
            'source_page_subtitle_selector',
            'source_page_article_selector',
            'source_page_author_selector',
            'source_page_image_selector',
            'source_page_remove_selectors' => $value,
            default => $value,
        };
    }
}
