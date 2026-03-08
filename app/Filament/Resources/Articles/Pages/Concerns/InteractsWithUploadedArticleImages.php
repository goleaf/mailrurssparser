<?php

namespace App\Filament\Resources\Articles\Pages\Concerns;

use App\Services\StorageDisk;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait InteractsWithUploadedArticleImages
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareUploadedImageData(array $data, ?string $existingImageUrl = null): array
    {
        $uploadedImage = $data['uploaded_image'] ?? null;

        unset($data['uploaded_image']);

        if (blank($uploadedImage)) {
            return $data;
        }

        if (filled($existingImageUrl)) {
            $this->deleteManagedArticleImage($existingImageUrl);
        }

        $data['image_url'] = Storage::disk(StorageDisk::Public)->url((string) $uploadedImage);

        return $data;
    }

    protected function deleteManagedArticleImage(string $imageUrl): void
    {
        $disk = Storage::disk(StorageDisk::Public);
        $publicPrefix = rtrim($disk->url(''), '/');

        if (! Str::startsWith($imageUrl, $publicPrefix.'/')) {
            return;
        }

        $path = ltrim(Str::after($imageUrl, $publicPrefix), '/');

        if ($path === '') {
            return;
        }

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
