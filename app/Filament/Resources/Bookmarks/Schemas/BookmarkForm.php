<?php

namespace App\Filament\Resources\Bookmarks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BookmarkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('session_hash')
                    ->required(),
                Select::make('article_id')
                    ->relationship('article', 'title')
                    ->required(),
            ]);
    }
}
