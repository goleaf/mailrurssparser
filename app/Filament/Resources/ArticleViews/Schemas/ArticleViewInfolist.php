<?php

namespace App\Filament\Resources\ArticleViews\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ArticleViewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Просмотр материала')
                    ->description('Основная карточка просмотра с таймингом и типом визита.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('article.title')
                            ->label('Статья')
                            ->icon(Heroicon::OutlinedNewspaper)
                            ->weight('bold'),
                        TextEntry::make('viewed_at')
                            ->label('Зафиксировано')
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime('d.m.Y H:i:s'),
                        TextEntry::make('device_type')
                            ->label('Устройство')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'desktop' => 'Компьютер',
                                'mobile' => 'Телефон',
                                'tablet' => 'Планшет',
                                default => 'Не указано',
                            }),
                        TextEntry::make('referrer_type')
                            ->label('Источник перехода')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'direct' => 'Прямой',
                                'search' => 'Поиск',
                                'social' => 'Соцсети',
                                'internal' => 'Внутренний',
                                default => 'Не указано',
                            }),
                    ]),
                Section::make('Сессия и устройство')
                    ->description('Технические идентификаторы просмотра и браузерный след.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ip_address')
                            ->label('IP-адрес')
                            ->placeholder('—'),
                        TextEntry::make('country_code')
                            ->label('Страна')
                            ->placeholder('—'),
                        TextEntry::make('session_id')
                            ->label('Session ID')
                            ->placeholder('—'),
                        TextEntry::make('session_hash')
                            ->label('Хэш сессии')
                            ->placeholder('—'),
                        TextEntry::make('ip_hash')
                            ->label('Хэш IP')
                            ->placeholder('—'),
                        TextEntry::make('user_agent')
                            ->label('User-Agent')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->wrap(),
                    ]),
                Section::make('Маршрут перехода')
                    ->description('Откуда пришёл читатель и какие региональные признаки определены.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('referrer_domain')
                            ->label('Домен-источник')
                            ->placeholder('—'),
                        TextEntry::make('referer')
                            ->label('Полный referer')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->wrap(),
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
