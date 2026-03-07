<?php

namespace App\Filament\Widgets;

use App\Models\RssParseLog;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ParseLogsWidget extends TableWidget
{
    protected static ?string $heading = 'История парсинга';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => RssParseLog::query()
                ->with('rssFeed')
                ->latest('started_at')
                ->limit(20))
            ->columns([
                TextColumn::make('rssFeed.title'),
                TextColumn::make('started_at')
                    ->dateTime(),
                TextColumn::make('new_count')
                    ->badge()
                    ->color('success'),
                TextColumn::make('skip_count'),
                TextColumn::make('error_count')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('duration_ms')
                    ->suffix(' ms'),
                IconColumn::make('success')
                    ->boolean(),
            ])
            ->paginated(false);
    }
}
