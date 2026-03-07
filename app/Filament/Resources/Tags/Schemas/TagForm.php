<?php

namespace App\Filament\Resources\Tags\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Информация')
                    ->schema([
                        Grid::make(2)
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
                                ColorPicker::make('color')
                                    ->required()
                                    ->default('#6B7280'),
                                TextInput::make('usage_count')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(0),
                            ]),
                        Textarea::make('description')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_trending')
                                    ->default(false),
                                Toggle::make('is_featured')
                                    ->default(false),
                            ]),
                    ]),
            ]);
    }
}
