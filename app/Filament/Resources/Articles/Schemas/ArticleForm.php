<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Filament\Support\SlugGeneratorAction;
use App\Models\Article;
use App\Services\ArticleContentType;
use App\Services\ArticleStatus;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use RalphJSmit\Filament\SEO\SEO;

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
            ->icon(Heroicon::OutlinedDocumentText)
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(1000)
                    ->columnSpanFull()
                    ->live(onBlur: true)
                    ->afterContent(
                        SlugGeneratorAction::make(
                            sourceField: 'title',
                            name: 'generateArticleSlug',
                        ),
                    )
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
                    ->fileAttachmentsVisibility('public')
                    ->fileAttachmentsDirectory('article-content')
                    ->fileAttachmentsAcceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                    ])
                    ->fileAttachmentsMaxSize(5120)
                    ->resizableImages()
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike', 'textColor', 'link'],
                        ['h2', 'h3', 'lead', 'grid'],
                        ['orderedList', 'bulletList', 'blockquote', 'codeBlock', 'table'],
                        ['attachFiles', 'undo', 'redo'],
                    ])
                    ->helperText('Полное содержание. Если пусто — показывается RSS-контент. Используйте @ для рубрик и # для тегов.'),
            ]);
    }

    private static function mediaTab(): Tab
    {
        return Tab::make('Медиа и источник')
            ->icon(Heroicon::OutlinedPhoto)
            ->schema([
                Section::make('RSS / Source Image')
                    ->description('This keeps the original parser-managed image URL. Local uploads in this tab still write back to the same image field, so the RSS pipeline remains unchanged.')
                    ->schema([
                        FileUpload::make('uploaded_image')
                            ->label('Загрузить изображение')
                            ->image()
                            ->imageEditor()
                            ->imageAspectRatio('16:9')
                            ->automaticallyOpenImageEditorForAspectRatio()
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('article-images')
                            ->visibility('public')
                            ->saved(false)
                            ->dehydrated()
                            ->columnSpanFull()
                            ->helperText('Загрузите локальное изображение 16:9 или оставьте поле пустым и используйте внешний URL ниже.'),
                        TextInput::make('image_url')
                            ->label('RSS / source image URL')
                            ->helperText('Автоимпорт RSS и локальная загрузка выше записывают в это строковое поле. Если ниже выбрано editorial image, оно будет показано вместо этого URL.')
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
                                    ->default((string) config('rss.source_name', '')),
                                TextInput::make('author')
                                    ->nullable(),
                                TextInput::make('author_url')
                                    ->url()
                                    ->nullable(),
                            ]),
                    ]),
                Section::make('Editorial Featured Image')
                    ->description('Optionally override the imported source image with manually managed media. Spatie Media Library uploads take priority, then Curator, then the RSS/source image URL.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('featured_image')
                            ->collection('featured_image')
                            ->label('Override Image')
                            ->helperText('Uploads a managed image through Spatie Media Library. This takes priority over Curator and the RSS/source image URL.')
                            ->disk('public')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
                            ->maxSize(10240)
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'image/gif',
                            ])
                            ->conversion('card')
                            ->responsiveImages()
                            ->columnSpanFull(),
                        CuratorPicker::make('curator_media_id')
                            ->relationship('curatorMedia', 'id')
                            ->label('Curator Override Image')
                            ->helperText('Legacy Curator-managed override. This is only used when no Spatie featured image is attached, and it never changes the RSS image column.')
                            ->disk('public')
                            ->directory('curator')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'image/gif',
                                'image/svg+xml',
                                'image/x-icon',
                                'image/vnd.microsoft.icon',
                            ])
                            ->maxSize(10240)
                            ->buttonLabel('Select or Upload Image')
                            ->color('primary')
                            ->outlined(true)
                            ->size('md')
                            ->constrained(true)
                            ->listDisplay(false)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn (?Article $record): bool => ! $record?->hasMedia('featured_image') && $record?->curator_media_id === null),
                Textarea::make('rss_content')
                    ->rows(5)
                    ->columnSpanFull()
                    ->disabled()
                    ->saved(false)
                    ->helperText('Оригинальный RSS-контент (только чтение)'),
            ]);
    }

    private static function classificationTab(): Tab
    {
        return Tab::make('Теги и Классификация')
            ->icon(Heroicon::OutlinedTag)
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
                    ->helperText('Создание новых тегов вынесено на отдельную страницу админки, чтобы редактирование оставалось page-based.'),
                Grid::make(2)
                    ->schema([
                        Select::make('content_type')
                            ->required()
                            ->default(ArticleContentType::News->value)
                            ->options(ArticleContentType::class),
                        Select::make('rss_feed_id')
                            ->relationship('rssFeed', 'title')
                            ->label('RSS-лента')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('editor_id')
                            ->relationship('editor', 'name')
                            ->label('Ответственный редактор')
                            ->searchable()
                            ->preload()
                            ->nullable(),
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
            ->icon(Heroicon::OutlinedCheckCircle)
            ->schema([
                Select::make('status')
                    ->required()
                    ->default(ArticleStatus::Draft->value)
                    ->options(ArticleStatus::class),
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
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->schema([
                Section::make('SEO & Meta')
                    ->description('Переопределите поисковый заголовок, описание, robots, канонический адрес и изображение для соцсетей.')
                    ->schema([
                        SEO::make(['title', 'description', 'robots']),
                        Grid::make(2)
                            ->relationship('seo')
                            ->schema([
                                TextInput::make('image')
                                    ->label('Social image URL')
                                    ->url()
                                    ->nullable()
                                    ->helperText('Используется для Open Graph и Twitter Card. Если пусто, берётся основное изображение статьи.'),
                                TextInput::make('canonical_url')
                                    ->label('Canonical URL')
                                    ->url()
                                    ->nullable()
                                    ->helperText('Оставьте пустым, чтобы использовать URL статьи на портале.'),
                            ]),
                        Textarea::make('structured_data')
                            ->label('Structured data override')
                            ->rows(8)
                            ->nullable()
                            ->columnSpanFull()
                            ->helperText('JSON-LD. Оставьте пустым, чтобы использовать автоматически сгенерированный NewsArticle.')
                            ->rule('json')
                            ->formatStateUsing(function (mixed $state): ?string {
                                if (is_array($state)) {
                                    return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                }

                                return is_string($state) ? $state : null;
                            })
                            ->dehydrateStateUsing(fn (?string $state): ?array => filled($state) ? json_decode($state, true) : null),
                    ]),
                Placeholder::make('seo_preview')
                    ->label('SEO Preview')
                    ->columnSpanFull()
                    ->content(function (Get $get): HtmlString {
                        $title = e((string) ($get('seo.title') ?: $get('title') ?: 'Заголовок статьи'));
                        $slug = e((string) ($get('slug') ?: 'article-slug'));
                        $description = e(Str::limit((string) ($get('seo.description') ?: $get('short_description') ?: 'Описание статьи для поисковой выдачи.'), 160));
                        $authority = rescue(
                            fn (): string => Uri::of((string) config('app.url'))->authority(),
                            'portal.test',
                            false,
                        );
                        $host = e($authority);
                        $canonicalUrl = e((string) ($get('seo.canonical_url') ?: 'https://'.$authority.'/articles/'.$slug));

                        return new HtmlString(
                            '<div class="space-y-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">'
                            .'<div class="text-sm text-blue-700">'.$canonicalUrl.'</div>'
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

    private static function resolveTagBadgeCount(?Article $record, mixed $tagsState): int
    {
        if (is_array($tagsState)) {
            return count(array_filter($tagsState, fn (mixed $tag): bool => filled($tag)));
        }

        return $record?->tags()->count() ?? 0;
    }
}
