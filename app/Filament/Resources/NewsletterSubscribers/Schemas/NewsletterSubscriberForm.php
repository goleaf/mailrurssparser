<?php

namespace App\Filament\Resources\NewsletterSubscribers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class NewsletterSubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('name'),
                Textarea::make('category_ids')
                    ->columnSpanFull(),
                TextInput::make('token')
                    ->required(),
                Toggle::make('confirmed')
                    ->required(),
                DateTimePicker::make('confirmed_at'),
                DateTimePicker::make('unsubscribed_at'),
                TextInput::make('ip_address'),
                TextInput::make('country_code'),
                TextInput::make('timezone'),
                TextInput::make('locale'),
            ]);
    }
}
