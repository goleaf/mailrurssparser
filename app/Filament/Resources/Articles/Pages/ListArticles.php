<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Services\RssParserService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('importFromUrl')
                ->label('Import from URL')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->modalHeading('Импорт из RSS URL')
                ->modalDescription('Будет импортирован первый материал из RSS-ленты и создан черновик статьи.')
                ->modalSubmitActionLabel('Импортировать')
                ->schema([
                    TextInput::make('url')
                        ->label('RSS URL')
                        ->url()
                        ->required()
                        ->placeholder('https://news.mail.ru/rss/main/'),
                ])
                ->action(function (array $data, RssParserService $parser): void {
                    try {
                        $article = $parser->importArticleFromUrl($data['url']);

                        Notification::make()
                            ->title('Черновик создан')
                            ->body($article->title)
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Не удалось импортировать материал')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
