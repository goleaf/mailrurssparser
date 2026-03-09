<?php

namespace App\Filament\Resources\Metrics\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MetricInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Метрика')
                    ->description('Базовые атрибуты и назначение метрики.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Имя')
                            ->weight('bold'),
                        TextEntry::make('category')
                            ->label('Категория')
                            ->placeholder('—'),
                        TextEntry::make('measurable_type')
                            ->label('Тип сущности')
                            ->placeholder('—'),
                        TextEntry::make('measurable_id')
                            ->label('ID сущности')
                            ->numeric()
                            ->placeholder('—'),
                    ]),
                Section::make('Агрегация и значение')
                    ->description('Когда рассчитана метрика и какое значение попало в срез.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('bucket_start')
                            ->label('Начало бакета')
                            ->dateTime('d.m.Y H:i:s'),
                        TextEntry::make('bucket_date')
                            ->label('Дата бакета')
                            ->date('d.m.Y'),
                        TextEntry::make('value')
                            ->label('Значение')
                            ->badge()
                            ->color('primary')
                            ->numeric(),
                        TextEntry::make('fingerprint')
                            ->label('Отпечаток')
                            ->placeholder('—'),
                    ]),
                Section::make('Служебные данные')
                    ->columns(2)
                    ->schema([
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
