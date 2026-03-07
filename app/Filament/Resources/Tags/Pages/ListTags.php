<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Filament\Resources\Tags\TagResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListTags extends ListRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('recalculateCounts')
                ->label('Recalculate Counts')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    DB::statement('
                        UPDATE tags
                        SET usage_count = (
                            SELECT COUNT(*)
                            FROM article_tag
                            WHERE article_tag.tag_id = tags.id
                        )
                    ');

                    Notification::make()
                        ->title('Tag counts recalculated')
                        ->success()
                        ->send();
                }),
        ];
    }
}
