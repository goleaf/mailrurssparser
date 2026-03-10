<?php

namespace App\Filament\Resources\Bookmarks\Schemas;

use App\Filament\Support\AdminUiIconResolver;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BookmarkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Сводка закладки')
                    ->icon(AdminUiIconResolver::section('Сводка закладки'))
                    ->columnSpanFull()
                    ->description('Основная информация о сохранённом материале и сессии читателя.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('article.title')
                            ->label('Статья')
                            ->icon(Heroicon::OutlinedNewspaper)
                            ->weight('bold'),
                        TextEntry::make('created_at')
                            ->label('Создано')
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime('d.m.Y H:i:s'),
                        TextEntry::make('session_hash')
                            ->label('Хэш сессии')
                            ->icon(Heroicon::OutlinedFingerPrint)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
