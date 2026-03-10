<?php

namespace App\Filament\Resources\RssParseLogs\Schemas;

use App\Filament\Support\AdminUiIconResolver;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RssParseLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Лог запуска')
                    ->icon(AdminUiIconResolver::section('Лог запуска'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('rss_feed_id')
                                    ->relationship('rssFeed', 'title')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Select::make('triggered_by')
                                    ->required()
                                    ->default('scheduler')
                                    ->options([
                                        'scheduler' => 'Планировщик',
                                        'manual' => 'Вручную',
                                        'api' => 'API',
                                        'filament' => 'Filament',
                                    ]),
                                DateTimePicker::make('started_at')
                                    ->required(),
                                DateTimePicker::make('finished_at'),
                                TextInput::make('duration_ms')
                                    ->numeric(),
                                Toggle::make('success')
                                    ->required(),
                            ]),
                    ]),
                Section::make('Результат')
                    ->icon(AdminUiIconResolver::section('Результат'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('new_count')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('skip_count')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('error_count')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('total_items')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('error_message')
                                    ->columnSpanFull(),
                                TagsInput::make('item_errors')
                                    ->columnSpanFull()
                                    ->placeholder('Добавьте ошибки элементов'),
                            ]),
                    ]),
            ]);
    }
}
