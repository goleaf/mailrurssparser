<?php

namespace App\Filament\Widgets\Charts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class SocialShareClicksWidget extends ApexChartWidget
{
    protected static ?string $chartId = 'social-share-clicks-chart';

    protected static ?string $heading = 'Клики по соцсетям';

    protected static ?string $subheading = 'VK, Telegram, WhatsApp, Twitter';

    protected static ?int $sort = 14;

    protected static ?int $contentHeight = 320;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getOptions(): array
    {
        /** @var array<string, mixed> $payload */
        $payload = Cache::remember(
            'charts:social-share-clicks:placeholder',
            now()->addMinutes(15),
            fn (): array => [
                'chart' => [
                    'type' => 'bar',
                    'height' => 320,
                    'toolbar' => [
                        'show' => false,
                    ],
                ],
                'series' => [],
                'xaxis' => [
                    'categories' => [],
                ],
                'noData' => [
                    'text' => 'Источник данных не найден',
                ],
            ],
        );

        return $payload;
    }

    protected function getFooter(): ?HtmlString
    {
        return new HtmlString(
            '<p class="px-1 text-sm text-gray-500">Текущая схема увеличивает только <code>articles.shares_count</code> и не хранит клики по платформам отдельно.</p>',
        );
    }
}
