<?php

namespace App\Filament\Resources\SubCategories\Tables;

use App\Filament\Resources\SubCategories\SubCategoryResource;
use App\Models\SubCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name')
                    ->label('Рубрика')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(['name', 'slug', 'description'])
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('articles_count')
                    ->label('Статей')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('search_fields')
                    ->label('Поиск по полям')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название'),
                        TextInput::make('slug')
                            ->label('Slug'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['name'] ?? null),
                                fn (Builder $query): Builder => $query->where('name', 'like', '%'.$data['name'].'%'),
                            )
                            ->when(
                                filled($data['slug'] ?? null),
                                fn (Builder $query): Builder => $query->where('slug', 'like', '%'.$data['slug'].'%'),
                            );
                    }),
                TernaryFilter::make('is_active'),
            ])
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
            ->toolbarActions([]);
    }
}
