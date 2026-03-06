<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                Section::make('Category Info')
                    ->schema([
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
                            ->unique(ignoreRecord: true),
                        TextInput::make('rss_url')
                            ->url()
                            ->nullable()
                            ->placeholder('https://news.mail.ru/rss/politics/'),
                        TextInput::make('rss_key')
                            ->nullable()
                            ->maxLength(50),
                        ColorPicker::make('color')
                            ->required()
                            ->default('#3B82F6'),
                        TextInput::make('icon')
                            ->nullable()
                            ->maxLength(10)
                            ->hint('Emoji like 🏛️'),
                        Textarea::make('description')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('order')
                            ->numeric()
                            ->integer()
                            ->default(0),
                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
