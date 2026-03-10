<?php

namespace App\Filament\Resources\Bookmarks\Schemas;

use App\Filament\Support\AdminUiIconResolver;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookmarkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Закладка')
                    ->icon(AdminUiIconResolver::section('Закладка'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('session_hash')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('article_id')
                                    ->relationship('article', 'title')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                            ]),
                    ]),
            ]);
    }
}
