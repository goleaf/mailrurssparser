<?php

namespace App\Filament\Resources\RssParseLogs\Schemas;

use App\Filament\Support\AdminUiIconResolver;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RssParseLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Запуск и источник')
                    ->icon(AdminUiIconResolver::section('Запуск и источник'))
                    ->columnSpanFull()
                    ->description('Где, когда и каким способом был запущен проход RSS-парсера.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('rssFeed.title')
                            ->label('Лента')
                            ->icon(Heroicon::OutlinedRss)
                            ->weight('bold'),
                        TextEntry::make('rssFeed.category.name')
                            ->label('Рубрика')
                            ->badge()
                            ->icon(Heroicon::OutlinedFolder)
                            ->placeholder('Без рубрики'),
                        TextEntry::make('triggered_by')
                            ->label('Источник запуска')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'scheduler' => 'Планировщик',
                                'manual' => 'Вручную',
                                'api' => 'API',
                                'filament' => 'Filament',
                                default => 'Неизвестно',
                            }),
                        IconEntry::make('success')
                            ->label('Успешно')
                            ->boolean(),
                        TextEntry::make('started_at')
                            ->label('Старт')
                            ->dateTime('d.m.Y H:i:s'),
                        TextEntry::make('finished_at')
                            ->label('Финиш')
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('—'),
                        TextEntry::make('duration_ms')
                            ->label('Длительность')
                            ->placeholder('—')
                            ->formatStateUsing(fn (?int $state): string => filled($state) ? number_format($state).' ms' : '—'),
                    ]),
                Section::make('Итог обработки')
                    ->icon(AdminUiIconResolver::section('Итог обработки'))
                    ->columnSpanFull()
                    ->description('Сводка по количеству импортированных, пропущенных и проблемных элементов.')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('new_count')
                            ->label('Новые')
                            ->badge()
                            ->color('success')
                            ->numeric(),
                        TextEntry::make('skip_count')
                            ->label('Пропущено')
                            ->badge()
                            ->color('gray')
                            ->numeric(),
                        TextEntry::make('error_count')
                            ->label('Ошибки')
                            ->badge()
                            ->color('danger')
                            ->numeric(),
                        TextEntry::make('total_items')
                            ->label('Всего элементов')
                            ->badge()
                            ->color('primary')
                            ->numeric(),
                    ]),
                Section::make('Ошибки и диагностика')
                    ->icon(AdminUiIconResolver::section('Ошибки и диагностика'))
                    ->columnSpanFull()
                    ->description('Подробности сбоя и служебные отметки для разбора проблемных импортов.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('Критическая ошибка')
                            ->placeholder('Без критических ошибок')
                            ->columnSpanFull()
                            ->wrap(),
                        TextEntry::make('item_errors')
                            ->label('Ошибки элементов')
                            ->placeholder('Ошибок по отдельным элементам нет')
                            ->columnSpanFull()
                            ->bulleted()
                            ->listWithLineBreaks(),
                        TextEntry::make('created_at')
                            ->label('Создано')
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label('Обновлено')
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
