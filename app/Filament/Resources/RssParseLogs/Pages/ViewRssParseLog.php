<?php

namespace App\Filament\Resources\RssParseLogs\Pages;

use App\Filament\Resources\RssParseLogs\RssParseLogResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRssParseLog extends ViewRecord
{
    protected static string $resource = RssParseLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
