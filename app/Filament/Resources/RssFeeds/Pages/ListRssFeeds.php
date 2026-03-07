<?php

namespace App\Filament\Resources\RssFeeds\Pages;

use App\Filament\Resources\RssFeeds\RssFeedResource;
use App\Services\RssParserService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRssFeeds extends ListRecords
{
    protected static string $resource = RssFeedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('parseAllFeeds')
                ->label('Parse All Feeds')
                ->action(function (RssParserService $parser): void {
                    $results = $parser->parseAllFeeds();

                    $newCount = 0;
                    $skippedCount = 0;
                    $errorCount = 0;

                    foreach ($results as $result) {
                        $newCount += (int) ($result['new'] ?? 0);
                        $skippedCount += (int) ($result['skipped'] ?? 0);

                        if (! empty($result['error_message'])) {
                            $errorCount++;
                        }
                    }

                    $body = "New: {$newCount}, Skipped: {$skippedCount}";

                    if ($errorCount > 0) {
                        $body .= ", Errors: {$errorCount}";
                    }

                    $notification = Notification::make()
                        ->title($errorCount > 0 ? 'Parse Completed with Errors' : 'Parse Complete')
                        ->body($body);

                    if ($errorCount > 0) {
                        $notification->danger();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
        ];
    }
}
