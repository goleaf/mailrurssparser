<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ChartsPage extends Page
{
    protected static ?string $navigationLabel = 'Графики и аналитика';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Analytics;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Графики и аналитика';

    protected static ?string $slug = 'charts';

    protected string $view = 'filament.pages.charts';
}
