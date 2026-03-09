<?php

namespace App\Filament\Resources\ArticleViews\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ArticleViewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('article.title')
                    ->label('Article'),
                TextEntry::make('ip_address')
                    ->placeholder('-'),
                TextEntry::make('session_id')
                    ->placeholder('-'),
                TextEntry::make('user_agent')
                    ->placeholder('-'),
                TextEntry::make('referer')
                    ->placeholder('-'),
                TextEntry::make('viewed_at')
                    ->dateTime(),
                TextEntry::make('ip_hash')
                    ->placeholder('-'),
                TextEntry::make('session_hash')
                    ->placeholder('-'),
                TextEntry::make('country_code')
                    ->placeholder('-'),
                TextEntry::make('device_type')
                    ->placeholder('-'),
                TextEntry::make('referrer_type')
                    ->placeholder('-'),
                TextEntry::make('referrer_domain')
                    ->placeholder('-'),
                TextEntry::make('timezone')
                    ->placeholder('-'),
                TextEntry::make('locale')
                    ->placeholder('-'),
            ]);
    }
}
