<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icon')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Иконка'),
                TextColumn::make('name')
                    ->searchable(['name', 'slug', 'rss_key'])
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
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
                ToggleColumn::make('show_in_menu')
                    ->label('В меню')
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('sub_categories_count')
                    ->label('Подкатегорий')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rss_feeds_count')
                    ->label('Лент')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('articles_count_cache')
                    ->numeric()
                    ->toggleable()
                    ->label('Статей')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('search_fields')
                    ->label('Поиск по полям')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название'),
                        TextInput::make('rss_key')
                            ->label('RSS key'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['name'] ?? null),
                                fn (Builder $query): Builder => $query->where('name', 'like', '%'.$data['name'].'%'),
                            )
                            ->when(
                                filled($data['rss_key'] ?? null),
                                fn (Builder $query): Builder => $query->where('rss_key', 'like', '%'.$data['rss_key'].'%'),
                            );
                    }),
                TernaryFilter::make('is_active'),
                TernaryFilter::make('show_in_menu')
                    ->label('Показывать в меню'),
            ])
            ->defaultSort('order')
            ->columnManager(false)
            ->reorderable('order')
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering): Action => $action
                    ->label($isReordering ? 'Завершить сортировку' : 'Изменить порядок')
                    ->icon(Heroicon::OutlinedArrowsUpDown),
            )
            ->recordActions([
                Action::make('editRecord')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn ($record): string => CategoryResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
