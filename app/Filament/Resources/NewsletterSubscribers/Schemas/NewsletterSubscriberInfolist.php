<?php

namespace App\Filament\Resources\NewsletterSubscribers\Schemas;

use App\Filament\Support\AdminUiIconResolver;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class NewsletterSubscriberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Профиль подписчика')
                    ->icon(AdminUiIconResolver::section('Профиль подписчика'))
                    ->columnSpanFull()
                    ->description('Кто подписан и какие рубрики интересуют этого читателя.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('email')
                            ->label('Email')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->weight('bold'),
                        TextEntry::make('name')
                            ->label('Имя')
                            ->placeholder('—'),
                        TextEntry::make('category_ids')
                            ->label('Рубрики интереса')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('token')
                            ->label('Токен подписки')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make('Статус подписки')
                    ->icon(AdminUiIconResolver::section('Статус подписки'))
                    ->columnSpanFull()
                    ->description('Подтверждение подписки и жизненный цикл подписчика.')
                    ->columns(2)
                    ->schema([
                        IconEntry::make('confirmed')
                            ->label('Подтверждён')
                            ->boolean(),
                        TextEntry::make('confirmed_at')
                            ->label('Подтверждён в')
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('—'),
                        TextEntry::make('unsubscribed_at')
                            ->label('Отписался в')
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Создано')
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label('Обновлено')
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('—'),
                    ]),
                Section::make('География и атрибуция')
                    ->icon(AdminUiIconResolver::section('География и атрибуция'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ip_address')
                            ->label('IP-адрес')
                            ->placeholder('—'),
                        TextEntry::make('country_code')
                            ->label('Страна')
                            ->placeholder('—'),
                        TextEntry::make('timezone')
                            ->label('Часовой пояс')
                            ->placeholder('—'),
                        TextEntry::make('locale')
                            ->label('Локаль')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
