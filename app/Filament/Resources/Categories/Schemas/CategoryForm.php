<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\AdminUiIconResolver;
use App\Filament\Support\SlugGeneratorAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

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
                        TextInput::make('meta_title')
                            ->nullable()
                            ->maxLength(255),
                        Textarea::make('meta_description')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
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
