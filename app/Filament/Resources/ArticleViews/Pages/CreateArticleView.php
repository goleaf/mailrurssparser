<?php

namespace App\Filament\Resources\ArticleViews\Pages;

use App\Filament\Resources\ArticleViews\ArticleViewResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArticleView extends CreateRecord
{
    protected static string $resource = ArticleViewResource::class;
}
