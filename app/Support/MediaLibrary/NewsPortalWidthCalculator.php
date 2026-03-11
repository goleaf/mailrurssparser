<?php

namespace App\Support\MediaLibrary;

use Illuminate\Support\Collection;
use Spatie\MediaLibrary\ResponsiveImages\WidthCalculator\WidthCalculator;
use Spatie\MediaLibrary\Support\ImageFactory;

class NewsPortalWidthCalculator implements WidthCalculator
{
    public function calculateWidthsFromFile(string $imagePath): Collection
    {
        $image = ImageFactory::load($imagePath);
        $fileSize = filesize($imagePath);

        return $this->calculateWidths(
            is_int($fileSize) ? $fileSize : 0,
            $image->getWidth(),
            $image->getHeight(),
        );
    }

    public function calculateWidths(int $fileSize, int $width, int $height): Collection
    {
        $configuredWidths = collect([2560, 1920, 1280, 960, 640, 320])
            ->filter(fn (int $targetWidth): bool => $targetWidth <= $width)
            ->values();

        if ($configuredWidths->isEmpty()) {
            return collect([$width]);
        }

        if (! $configuredWidths->contains($width)) {
            $configuredWidths->prepend($width);
        }

        return $configuredWidths
            ->unique()
            ->values();
    }
}
