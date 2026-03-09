<?php

namespace App\Filament\Resources\ArticleViews\Pages;

use App\Filament\Resources\ArticleViews\ArticleViewResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewArticleView extends ViewRecord
{
    protected static string $resource = ArticleViewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
