<?php

namespace App\Filament\Resources\ArticleViews\Pages;

use App\Filament\Resources\ArticleViews\ArticleViewResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditArticleView extends EditRecord
{
    protected static string $resource = ArticleViewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
