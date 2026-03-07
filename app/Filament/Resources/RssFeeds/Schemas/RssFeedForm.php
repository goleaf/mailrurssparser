<?php

namespace App\Filament\Resources\RssFeeds\Schemas;

use App\Models\RssFeed;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RssFeedForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Feed')
                    ->schema([
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('url')
                            ->url()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('source_name')
                            ->required()
                            ->default('Новости Mail')
                            ->maxLength(255),
                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_active')
                                    ->default(true),
                                Toggle::make('auto_publish')
                                    ->default(true),
                                Toggle::make('auto_featured')
                                    ->default(false),
                            ]),
                        TextInput::make('fetch_interval')
                            ->numeric()
                            ->integer()
                            ->default(15)
                            ->suffix('min'),
                    ])
                    ->columns(2),
                Section::make('Status (readonly)')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('last_parsed_at_display')
                                    ->label('last_parsed_at')
                                    ->content(fn (?RssFeed $record): string => $record?->last_parsed_at?->format('d.m.Y H:i') ?? '—'),
                                Placeholder::make('next_parse_at_display')
                                    ->label('next_parse_at')
                                    ->content(fn (?RssFeed $record): string => $record?->next_parse_at?->format('d.m.Y H:i') ?? '—'),
                                Placeholder::make('articles_parsed_total_display')
                                    ->label('articles_parsed_total')
                                    ->content(fn (?RssFeed $record): string => (string) ($record?->articles_parsed_total ?? 0)),
                                Placeholder::make('last_run_new_count_display')
                                    ->label('last_run_new_count')
                                    ->content(fn (?RssFeed $record): string => (string) ($record?->last_run_new_count ?? 0)),
                                Placeholder::make('consecutive_failures_display')
                                    ->label('consecutive_failures')
                                    ->content(fn (?RssFeed $record): string => (string) ($record?->consecutive_failures ?? 0)),
                            ]),
                        Textarea::make('last_error')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->extraInputAttributes(['class' => 'text-danger-600']),
                    ]),
            ]);
    }
}
