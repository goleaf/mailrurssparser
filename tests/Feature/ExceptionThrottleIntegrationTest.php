<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

afterEach(function () {
    \Mockery::close();
});

it('throttles repeated rss parser runtime exceptions by message', function () {
    $message = 'Feed unreachable after 3 attempts: https://example.test/rss?case='.Str::uuid();

    $logger = \Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')
        ->once()
        ->withArgs(function (string $loggedMessage, array $context) use ($message): bool {
            return $loggedMessage === $message
                && ($context['exception'] ?? null) instanceof RuntimeException;
        });

    app()->instance(LoggerInterface::class, $logger);

    $handler = app(ExceptionHandler::class);

    $handler->report(new RuntimeException($message));
    $handler->report(new RuntimeException($message));
});

it('reports distinct rss parser runtime exception messages independently', function () {
    $firstMessage = 'Invalid RSS: no channel element ['.Str::uuid().']';
    $secondMessage = 'Feed gone: HTTP 410 ['.Str::uuid().']';

    $logger = \Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message): bool => $message === $firstMessage);
    $logger->shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message): bool => $message === $secondMessage);

    app()->instance(LoggerInterface::class, $logger);

    $handler = app(ExceptionHandler::class);

    $handler->report(new RuntimeException($firstMessage));
    $handler->report(new RuntimeException($secondMessage));
});
