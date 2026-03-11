<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\AdminUiIconResolver;
use App\Filament\Support\SlugGeneratorAction;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use RalphJSmit\Filament\SEO\SEO;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Информация')
                    ->icon(AdminUiIconResolver::section('Информация'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterContent(
                                        SlugGeneratorAction::make(
                                            sourceField: 'name',
                                            name: 'generateCategorySlug',
                                        ),
                                    )
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        $set('slug', Str::slug($state ?? ''));
                                    }),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                ColorPicker::make('color')
                                    ->required()
                                    ->default('#3B82F6'),
                                TextInput::make('icon')
                                    ->nullable()
                                    ->maxLength(10)
                                    ->hint('Emoji, например 🏛️'),
                                TextInput::make('rss_url')
                                    ->url()
                                    ->nullable()
                                    ->placeholder(rtrim((string) config('rss.feed_origin', 'https://example.com'), '/').'/rss/politics/'),
                                TextInput::make('rss_key')
                                    ->nullable()
                                    ->maxLength(50),
                            ]),
                        Textarea::make('description')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Cover Image')
                    ->icon(AdminUiIconResolver::section('Cover Image'))
                    ->columnSpanFull()
                    ->description('Cover image displayed on the category page on the portal. Spatie Media Library uploads take priority over Curator-managed media.')
                    ->collapsible()
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('cover_image')
                            ->collection('cover_image')
                            ->label('Category Cover Image')
                            ->helperText('Managed through Spatie Media Library and used as the primary category cover image.')
                            ->disk('public')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios(['3:1', '16:9'])
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                            ->conversion('banner')
                            ->responsiveImages()
                            ->columnSpanFull(),
                        CuratorPicker::make('curator_cover_id')
                            ->relationship('coverImage', 'id')
                            ->label('Curator Cover Image')
                            ->helperText('Legacy Curator-managed cover image. Used only when no Spatie cover image is attached.')
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
                            ->buttonLabel('Select or Upload Cover Image')
                            ->color('primary')
                            ->outlined(true)
                            ->constrained(true)
                            ->columnSpanFull(),
                    ]),
                Section::make('SEO & Meta')
                    ->icon(AdminUiIconResolver::section('SEO & Meta'))
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        SEO::make(['title', 'description', 'robots']),
                        Grid::make(2)
                            ->relationship('seo')
                            ->schema([
                                TextInput::make('image')
                                    ->label('Social image URL')
                                    ->url()
                                    ->nullable(),
                                TextInput::make('canonical_url')
                                    ->label('Canonical URL')
                                    ->url()
                                    ->nullable(),
                            ]),
                    ]),
                Section::make('Настройки')
                    ->icon(AdminUiIconResolver::section('Настройки'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('order')
                                    ->numeric()
                                    ->integer()
                                    ->default(0),
                                Toggle::make('is_active')
                                    ->default(true),
                                Toggle::make('show_in_menu')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }
}
