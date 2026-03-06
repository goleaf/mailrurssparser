<?php

use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('publishes selected articles from the bulk action', function () {
    $this->actingAs(User::factory()->create());

    $articles = Article::withoutSyncingToSearch(function () {
        return Article::factory()
            ->count(2)
            ->create(['status' => 'draft']);
    });

    Article::withoutSyncingToSearch(function () use ($articles): void {
        Livewire::test(ListArticles::class)
            ->selectTableRecords($articles->pluck('id')->all())
            ->callAction(TestAction::make('publishSelected')->table()->bulk());
    });

    $articles->each(function (Article $article): void {
        expect($article->refresh()->status)->toBe('published');
    });
});
