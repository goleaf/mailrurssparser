<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icon')
                    ->label('Icon'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rss_key')
                    ->badge(),
                ColorColumn::make('color'),
                TextColumn::make('order')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->sortable(),
                TextColumn::make('articles_count_cache')
                    ->numeric()
                    ->label('Articles'),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering): Action => $action
                    ->label($isReordering ? 'Завершить сортировку' : 'Изменить порядок')
                    ->icon('heroicon-o-arrows-up-down'),
            )
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Category $record): bool => $record->articles_count > 0),
            ])
            ->toolbarActions([]);
    }
}
