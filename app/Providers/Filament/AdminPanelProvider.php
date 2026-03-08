<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Support\AdminNavigationGroup;
use App\Filament\Widgets\FeedStatusWidget;
use App\Filament\Widgets\LatestArticlesWidget;
use App\Filament\Widgets\ParseLogsWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\ViewsChartWidget;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->topbar(false)
            ->login()
            ->profile(isSimple: false)
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName((string) config('app.name').' Admin'),
                EmailAuthentication::make(),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->resources([
                ArticleResource::make('moderation')
                    ->navigationLabel('Очередь модерации')
                    ->navigationGroup(AdminNavigationGroup::Editorial)
                    ->navigationSort(2)
                    ->pluralModelLabel('материалы на модерации')
                    ->slug('moderation-queue')
                    ->status('pending'),
                ArticleResource::make('published')
                    ->navigationLabel('Опубликованные статьи')
                    ->navigationGroup(AdminNavigationGroup::Editorial)
                    ->navigationSort(3)
                    ->pluralModelLabel('опубликованные статьи')
                    ->slug('published-articles')
                    ->status('published'),
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                StatsOverviewWidget::class,
                ViewsChartWidget::class,
                LatestArticlesWidget::class,
                FeedStatusWidget::class,
                ParseLogsWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
