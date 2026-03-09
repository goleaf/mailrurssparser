<?php

namespace App\Filament\Resources\RssParseLogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RssParseLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('rss_feed_id')
                    ->relationship('rssFeed', 'title')
                    ->required(),
                DateTimePicker::make('started_at')
                    ->required(),
                DateTimePicker::make('finished_at'),
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
                TextInput::make('duration_ms')
                    ->numeric(),
                Toggle::make('success')
                    ->required(),
                Textarea::make('error_message')
                    ->columnSpanFull(),
                Textarea::make('item_errors')
                    ->columnSpanFull(),
                TextInput::make('triggered_by')
                    ->required()
                    ->default('scheduler'),
            ]);
    }
}
