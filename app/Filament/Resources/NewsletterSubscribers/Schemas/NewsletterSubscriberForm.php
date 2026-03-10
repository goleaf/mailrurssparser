<?php

namespace App\Filament\Resources\NewsletterSubscribers\Schemas;

use App\Filament\Support\AdminUiIconResolver;
use App\Models\Category;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NewsletterSubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Подписчик')
                    ->icon(AdminUiIconResolver::section('Подписчик'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                TextInput::make('name')
                                    ->maxLength(255),
                                Select::make('category_ids')
                                    ->label('Интересующие рубрики')
                                    ->multiple()
                                    ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),
                                TextInput::make('token')
                                    ->maxLength(255)
                                    ->helperText('Оставьте пустым при создании, если хотите использовать токен из model boot hook.'),
                            ]),
                    ]),
                Section::make('Статус и атрибуция')
                    ->icon(AdminUiIconResolver::section('Статус и атрибуция'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('confirmed')
                                    ->default(false),
                                DateTimePicker::make('confirmed_at'),
                                DateTimePicker::make('unsubscribed_at'),
                                TextInput::make('ip_address'),
                                TextInput::make('country_code')
                                    ->maxLength(2),
                                TextInput::make('timezone')
                                    ->maxLength(255),
                                TextInput::make('locale')
                                    ->maxLength(10),
                            ]),
                    ]),
            ]);
    }
}
