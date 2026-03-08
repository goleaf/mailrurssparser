<?php

use App\Services\SendNewsletterConfirmationMail;
use Illuminate\Cache\Events\CacheFailedOver;
use Illuminate\Queue\Events\QueueFailedOver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

it('logs cache failover events with the failed store context', function () {
    Log::spy();

    event(new CacheFailedOver('database', new RuntimeException('Cache connection lost.')));

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Cache store failed over.', [
            'store' => 'database',
            'exception' => RuntimeException::class,
            'message' => 'Cache connection lost.',
        ]);
});

it('logs queue failover events with the failed connection context', function () {
    Log::spy();

    event(new QueueFailedOver('database', new SendNewsletterConfirmationMail(new \App\Models\NewsletterSubscriber), new RuntimeException('Queue insert failed.')));

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Queue connection failed over.', [
            'connection' => 'database',
            'command' => SendNewsletterConfirmationMail::class,
            'exception' => RuntimeException::class,
            'message' => 'Queue insert failed.',
        ]);
});

it('registers cache and queue failover listeners', function () {
    expect(Event::getListeners(CacheFailedOver::class))->not->toBeEmpty()
        ->and(Event::getListeners(QueueFailedOver::class))->not->toBeEmpty();
});
