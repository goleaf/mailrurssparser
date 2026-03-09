<?php

namespace App\Filament\Support;

use BackedEnum;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum AdminNavigationGroup implements HasIcon, HasLabel
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

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Editorial => Heroicon::OutlinedNewspaper,
            self::Taxonomy => Heroicon::OutlinedTag,
            self::Ingestion => Heroicon::OutlinedRss,
            self::Audience => Heroicon::OutlinedUsers,
            self::Analytics => Heroicon::OutlinedChartBarSquare,
        };
    }
}
