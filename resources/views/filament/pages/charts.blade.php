<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="xl:col-span-2">
            @livewire(\App\Filament\Widgets\Charts\DailyViewsChartWidget::class)
        </div>

        <div class="xl:col-span-2">
            @livewire(\App\Filament\Widgets\Charts\ArticlePublicationTrendWidget::class)
        </div>

        @livewire(\App\Filament\Widgets\Charts\RssFeedParseActivityWidget::class)
        @livewire(\App\Filament\Widgets\Charts\ParseSuccessRateRadialWidget::class)
        @livewire(\App\Filament\Widgets\Charts\CategoryBreakdownChartWidget::class)
        @livewire(\App\Filament\Widgets\Charts\TopTagsChartWidget::class)

        <div class="xl:col-span-2">
            @livewire(\App\Filament\Widgets\Charts\NewsletterSubscriberGrowthWidget::class)
        </div>

        <div class="xl:col-span-2">
            @livewire(\App\Filament\Widgets\Charts\SocialShareClicksWidget::class)
        </div>
    </div>
</x-filament-panels::page>
