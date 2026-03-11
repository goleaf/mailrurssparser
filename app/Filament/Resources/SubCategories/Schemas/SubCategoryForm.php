<?php

namespace App\Filament\Resources\SubCategories\Schemas;

use App\Filament\Support\AdminUiIconResolver;
use App\Filament\Support\SlugGeneratorAction;
use App\Models\Category;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use RalphJSmit\Filament\SEO\SEO;

class SubCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Подкатегория')
                    ->icon(AdminUiIconResolver::section('Подкатегория'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('category_id')
                                    ->relationship('category', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                        if (blank($state)) {
                                            return;
                                        }

                                        $category = Category::query()->find($state);

                                        if ($category === null) {
                                            return;
                                        }

                                        if (blank($get('color'))) {
                                            $set('color', $category->color);
                                        }

                                        if (blank($get('icon'))) {
                                            $set('icon', $category->icon);
                                        }
                                    }),
                                TextInput::make('order')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterContent(
                                        SlugGeneratorAction::make(
                                            sourceField: 'name',
                                            name: 'generateSubCategorySlug',
                                        ),
                                    )
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                        if ((bool) $get('is_slug_manual')) {
                                            return;
                                        }

                                        $set('slug', Str::slug($state ?? ''));
                                    }),
                                Toggle::make('is_slug_manual')
                                    ->label('Редактировать slug вручную')
                                    ->dehydrated(false)
                                    ->live(),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->disabled(fn (Get $get): bool => ! (bool) $get('is_slug_manual'))
                                    ->dehydrated(),
                                ColorPicker::make('color')
                                    ->required()
                                    ->default('#3B82F6'),
                                TextInput::make('icon')
                                    ->nullable()
                                    ->maxLength(10)
                                    ->hint('Emoji, например 🏙️'),
                            ]),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->default(true),
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
            ]);
    }
}
