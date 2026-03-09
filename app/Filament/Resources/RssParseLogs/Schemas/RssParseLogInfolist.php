<?php

namespace App\Filament\Resources\RssParseLogs\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RssParseLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('rssFeed.title')
                    ->label('Rss feed'),
                TextEntry::make('started_at')
                    ->dateTime(),
                TextEntry::make('finished_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('new_count')
                    ->numeric(),
                TextEntry::make('skip_count')
                    ->numeric(),
                TextEntry::make('error_count')
                    ->numeric(),
                TextEntry::make('total_items')
                    ->numeric(),
                TextEntry::make('duration_ms')
                    ->numeric()
                    ->placeholder('-'),
                IconEntry::make('success')
                    ->boolean(),
                TextEntry::make('error_message')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('item_errors')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('triggered_by'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
