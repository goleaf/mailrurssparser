<?php

namespace App\Filament\Resources\Metrics;

use App\Filament\Resources\Metrics\Pages\CreateMetric;
use App\Filament\Resources\Metrics\Pages\EditMetric;
use App\Filament\Resources\Metrics\Pages\ListMetrics;
use App\Filament\Resources\Metrics\Pages\ViewMetric;
use App\Filament\Resources\Metrics\Schemas\MetricForm;
use App\Filament\Resources\Metrics\Schemas\MetricInfolist;
use App\Filament\Resources\Metrics\Tables\MetricsTable;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\Metric;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MetricResource extends Resource
{
    protected static ?string $model = Metric::class;

    protected static ?string $modelLabel = 'метрика';

    protected static ?string $pluralModelLabel = 'метрики';

    protected static ?string $navigationLabel = 'Метрики';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Analytics;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forAdminIndex();
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
