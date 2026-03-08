<?php

use Illuminate\Foundation\Exceptions\Renderer\Frame;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Process\Process;

it('maps app editor environment variables into the application config', function () {
    $process = new Process([
        'php',
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo json_encode(config('app.editor'), JSON_THROW_ON_ERROR);
        PHP,
    ], base_path(), [
        'APP_ENV' => 'local',
        'APP_EDITOR' => 'windsurf',
        'APP_EDITOR_BASE_PATH' => '/workspace/mailrurssparser',
    ]);

    $process->mustRun();

    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe([
            'name' => 'windsurf',
            'base_path' => '/workspace/mailrurssparser',
        ]);
});

it('falls back to ignition editor when no app editor is configured', function () {
    $process = new Process([
        'php',
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo json_encode(config('app.editor'), JSON_THROW_ON_ERROR);
        PHP,
    ], base_path(), [
        'APP_ENV' => 'local',
        'IGNITION_EDITOR' => 'phpstorm',
    ]);

    $process->mustRun();

    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe('phpstorm');
});

it('builds local exception page editor links with base path remapping', function () {
    config()->set('app.editor', [
        'name' => 'windsurf',
        'base_path' => '/workspace/mailrurssparser',
    ]);

    $frame = new Frame(
        FlattenException::createFromThrowable(new RuntimeException('Boom')),
        [],
        [
            'file' => app_path('Services/ArticleCacheService.php'),
            'line' => 21,
        ],
        base_path(),
    );

    expect($frame->editorHref())
        ->toBe('windsurf://file//workspace/mailrurssparser/app/Services/ArticleCacheService.php:21');
});
