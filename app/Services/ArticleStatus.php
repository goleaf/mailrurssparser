<?php

namespace App\Services;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ArticleStatus: string implements HasColor, HasLabel
{
    use TranslatableBackedEnum;

    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Archived = 'archived';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'warning',
            self::Published => 'success',
            self::Archived => 'danger',
        };
    }

    public function getLabel(): string|Htmlable|null
    {
        return $this->label('ru');
    }

    protected function translationGroup(): string
    {
        return 'article_status';
    }
}
