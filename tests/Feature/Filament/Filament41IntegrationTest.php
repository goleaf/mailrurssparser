<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\RssFeeds\Pages\CreateRssFeed;
use App\Models\Article;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Panel;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel((new AdminPanelProvider(app()))->panel(new Panel));
    $this->actingAs(User::factory()->create());
});

function filament41PageField(string $pageClass, string $field): Field
{
    $fields = Livewire::test($pageClass)
        ->instance()
        ->getSchema('form')
        ->getFlatFields(withHidden: true);

    $component = $fields[$field]
        ?? collect($fields)
            ->first(fn (Field $component, string $key): bool => $key === $field || str_ends_with($key, ".{$field}"));

    expect($component)->toBeInstanceOf(Field::class);

    return $component;
}

it('disables the filament topbar for the admin panel', function () {
    expect(Filament::getCurrentPanel()->hasTopbar())->toBeFalse();
});

it('configures the article rich editor with grid and text color tools', function () {
    $richEditor = filament41PageField(CreateArticle::class, 'full_description');
    $richContentAttribute = (new Article(['full_description' => '<p>Body</p>']))->getRichContentAttribute('full_description');

    expect($richEditor)->toBeInstanceOf(RichEditor::class)
        ->and($richEditor->hasToolbarButton('grid'))->toBeTrue()
        ->and($richEditor->hasToolbarButton('textColor'))->toBeTrue()
        ->and($richEditor->hasToolbarButton('table'))->toBeTrue()
        ->and($richEditor->hasToolbarButton('attachFiles'))->toBeTrue()
        ->and($richEditor->hasCustomTextColors())->toBeTrue()
        ->and($richEditor->getTextColors())->toHaveKeys(['mail-blue', 'urgent-red', 'market-green'])
        ->and($richContentAttribute?->getTextColors())->toHaveKeys(['mail-blue', 'urgent-red', 'market-green'])
        ->and($richContentAttribute?->hasCustomTextColors())->toBeTrue();
});

it('uses a compact table repeater for rss feed overrides', function () {
    $repeater = filament41PageField(CreateRssFeed::class, 'extra_settings_rows');

    expect($repeater)->toBeInstanceOf(Repeater::class)
        ->and($repeater->isCompact())->toBeTrue()
        ->and($repeater->getTableColumns())->toHaveCount(2);
});
