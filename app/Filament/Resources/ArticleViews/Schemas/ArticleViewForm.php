<?php

namespace App\Filament\Resources\ArticleViews\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ArticleViewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('article_id')
                    ->relationship('article', 'title')
                    ->required(),
                TextInput::make('ip_address'),
                TextInput::make('session_id'),
                TextInput::make('user_agent'),
                TextInput::make('referer'),
                DateTimePicker::make('viewed_at')
                    ->required(),
                TextInput::make('ip_hash'),
                TextInput::make('session_hash'),
                TextInput::make('country_code'),
                TextInput::make('device_type'),
                TextInput::make('referrer_type'),
                TextInput::make('referrer_domain'),
                TextInput::make('timezone'),
                TextInput::make('locale'),
            ]);
    }
}
