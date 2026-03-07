<?php

use App\Mail\ConfirmSubscriptionMail;
use App\Models\Article;
use App\Models\Bookmark;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Mail;

it('returns bookmarked articles for the current anonymous session', function () {
    $article = Article::factory()->create([
        'status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $sessionHash = hash('sha256', '127.0.0.1Bookmark Agent');

    Bookmark::factory()->create([
        'article_id' => $article->id,
        'session_hash' => $sessionHash,
    ]);

    Bookmark::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeader('User-Agent', 'Bookmark Agent')
        ->getJson('/api/v1/bookmarks')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $article->id);
});

it('toggles bookmarks and reports bookmarked ids for the current session', function () {
    $article = Article::factory()->create([
        'bookmarks_count' => 0,
    ]);

    $request = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeader('User-Agent', 'Bookmark Agent');

    $request->postJson('/api/v1/bookmarks/'.$article->id)
        ->assertSuccessful()
        ->assertJson([
            'bookmarked' => true,
            'total' => 1,
        ]);

    $request->postJson('/api/v1/bookmarks/check', [
        'ids' => [$article->id, 999999],
    ])->assertSuccessful()
        ->assertJson([
            'bookmarked_ids' => [$article->id],
        ]);

    $request->postJson('/api/v1/bookmarks/'.$article->id)
        ->assertSuccessful()
        ->assertJson([
            'bookmarked' => false,
            'total' => 0,
        ]);

    expect(Bookmark::query()->where('article_id', $article->id)->count())->toBe(0)
        ->and($article->fresh()->bookmarks_count)->toBe(0);
});

it('tracks shares and returns a share url', function () {
    $article = Article::factory()->create([
        'title' => 'Portal Story',
        'slug' => 'portal-story',
        'shares_count' => 0,
    ]);

    $this->postJson('/api/v1/share/'.$article->id, [
        'platform' => 'telegram',
    ])->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('platform', 'telegram')
        ->assertJsonPath('total', 1);

    expect($article->fresh()->shares_count)->toBe(1);
});

it('subscribes and resends confirmation emails based on subscriber state', function () {
    Mail::fake();

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'reader@example.com',
        'name' => 'Reader',
    ])->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Проверьте почту для подтверждения',
        ]);

    $subscriber = NewsletterSubscriber::query()->where('email', 'reader@example.com')->firstOrFail();

    Mail::assertSent(ConfirmSubscriptionMail::class, 1);

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'reader@example.com',
    ])->assertSuccessful()
        ->assertJson([
            'resent' => true,
        ]);

    Mail::assertSent(ConfirmSubscriptionMail::class, 2);

    $subscriber->update([
        'confirmed' => true,
        'confirmed_at' => now(),
    ]);

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'reader@example.com',
    ])->assertSuccessful()
        ->assertJson([
            'already_subscribed' => true,
        ]);
});

it('confirms and unsubscribes newsletter subscribers', function () {
    $subscriber = NewsletterSubscriber::factory()->create([
        'confirmed' => false,
        'confirmed_at' => null,
        'unsubscribed_at' => null,
    ]);

    $this->getJson('/api/v1/newsletter/confirm/'.$subscriber->token)
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Подписка подтверждена',
        ]);

    $subscriber->refresh();

    expect($subscriber->confirmed)->toBeTrue()
        ->and($subscriber->confirmed_at)->not->toBeNull();

    $this->getJson('/api/v1/newsletter/confirm/'.$subscriber->token)
        ->assertSuccessful()
        ->assertJson([
            'already_confirmed' => true,
        ]);

    $this->getJson('/api/v1/newsletter/unsubscribe/'.$subscriber->token)
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
        ]);

    expect($subscriber->fresh()->unsubscribed_at)->not->toBeNull();
});
