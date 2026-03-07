<?php

namespace App\Filament\Resources\Tags\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerLayout;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Table;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->toggleable()
                    ->searchable(),
                ColorColumn::make('color')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('usage_count')
                    ->numeric()
                    ->toggleable()
                    ->sortable(),
                IconColumn::make('is_trending')
                    ->toggleable()
                    ->boolean(),
                IconColumn::make('is_featured')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->reorderableColumns()
            ->columnManagerLayout(ColumnManagerLayout::Modal)
            ->columnManagerColumns(2)
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->columnManagerTriggerAction(
                fn (Action $action): Action => $action
                    ->button()
                    ->label('Вид таблицы')
                    ->icon(Heroicon::AdjustmentsHorizontal)
                    ->modalHeading('Вид таблицы тегов'),
            )
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
