<?php

namespace App\Filament\Resources\Articles\Pages\Concerns;

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

        $data['image_url'] = Storage::disk('public')->url((string) $uploadedImage);

        return $data;
    }

    protected function deleteManagedArticleImage(string $imageUrl): void
    {
        $disk = Storage::disk('public');
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
