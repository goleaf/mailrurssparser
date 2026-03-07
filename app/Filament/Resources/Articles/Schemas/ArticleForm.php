<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Models\Article;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('article-tabs')
                    ->key('article-editor-tabs')
                    ->persistTabInQueryString('article-tab')
                    ->columnSpanFull()
                    ->tabs([
                        self::contentTab(),
                        self::mediaTab(),
                        self::classificationTab(),
                        self::publishingTab(),
                        self::seoTab(),
                    ]),
            ]);
    }

    private static function contentTab(): Tab
    {
        return Tab::make('Контент')
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(1000)
                    ->columnSpanFull()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $set('slug', Str::slug($state ?? ''));
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),
                Grid::make(2)
                    ->schema([
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('sub_category_id', null);
                            }),
                        Select::make('sub_category_id')
                            ->relationship(
                                name: 'subCategory',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, Get $get): Builder {
                                    $categoryId = $get('category_id');

                                    if (blank($categoryId)) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    return $query->where('category_id', $categoryId);
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->disabled(fn (Get $get): bool => blank($get('category_id'))),
                    ]),
                Textarea::make('short_description')
                    ->nullable()
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull()
                    ->helperText('~300 символов. Показывается в карточке статьи. SEO важно.'),
                RichEditor::make('full_description')
                    ->nullable()
                    ->columnSpanFull()
                    ->fileAttachmentsDisk('public')
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike', 'link'],
                        ['orderedList', 'bulletList', 'blockquote'],
                        ['h2', 'h3', 'codeBlock'],
                        ['undo', 'redo'],
                    ])
                    ->helperText('Полное содержание. Если пусто — показывается RSS-контент.'),
            ]);
    }

    private static function mediaTab(): Tab
    {
        return Tab::make('Медиа и источник')
            ->schema([
                TextInput::make('image_url')
                    ->url()
                    ->nullable()
                    ->columnSpanFull()
                    ->live(onBlur: true),
                Placeholder::make('image_preview')
                    ->label('Предпросмотр изображения')
                    ->columnSpanFull()
                    ->hidden(fn (Get $get): bool => blank($get('image_url')))
                    ->content(function (Get $get): HtmlString {
                        $imageUrl = e((string) $get('image_url'));

                        return new HtmlString(
                            "<img src=\"{$imageUrl}\" alt=\"Preview\" class=\"max-h-64 rounded-xl border border-gray-200 object-cover shadow-sm\" />",
                        );
                    }),
                Grid::make(2)
                    ->schema([
                        TextInput::make('image_caption')
                            ->nullable()
                            ->maxLength(255),
                        TextInput::make('source_url')
                            ->url()
                            ->nullable(),
                        TextInput::make('source_name')
                            ->nullable()
                            ->default('Новости Mail'),
                        TextInput::make('author')
                            ->nullable(),
                        TextInput::make('author_url')
                            ->url()
                            ->nullable(),
                    ]),
                Textarea::make('rss_content')
                    ->rows(5)
                    ->columnSpanFull()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Оригинальный RSS-контент (только чтение)'),
            ]);
    }

    private static function classificationTab(): Tab
    {
        return Tab::make('Теги и Классификация')
            ->badge(fn (?Article $record, Get $get): int => self::resolveTagBadgeCount($record, $get('tags')))
            ->badgeColor(fn (?Article $record, Get $get): string => self::resolveTagBadgeCount($record, $get('tags')) > 0 ? 'success' : 'gray')
            ->deferBadge(fn (?Article $record): bool => $record !== null)
            ->schema([
                Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('slug', Str::slug($state ?? ''));
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'tags', column: 'slug'),
                        ColorPicker::make('color')
                            ->required()
                            ->default('#6B7280'),
                    ]),
                Grid::make(2)
                    ->schema([
                        Select::make('content_type')
                            ->required()
                            ->default('news')
                            ->options([
                                'news' => 'Новости',
                                'article' => 'Статья',
                                'opinion' => 'Мнение',
                                'analysis' => 'Аналитика',
                                'interview' => 'Интервью',
                            ]),
                        Select::make('importance')
                            ->required()
                            ->default(5)
                            ->options(self::importanceOptions())
                            ->native(false),
                    ]),
                Grid::make(4)
                    ->schema([
                        Toggle::make('is_featured')
                            ->label('Рекомендуемая'),
                        Toggle::make('is_breaking')
                            ->label('Срочная'),
                        Toggle::make('is_pinned')
                            ->label('Закреплена'),
                        Toggle::make('is_editors_choice')
                            ->label('Выбор редакции'),
                        Toggle::make('is_sponsored')
                            ->label('Партнёрский материал'),
                    ]),
            ]);
    }

    private static function publishingTab(): Tab
    {
        return Tab::make('Публикация')
            ->schema([
                Select::make('status')
                    ->required()
                    ->default('draft')
                    ->options([
                        'draft' => 'Черновик',
                        'pending' => 'На модерации',
                        'published' => 'Опубликовано',
                        'archived' => 'Архив',
                    ]),
                DateTimePicker::make('published_at')
                    ->seconds(false)
                    ->displayFormat('d.m.Y H:i'),
                Grid::make(2)
                    ->schema([
                        Placeholder::make('views_count_display')
                            ->label('Просмотры')
                            ->content(fn (?Article $record): string => (string) ($record?->views_count ?? 0)),
                        Placeholder::make('bookmarks_count_display')
                            ->label('Закладки')
                            ->content(fn (?Article $record): string => (string) ($record?->bookmarks_count ?? 0)),
                        Placeholder::make('reading_time_display')
                            ->label('Время чтения')
                            ->content(fn (?Article $record): string => sprintf('%d мин', $record?->reading_time ?? 1)),
                        Placeholder::make('importance_display')
                            ->label('Важность')
                            ->content(fn (Get $get, ?Article $record): string => sprintf('%s/10', $get('importance') ?? $record?->importance ?? 5)),
                        Placeholder::make('rss_parsed_at_display')
                            ->label('RSS парсинг')
                            ->content(fn (?Article $record): string => $record?->rss_parsed_at?->format('d.m.Y H:i') ?? '—'),
                        Placeholder::make('last_edited_at_display')
                            ->label('Последнее изменение')
                            ->content(fn (?Article $record): string => $record?->last_edited_at?->format('d.m.Y H:i') ?? '—'),
                    ]),
            ]);
    }

    private static function seoTab(): Tab
    {
        return Tab::make('SEO')
            ->schema([
                TextInput::make('meta_title')
                    ->nullable()
                    ->maxLength(70)
                    ->helperText('До 70 символов'),
                Textarea::make('meta_description')
                    ->nullable()
                    ->rows(3)
                    ->maxLength(160)
                    ->helperText('До 160 символов'),
                TextInput::make('canonical_url')
                    ->url()
                    ->nullable(),
                Placeholder::make('seo_preview')
                    ->label('SEO Preview')
                    ->columnSpanFull()
                    ->content(function (Get $get): HtmlString {
                        $title = e((string) ($get('meta_title') ?: $get('title') ?: 'Заголовок статьи'));
                        $slug = e((string) ($get('slug') ?: 'article-slug'));
                        $description = e(Str::limit((string) ($get('meta_description') ?: $get('short_description') ?: 'Описание статьи для поисковой выдачи.'), 160));
                        $host = e((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'portal.test'));

                        return new HtmlString(
                            '<div class="space-y-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">'
                            .'<div class="text-sm text-blue-700">'.$host.'/#/articles/'.$slug.'</div>'
                            .'<div class="text-lg font-semibold text-blue-600">'.$title.'</div>'
                            .'<div class="text-sm text-gray-600">'.$description.'</div>'
                            .'</div>',
                        );
                    }),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function importanceOptions(): array
    {
        return [
            1 => '1 — минимум',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5 — средняя',
            6 => '6',
            7 => '7',
            8 => '8',
            9 => '9',
            10 => '10 — максимум',
        ];
    }

    /**
     * @param  mixed  $tagsState
     */
    private static function resolveTagBadgeCount(?Article $record, mixed $tagsState): int
    {
        if (is_array($tagsState)) {
            return count(array_filter($tagsState, fn (mixed $tag): bool => filled($tag)));
        }

        return $record?->tags()->count() ?? 0;
    }
}
