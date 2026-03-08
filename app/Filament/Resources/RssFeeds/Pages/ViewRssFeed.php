<?php

namespace App\Filament\Resources\RssFeeds\Pages;

use App\Filament\Resources\RssFeeds\RssFeedResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRssFeed extends ViewRecord
{
    protected static string $resource = RssFeedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
