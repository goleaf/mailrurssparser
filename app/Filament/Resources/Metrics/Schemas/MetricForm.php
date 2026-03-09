<?php

namespace App\Filament\Resources\Metrics\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MetricForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('category'),
                TextInput::make('measurable_type'),
                TextInput::make('measurable_id')
                    ->numeric(),
                DateTimePicker::make('bucket_start')
                    ->required(),
                DatePicker::make('bucket_date')
                    ->required(),
                TextInput::make('fingerprint')
                    ->required(),
                TextInput::make('value')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
