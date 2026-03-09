<?php

namespace App\Filament\Resources\Bookmarks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BookmarkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('session_hash'),
                TextEntry::make('article.title')
                    ->label('Article'),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
