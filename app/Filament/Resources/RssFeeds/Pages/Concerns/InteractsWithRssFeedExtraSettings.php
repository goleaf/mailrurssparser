<?php

namespace App\Filament\Resources\RssFeeds\Pages\Concerns;

use App\Filament\Resources\RssFeeds\Schemas\RssFeedForm;

trait InteractsWithRssFeedExtraSettings
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillExtraSettingsRows(array $data): array
    {
        $extraSettings = $data['extra_settings'] ?? null;

        $data['extra_settings_rows'] = is_array($extraSettings)
            ? RssFeedForm::rowsFromExtraSettings($extraSettings)
            : [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareExtraSettingsData(array $data): array
    {
        $rows = $data['extra_settings_rows'] ?? [];

        $data['extra_settings'] = is_array($rows)
            ? RssFeedForm::extraSettingsFromRows($rows)
            : [];

        unset($data['extra_settings_rows']);

        return $data;
    }
}
