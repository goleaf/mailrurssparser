<?php

namespace App\Filament\Resources\Tags\Tables;

use App\Filament\Resources\Tags\TagResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(['name', 'slug', 'description'])
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->placeholder('—'),
                ColorColumn::make('color')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('articles_count')
                    ->label('Статей')
                    ->numeric()
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('usage_count')
                    ->numeric()
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_trending')
                    ->toggleable()
                    ->boolean()
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_featured')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
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
                TernaryFilter::make('is_trending')
                    ->label('В тренде'),
                TernaryFilter::make('is_featured')
                    ->label('Избранный'),
            ])
            ->columnManager(false)
            ->recordActions([
                Action::make('editRecord')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn ($record): string => TagResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
