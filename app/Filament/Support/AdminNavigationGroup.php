<?php

namespace App\Filament\Support;

use Filament\Support\Contracts\HasLabel;

enum AdminNavigationGroup implements HasLabel
{
    case Editorial;

    case Taxonomy;

    case Ingestion;

    case Audience;

    case Analytics;

    public function getLabel(): string
    {
        return match ($this) {
            self::Editorial => 'Редакция',
            self::Taxonomy => 'Рубрики и теги',
            self::Ingestion => 'RSS и парсинг',
            self::Audience => 'Аудитория',
            self::Analytics => 'Метрики',
        };
    }
}
