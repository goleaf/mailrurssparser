<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Support\AdminNavigationGroup;
use App\Filament\Support\AdminUiIconResolver;
use App\Filament\Support\AdminUiLabelResolver;
use App\Filament\Widgets\Charts\DailyViewsChartWidget;
use App\Filament\Widgets\Charts\RssFeedParseActivityWidget;
use App\Filament\Widgets\FeedStatusWidget;
use App\Filament\Widgets\LatestArticlesWidget;
use App\Filament\Widgets\ParseLogsWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\ViewsChartWidget;
use Awcodes\StickyHeader\StickyHeaderPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Enums\ThemeMode;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Colors\Color;
use Filament\Support\Components\ComponentManager;
use Filament\Support\Components\Contracts\ScopedComponentManager;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        self::configureAdminUiComponents();

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
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
                DailyViewsChartWidget::class,
                RssFeedParseActivityWidget::class,
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
            ->plugins([
                FilamentShieldPlugin::make()
                    ->gridColumns(['default' => 1, 'sm' => 2, 'lg' => 3])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->resourceCheckboxListColumns(['default' => 1, 'sm' => 2])
                    ->navigationGroup('Shield')
                    ->navigationIcon(Heroicon::OutlinedShieldCheck),
                StickyHeaderPlugin::make(),
                FilamentApexChartsPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    protected static function configureAdminUiComponents(): void
    {
        self::configureComponent(Action::class, fn (Action $component) => self::configureActionLabel($component));
        self::configureComponent(TextInput::class, function (TextInput $component): void {
            self::configureFieldLabel($component);
            self::configureAffixIcon($component);
        });
        self::configureComponent(Select::class, function (Select $component): void {
            self::configureFieldLabel($component);
            self::configureAffixIcon($component);
        });
        self::configureComponent(DatePicker::class, function (DatePicker $component): void {
            self::configureFieldLabel($component);
            self::configureAffixIcon($component);
        });
        self::configureComponent(DateTimePicker::class, function (DateTimePicker $component): void {
            self::configureFieldLabel($component);
            self::configureAffixIcon($component);
        });
        self::configureComponent(ColorPicker::class, function (ColorPicker $component): void {
            self::configureFieldLabel($component);
            self::configureAffixIcon($component);
        });
        self::configureComponent(TagsInput::class, function (TagsInput $component): void {
            self::configureFieldLabel($component);
            self::configureAffixIcon($component);
        });
        self::configureComponent(Textarea::class, function (Textarea $component): void {
            self::configureFieldLabel($component);
            self::configureHintIcon($component);
        });
        self::configureComponent(RichEditor::class, function (RichEditor $component): void {
            self::configureFieldLabel($component);
            self::configureHintIcon($component);
        });
        self::configureComponent(FileUpload::class, function (FileUpload $component): void {
            self::configureFieldLabel($component);
            self::configureHintIcon($component);
        });
        self::configureComponent(Toggle::class, function (Toggle $component): void {
            self::configureFieldLabel($component);
            self::configureToggleIcons($component);
        });
        self::configureComponent(Placeholder::class, function (Placeholder $component): void {
            self::configurePlaceholderLabel($component);
            self::configureHintIcon($component);
        });
        self::configureComponent(TextEntry::class, function (TextEntry $component): void {
            self::configureTextEntryLabel($component);
            self::configureTextEntryIcon($component);
        });
        self::configureComponent(TextColumn::class, fn (TextColumn $component) => self::configureColumnLabel($component));
        self::configureComponent(IconColumn::class, fn (IconColumn $component) => self::configureColumnLabel($component));
        self::configureComponent(ToggleColumn::class, fn (ToggleColumn $component) => self::configureColumnLabel($component));
        self::configureComponent(ColorColumn::class, fn (ColorColumn $component) => self::configureColumnLabel($component));
        self::configureComponent(Filter::class, fn (Filter $component) => self::configureFilterLabel($component));
        self::configureComponent(SelectFilter::class, fn (SelectFilter $component) => self::configureFilterLabel($component));
        self::configureComponent(TernaryFilter::class, fn (TernaryFilter $component) => self::configureFilterLabel($component));
        self::configureComponent(TrashedFilter::class, fn (TrashedFilter $component) => self::configureFilterLabel($component));
        self::configureComponent(Section::class, fn (Section $component) => self::configureSectionIcon($component));
        self::configureComponent(Tab::class, fn (Tab $component) => self::configureTabIcon($component));
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

    private static function configureActionLabel(Action $component): void
    {
        $component->translateLabel();
    }

    private static function configureFieldLabel(Field $component): void
    {
        $component->translateLabel();

        if ($component->hasCustomLabel()) {
            return;
        }

        $label = AdminUiLabelResolver::field($component->getName());

        if (blank($label)) {
            return;
        }

        $component->label($label);
    }

    private static function configurePlaceholderLabel(Placeholder $component): void
    {
        $component->translateLabel();

        if ($component->hasCustomLabel()) {
            return;
        }

        $label = AdminUiLabelResolver::field($component->getName());

        if (blank($label)) {
            return;
        }

        $component->label($label);
    }

    private static function configureTextEntryLabel(TextEntry $component): void
    {
        $currentLabel = $component->getLabel();

        $component->translateLabel();

        $defaultLabel = self::defaultSchemaLabel($component->getName());
        $label = AdminUiLabelResolver::field($component->getName());

        if (blank($label) || (string) $currentLabel !== $defaultLabel) {
            return;
        }

        $component->label($label);
    }

    private static function configureColumnLabel(Column $component): void
    {
        $currentLabel = $component->getLabel();

        $component->translateLabel();

        $defaultLabel = self::defaultColumnLabel($component->getName());
        $label = AdminUiLabelResolver::field($component->getName());

        if (blank($label) || (string) $currentLabel !== $defaultLabel) {
            return;
        }

        $component->label($label);
    }

    private static function configureFilterLabel(BaseFilter $component): void
    {
        $currentLabel = $component->getLabel();

        $component->translateLabel();

        $defaultLabel = self::defaultFilterLabel($component->getName());
        $label = AdminUiLabelResolver::field($component->getName());

        if (blank($label) || (string) $currentLabel !== $defaultLabel) {
            return;
        }

        $component->label($label);
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

    private static function defaultSchemaLabel(string $name): string
    {
        return (string) str($name)
            ->afterLast('.')
            ->kebab()
            ->replace(['-', '_'], ' ')
            ->ucfirst();
    }

    private static function defaultColumnLabel(string $name): string
    {
        return (string) str($name)
            ->beforeLast('.')
            ->afterLast('.')
            ->kebab()
            ->replace(['-', '_'], ' ')
            ->ucfirst();
    }

    private static function defaultFilterLabel(string $name): string
    {
        return (string) str($name)
            ->before('.')
            ->kebab()
            ->replace(['-', '_'], ' ')
            ->ucfirst();
    }

    private static function configureComponent(string $componentClass, \Closure $configuration): void
    {
        app(ComponentManager::class)->configureUsing($componentClass, $configuration);

        if (app()->resolved(ScopedComponentManager::class)) {
            app(ScopedComponentManager::class)->configureUsing($componentClass, $configuration);
        }
    }
}
