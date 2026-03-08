<?php

use Symfony\Component\Process\Process;

function localStoreConfiguration(): array
{
    $process = new Process([
        'php',
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo json_encode([
            'database' => config('database.default'),
            'cache' => config('cache.default'),
            'cache_failover_stores' => config('cache.stores.failover.stores'),
            'session' => config('session.driver'),
            'scout' => config('scout.driver'),
            'tntsearch_storage_exists' => is_dir((string) config('scout.tntsearch.storage')),
        ], JSON_THROW_ON_ERROR);
        PHP,
    ], base_path(), [
        'APP_ENV' => 'local',
        'CACHE_STORE' => 'database',
        'SESSION_DRIVER' => 'database',
        'DB_CONNECTION' => 'sqlite',
        'SCOUT_DRIVER' => 'tntsearch',
    ]);

    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

it('uses file backed cache and sessions for local sqlite environments', function () {
    $config = localStoreConfiguration();

    expect($config)
        ->toMatchArray([
            'database' => 'sqlite',
            'cache' => 'file',
            'session' => 'file',
            'scout' => 'tntsearch',
            'tntsearch_storage_exists' => true,
        ]);
});

it('prefers file-backed failover cache stores for local sqlite environments', function () {
    $process = new Process([
        'php',
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo json_encode([
            'database' => config('database.default'),
            'cache' => config('cache.default'),
            'cache_failover_stores' => config('cache.stores.failover.stores'),
            'session' => config('session.driver'),
        ], JSON_THROW_ON_ERROR);
        PHP,
    ], base_path(), [
        'APP_ENV' => 'local',
        'CACHE_STORE' => 'failover',
        'SESSION_DRIVER' => 'database',
        'DB_CONNECTION' => 'sqlite',
    ]);

    $process->mustRun();

    $config = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($config)->toMatchArray([
        'database' => 'sqlite',
        'cache' => 'failover',
        'cache_failover_stores' => ['file', 'array'],
        'session' => 'file',
    ]);
});
