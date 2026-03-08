<?php

namespace App\Services;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ArticleContentType: string implements HasColor, HasLabel
{
    use TranslatableBackedEnum;

    case News = 'news';
    case Article = 'article';
    case Opinion = 'opinion';
    case Analysis = 'analysis';
    case Interview = 'interview';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Analysis => 'info',
            self::Opinion => 'warning',
            self::Interview => 'success',
            self::Article => 'primary',
            self::News => 'gray',
        };
    }

    public function getLabel(): string|Htmlable|null
    {
        return $this->label('ru');
    }

    protected function translationGroup(): string
    {
        return 'article_content_type';
    }
}
