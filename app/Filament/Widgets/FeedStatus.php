<?php

namespace App\Filament\Widgets;

use App\Models\RssFeed;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class FeedStatus extends TableWidget
{
    protected static ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => RssFeed::query())
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('last_parsed_at')
                    ->since(),
                TextColumn::make('last_run_new_count'),
                TextColumn::make('last_error')
                    ->limit(30)
                    ->color(fn (?string $state): string => filled($state) ? 'danger' : 'gray'),
            ])
            ->paginated(false);
    }
}
