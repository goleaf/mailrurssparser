<?php

namespace App\Filament\Resources\RssParseLogs\Pages;

use App\Filament\Resources\RssParseLogs\RssParseLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRssParseLogs extends ListRecords
{
    protected static string $resource = RssParseLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
