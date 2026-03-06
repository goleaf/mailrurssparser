<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Models\Article;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Main Content')
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
                            ->unique(ignoreRecord: true),
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
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

                                    if (! $categoryId) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    return $query->where('category_id', $categoryId);
                                },
                            )
                            ->nullable()
                            ->disabled(fn (Get $get): bool => blank($get('category_id'))),
                        Textarea::make('short_description')
                            ->nullable()
                            ->rows(4)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->hint('Auto-filled from RSS. Edit manually to improve SEO.'),
                        RichEditor::make('full_description')
                            ->nullable()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'orderedList',
                                'bulletList',
                                'blockquote',
                                'h2',
                                'h3',
                                'undo',
                                'redo',
                            ])
                            ->hint('Leave empty to show RSS content on frontend.'),
                    ])
                    ->columns(2),
                Section::make('Media & Source')
                    ->schema([
                        TextInput::make('image_url')
                            ->url()
                            ->nullable()
                            ->columnSpanFull(),
                        TextInput::make('source_url')
                            ->url()
                            ->nullable(),
                        TextInput::make('source_name')
                            ->nullable()
                            ->default('Новости Mail'),
                        TextInput::make('author')
                            ->nullable(),
                    ])
                    ->columns(2),
                Section::make('Tags & Publishing')
                    ->schema([
                        Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(table: 'tags', column: 'slug'),
                            ]),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->required(),
                        Toggle::make('is_featured'),
                        Toggle::make('is_breaking'),
                        DateTimePicker::make('published_at')
                            ->seconds(false),
                        Placeholder::make('reading_time')
                            ->label('Reading Time')
                            ->content(fn (?Article $record): string => $record?->reading_time !== null ? (string) $record->reading_time : '-'),
                        Placeholder::make('views_count')
                            ->label('Views')
                            ->content(fn (?Article $record): string => $record?->views_count !== null ? (string) $record->views_count : '-'),
                        Placeholder::make('rss_parsed_at')
                            ->label('RSS Parsed At')
                            ->content(fn (?Article $record): string => $record?->rss_parsed_at?->format('Y-m-d H:i') ?? '-'),
                    ])
                    ->columns(2),
            ]);
    }
}
