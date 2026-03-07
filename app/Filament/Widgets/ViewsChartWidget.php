<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class ViewsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Views Chart Widget';

    protected function getData(): array
    {
        return [
            //
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
