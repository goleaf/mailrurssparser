<?php

namespace App\Filament\Resources\SubCategories\Schemas;

use App\Filament\Support\AdminUiIconResolver;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SubCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Подкатегория')
                    ->icon(AdminUiIconResolver::section('Подкатегория'))
                    ->columnSpanFull()
                    ->description('Основные данные подрубрики и её позиция внутри категории.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('category.name')
                            ->label('Рубрика')
                            ->badge()
                            ->icon(Heroicon::OutlinedFolder)
                            ->placeholder('Без рубрики'),
                        TextEntry::make('name')
                            ->label('Название')
                            ->weight('bold'),
                        TextEntry::make('slug')
                            ->label('Slug'),
                        ColorEntry::make('color')
                            ->label('Цвет')
                            ->placeholder('—'),
                        TextEntry::make('icon')
                            ->label('Иконка')
                            ->placeholder('—'),
                        TextEntry::make('order')
                            ->label('Порядок')
                            ->numeric(),
                        TextEntry::make('description')
                            ->label('Описание')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->wrap(),
                    ]),
                Section::make('Публикация')
                    ->icon(AdminUiIconResolver::section('Публикация'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        IconEntry::make('is_active')
                            ->label('Активна')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label('Создана')
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label('Обновлена')
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
