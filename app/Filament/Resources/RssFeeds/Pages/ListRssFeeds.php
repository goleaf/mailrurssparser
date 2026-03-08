<?php

namespace App\Filament\Resources\RssFeeds\Pages;

use App\Filament\Resources\RssFeeds\RssFeedResource;
use App\Models\RssParseLog;
use App\Services\RssParserService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListRssFeeds extends ListRecords
{
    protected static string $resource = RssFeedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('parseAllFeeds')
                ->label('Запустить все активные')
                ->action(function (RssParserService $parser): void {
                    $results = $parser->parseAllFeeds('filament');

                    $newCount = 0;
                    $skippedCount = 0;
                    $errorCount = 0;

                    foreach ($results as $result) {
                        $newCount += (int) ($result['new'] ?? 0);
                        $skippedCount += (int) ($result['skip'] ?? 0);

                        if (! empty($result['error'])) {
                            $errorCount++;
                        }
                    }

                    $body = "Новые: {$newCount}, Пропущено: {$skippedCount}";

                    if ($errorCount > 0) {
                        $body .= ", Ошибки: {$errorCount}";
                    }

                    $notification = Notification::make()
                        ->title($errorCount > 0 ? 'Парсинг завершён с ошибками' : 'Парсинг завершён')
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

    public function getFooter(): ?View
    {
        return view('filament.resources.rss-feeds.pages.parse-log-footer', [
            'logs' => RssParseLog::query()
                ->with('rssFeed')
                ->latest('started_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
