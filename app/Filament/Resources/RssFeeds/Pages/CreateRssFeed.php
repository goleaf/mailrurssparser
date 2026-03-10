<?php

namespace App\Filament\Resources\RssFeeds\Pages;

use App\Filament\Resources\RssFeeds\Pages\Concerns\InteractsWithRssFeedExtraSettings;
use App\Filament\Resources\RssFeeds\RssFeedResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRssFeed extends CreateRecord
{
    use InteractsWithRssFeedExtraSettings;

    protected static string $resource = RssFeedResource::class;

    public function getSubheading(): ?string
    {
        return 'Подключите новый источник, задайте ритм обработки и при необходимости добавьте локальные parser-override правила.';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareExtraSettingsData($data);
    }
}
