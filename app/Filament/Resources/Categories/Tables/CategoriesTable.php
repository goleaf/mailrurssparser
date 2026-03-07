<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\ColumnManagerLayout;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icon')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Icon'),
                TextColumn::make('name')
                    ->searchable()
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('rss_key')
                    ->toggleable()
                    ->badge(),
                ColorColumn::make('color')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('order')
                    ->toggleable()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('articles_count_cache')
                    ->numeric()
                    ->toggleable()
                    ->label('Articles'),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->reorderableColumns()
            ->columnManagerLayout(ColumnManagerLayout::Modal)
            ->columnManagerColumns(2)
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->columnManagerTriggerAction(
                fn (Action $action): Action => $action
                    ->button()
                    ->label('Вид таблицы')
                    ->icon(Heroicon::AdjustmentsHorizontal)
                    ->modalHeading('Вид таблицы рубрик'),
            )
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
