<?php

namespace App\Filament\Resources\Metrics;

use App\Filament\Resources\Metrics\Pages\CreateMetric;
use App\Filament\Resources\Metrics\Pages\EditMetric;
use App\Filament\Resources\Metrics\Pages\ListMetrics;
use App\Filament\Resources\Metrics\Pages\ViewMetric;
use App\Filament\Resources\Metrics\Schemas\MetricForm;
use App\Filament\Resources\Metrics\Schemas\MetricInfolist;
use App\Filament\Resources\Metrics\Tables\MetricsTable;
use App\Models\Metric;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MetricResource extends Resource
{
    protected static ?string $model = Metric::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return MetricForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MetricInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MetricsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMetrics::route('/'),
            'create' => CreateMetric::route('/create'),
            'view' => ViewMetric::route('/{record}'),
            'edit' => EditMetric::route('/{record}/edit'),
        ];
    }
}
