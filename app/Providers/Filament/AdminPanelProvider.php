<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Support\AdminNavigationGroup;
use App\Filament\Support\AdminUiIconResolver;
use App\Filament\Widgets\FeedStatusWidget;
use App\Filament\Widgets\LatestArticlesWidget;
use App\Filament\Widgets\ParseLogsWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\ViewsChartWidget;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    protected static bool $componentsConfigured = false;

    public function panel(Panel $panel): Panel
    {
        self::configureAdminUiComponents();

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->topbar(false)
            ->darkMode(false)
            ->defaultThemeMode(ThemeMode::Light)
            ->maxContentWidth(Width::Full)
            ->simplePageMaxContentWidth(Width::Full)
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
                    ->navigationIcon(Heroicon::OutlinedClipboardDocumentList)
                    ->navigationSort(2)
                    ->pluralModelLabel('материалы на модерации')
                    ->slug('moderation-queue')
                    ->status('pending'),
                ArticleResource::make('published')
                    ->navigationLabel('Опубликованные статьи')
                    ->navigationGroup(AdminNavigationGroup::Editorial)
                    ->navigationIcon(Heroicon::OutlinedCheckCircle)
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

    protected static function configureAdminUiComponents(): void
    {
        if (self::$componentsConfigured) {
            return;
        }

        self::$componentsConfigured = true;

        TextInput::configureUsing(fn (TextInput $component) => self::configureAffixIcon($component));
        Select::configureUsing(fn (Select $component) => self::configureAffixIcon($component));
        DatePicker::configureUsing(fn (DatePicker $component) => self::configureAffixIcon($component));
        DateTimePicker::configureUsing(fn (DateTimePicker $component) => self::configureAffixIcon($component));
        ColorPicker::configureUsing(fn (ColorPicker $component) => self::configureAffixIcon($component));
        TagsInput::configureUsing(fn (TagsInput $component) => self::configureAffixIcon($component));
        Textarea::configureUsing(fn (Textarea $component) => self::configureHintIcon($component));
        RichEditor::configureUsing(fn (RichEditor $component) => self::configureHintIcon($component));
        FileUpload::configureUsing(fn (FileUpload $component) => self::configureHintIcon($component));
        Placeholder::configureUsing(fn (Placeholder $component) => self::configureHintIcon($component));
        Toggle::configureUsing(fn (Toggle $component) => self::configureToggleIcons($component));
        TextEntry::configureUsing(fn (TextEntry $component) => self::configureTextEntryIcon($component));
        Section::configureUsing(fn (Section $component) => self::configureSectionIcon($component));
        Tab::configureUsing(fn (Tab $component) => self::configureTabIcon($component));
    }

    private static function configureAffixIcon(
        TextInput|Select|DatePicker|DateTimePicker|ColorPicker|TagsInput $component,
    ): void {
        if (filled($component->getPrefixIcon())) {
            return;
        }

        $icon = AdminUiIconResolver::field($component->getName());

        if (blank($icon)) {
            return;
        }

        $component
            ->prefixIcon($icon)
            ->prefixIconColor('gray');
    }

    private static function configureHintIcon(
        Textarea|RichEditor|FileUpload|Placeholder $component,
    ): void {
        if (filled($component->getHintIcon())) {
            return;
        }

        $icon = AdminUiIconResolver::field($component->getName());

        if (blank($icon)) {
            return;
        }

        $component->hintIcon($icon);
    }

    private static function configureToggleIcons(Toggle $component): void
    {
        if (filled($component->getOnIcon()) || filled($component->getOffIcon())) {
            return;
        }

        $icons = AdminUiIconResolver::toggle($component->getName());

        $component
            ->onIcon($icons['on'])
            ->offIcon($icons['off']);
    }

    private static function configureTextEntryIcon(TextEntry $component): void
    {
        if (filled($component->getIcon(null))) {
            return;
        }

        $icon = AdminUiIconResolver::field($component->getName());

        if (blank($icon)) {
            return;
        }

        $component
            ->icon($icon)
            ->iconColor('gray');
    }

    private static function configureSectionIcon(Section $component): void
    {
        $component->columnSpanFull();

        if (filled($component->getIcon())) {
            return;
        }

        $icon = AdminUiIconResolver::section($component->getHeading());

        if (blank($icon)) {
            return;
        }

        $component
            ->icon($icon)
            ->iconColor('primary');
    }

    private static function configureTabIcon(Tab $component): void
    {
        if (filled($component->getIcon())) {
            return;
        }

        $icon = AdminUiIconResolver::tab($component->getLabel());

        if (blank($icon)) {
            return;
        }

        $component->icon($icon);
    }
}
