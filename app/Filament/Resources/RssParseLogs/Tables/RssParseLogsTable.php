<?php

namespace App\Filament\Resources\RssParseLogs\Tables;

use App\Filament\Resources\RssParseLogs\RssParseLogResource;
use App\Models\RssParseLog;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RssParseLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('rssFeed.title')
                    ->label('Лента')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rssFeed.category.name')
                    ->label('Рубрика')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('duration_ms')
                    ->label('Длительность')
                    ->suffix(' ms')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('new_count')
                    ->label('Новые')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('skip_count')
                    ->label('Пропущено')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('error_count')
                    ->label('Ошибки')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                IconColumn::make('success')
                    ->boolean()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('triggered_by')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('error_message')
                    ->limit(40)
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('rssFeed')
                    ->relationship('rssFeed', 'title')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('triggered_by')
                    ->options([
                        'scheduler' => 'Планировщик',
                        'manual' => 'Вручную',
                        'api' => 'API',
                        'filament' => 'Filament',
                    ]),
                TernaryFilter::make('success')
                    ->label('Успешный запуск'),
            ])
            ->recordActions([
                Action::make('viewRecord')
                    ->label('Просмотр')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (RssParseLog $record): string => RssParseLogResource::getUrl('view', ['record' => $record])),
                Action::make('editRecord')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (RssParseLog $record): string => RssParseLogResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
