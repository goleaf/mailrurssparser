<?php

namespace App\Filament\Resources\Metrics\Tables;

use App\Filament\Resources\Metrics\MetricResource;
use App\Models\Metric;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MetricsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('bucket_start', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('measurable_type')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Глобальная'),
                TextColumn::make('measurable_id')
                    ->numeric()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('value')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bucket_start')
                    ->dateTime()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bucket_date')
                    ->date()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fingerprint')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(18),
            ])
            ->filters([
                SelectFilter::make('name')
                    ->options(fn (): array => Metric::query()->distinct()->orderBy('name')->pluck('name', 'name')->all()),
                SelectFilter::make('category')
                    ->options(fn (): array => Metric::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
            ])
            ->recordActions([
                Action::make('viewRecord')
                    ->label('Просмотр')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Metric $record): string => MetricResource::getUrl('view', ['record' => $record])),
                Action::make('editRecord')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Metric $record): string => MetricResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
