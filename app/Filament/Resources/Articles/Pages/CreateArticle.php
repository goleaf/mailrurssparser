<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\Concerns\InteractsWithUploadedArticleImages;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    use InteractsWithUploadedArticleImages;

    protected static string $resource = ArticleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareUploadedImageData($data);
    }
}
