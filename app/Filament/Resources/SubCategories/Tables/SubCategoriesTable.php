<?php

namespace App\Filament\Resources\SubCategories\Tables;

use App\Filament\Resources\SubCategories\SubCategoryResource;
use App\Models\SubCategory;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SubCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(
                fn (Builder $query): Builder => $query->orderBy('order')->orderBy('name'),
            )
            ->columns([
                ColorColumn::make('color')
                    ->label('Цвет')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category.name')
                    ->label('Рубрика')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(['name', 'slug', 'description'])
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('articles_count')
                    ->label('Статей')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Рубрика')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active'),
            ])
            ->columnManager(false)
            ->recordActions([
                Action::make('viewRecord')
                    ->label('Просмотр')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (SubCategory $record): string => SubCategoryResource::getUrl('view', ['record' => $record])),
                Action::make('editRecord')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (SubCategory $record): string => SubCategoryResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activateSelected')
                        ->label('Активировать')
                        ->color('success')
                        ->action(function (Collection $records): void {
                            $records->each(function (SubCategory $record): void {
                                $record->update(['is_active' => true]);
                            });
                        }),
                    BulkAction::make('deactivateSelected')
                        ->label('Деактивировать')
                        ->color('gray')
                        ->action(function (Collection $records): void {
                            $records->each(function (SubCategory $record): void {
                                $record->update(['is_active' => false]);
                            });
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
