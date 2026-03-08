<?php

use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\Article;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

it('creates a tag with the cms form fields', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateTag::class)
        ->fillForm([
            'name' => 'Политика',
            'slug' => 'politika',
            'color' => '#6B7280',
            'description' => 'Тег для политических новостей.',
            'is_trending' => true,
            'is_featured' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $tag = Tag::query()->where('slug', 'politika')->first();

    expect($tag)
        ->not()->toBeNull()
        ->and($tag?->is_trending)->toBeTrue()
        ->and($tag?->is_featured)->toBeTrue();
});

it('recalculates tag usage counts from the header action', function () {
    $this->actingAs(User::factory()->create());

    $tag = Tag::factory()->create([
        'usage_count' => 0,
    ]);

    Article::withoutSyncingToSearch(function () use ($tag): void {
        $article = Article::factory()->create();
        $article->tags()->sync([$tag->id]);
    });

    Livewire::test(ListTags::class)
        ->callAction('recalculateCounts')
        ->assertNotified('Счётчики тегов пересчитаны');

    expect($tag->refresh()->usage_count)->toBe(1);
});
