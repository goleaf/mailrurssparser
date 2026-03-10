<?php

namespace App\Filament\Resources\ArticleViews\Schemas;

use App\Filament\Support\AdminUiIconResolver;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticleViewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Просмотр')
                    ->icon(AdminUiIconResolver::section('Просмотр'))
                    ->columnSpanFull()
                    ->schema([
                        Select::make('article_id')
                            ->relationship('article', 'title')
                            ->required()
                            ->searchable()
                            ->preload(),
                        DateTimePicker::make('viewed_at')
                            ->required(),
                    ]),
                Section::make('Контекст пользователя')
                    ->icon(AdminUiIconResolver::section('Контекст пользователя'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('ip_address'),
                                TextInput::make('session_id'),
                                TextInput::make('ip_hash'),
                                TextInput::make('session_hash'),
                                TextInput::make('country_code')
                                    ->maxLength(2),
                                Select::make('device_type')
                                    ->options([
                                        'desktop' => 'Desktop',
                                        'mobile' => 'Mobile',
                                        'tablet' => 'Tablet',
                                    ])
                                    ->native(false),
                                Select::make('referrer_type')
                                    ->options([
                                        'direct' => 'Direct',
                                        'search' => 'Search',
                                        'social' => 'Social',
                                        'internal' => 'Internal',
                                    ])
                                    ->native(false),
                                TextInput::make('referrer_domain'),
                                TextInput::make('timezone'),
                                TextInput::make('locale'),
                                TextInput::make('referer')
                                    ->columnSpanFull(),
                                TextInput::make('user_agent')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
