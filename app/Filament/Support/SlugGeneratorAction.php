<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

class SlugGeneratorAction
{
    public static function make(
        string $sourceField = 'name',
        string $targetField = 'slug',
        string $name = 'generateSlug',
        string $label = 'Сгенерировать slug',
    ): Action {
        return Action::make($name)
            ->label($label)
            ->link()
            ->action(function (Get $schemaGet, Set $schemaSet) use ($sourceField, $targetField): void {
                $schemaSet($targetField, Str::slug((string) ($schemaGet($sourceField) ?? '')));
            });
    }
}
