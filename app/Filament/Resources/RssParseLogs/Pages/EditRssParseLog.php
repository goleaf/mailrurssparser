<?php

namespace App\Filament\Resources\RssParseLogs\Pages;

use App\Filament\Resources\RssParseLogs\RssParseLogResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRssParseLog extends EditRecord
{
    protected static string $resource = RssParseLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
