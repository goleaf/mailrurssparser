<?php

namespace App\Filament\Resources\Metrics\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MetricInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('category')
                    ->placeholder('-'),
                TextEntry::make('measurable_type')
                    ->placeholder('-'),
                TextEntry::make('measurable_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('bucket_start')
                    ->dateTime(),
                TextEntry::make('bucket_date')
                    ->date(),
                TextEntry::make('fingerprint'),
                TextEntry::make('value')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
