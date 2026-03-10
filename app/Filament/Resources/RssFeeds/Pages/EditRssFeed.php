<?php

namespace App\Filament\Resources\RssFeeds\Pages;

use App\Filament\Resources\RssFeeds\Pages\Concerns\InteractsWithRssFeedExtraSettings;
use App\Filament\Resources\RssFeeds\RssFeedResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRssFeed extends EditRecord
{
    use InteractsWithRssFeedExtraSettings;

    protected static string $resource = RssFeedResource::class;

    public function getSubheading(): ?string
    {
        return 'Изменяйте расписание, публикационные флаги и parser-override настройки, не теряя контекст по последним запускам.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillExtraSettingsRows($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareExtraSettingsData($data);
    }
}
