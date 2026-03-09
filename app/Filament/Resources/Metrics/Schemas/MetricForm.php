<?php

namespace App\Filament\Resources\Metrics\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MetricForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Метрика')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required(),
                                TextInput::make('category'),
                                TextInput::make('measurable_type')
                                    ->placeholder('Например, App\\Models\\Article'),
                                TextInput::make('measurable_id')
                                    ->numeric(),
                                DateTimePicker::make('bucket_start')
                                    ->required(),
                                DatePicker::make('bucket_date')
                                    ->required(),
                                TextInput::make('value')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('fingerprint')
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
