<?php

it('configures the dedicated rss logging channel', function () {
    $channel = config('logging.channels.rss');

    expect($channel)->toMatchArray([
        'driver' => 'daily',
        'path' => storage_path('logs/rss/rss.log'),
        'level' => 'debug',
        'days' => 14,
    ])->and($channel['formatter'])->toBe(\Monolog\Formatter\LineFormatter::class);
});
