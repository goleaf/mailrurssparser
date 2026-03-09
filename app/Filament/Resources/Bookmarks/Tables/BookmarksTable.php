<?php

namespace App\Filament\Resources\Bookmarks\Tables;

use App\Filament\Resources\Bookmarks\BookmarkResource;
use App\Models\Bookmark;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookmarksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('article.title')
                    ->label('Статья')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('article.category.name')
                    ->label('Рубрика')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('article.subCategory.name')
                    ->label('Подкатегория')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('session_hash')
                    ->searchable()
                    ->sortable()
                    ->limit(18),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('viewRecord')
                    ->label('Просмотр')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Bookmark $record): string => BookmarkResource::getUrl('view', ['record' => $record])),
                Action::make('editRecord')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Bookmark $record): string => BookmarkResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
