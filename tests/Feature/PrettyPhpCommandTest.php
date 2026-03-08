<?php

use App\Services\PrettyPhpService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

beforeEach(function () {
    config()->set('pretty_php.config_path', '.prettyphp');
    config()->set('pretty_php.default_paths', ['.']);

    File::ensureDirectoryExists(base_path('storage/framework/testing-pretty-php'));
});

afterEach(function () {
    File::deleteDirectory(base_path('storage/framework/testing-pretty-php'));
});

it('builds a command for a pretty-php phar install', function () {
    $binaryPath = base_path('storage/framework/testing-pretty-php/pretty-php.phar');

    File::put($binaryPath, 'phar');

    config()->set('pretty_php.binary_candidates', [
        'storage/framework/testing-pretty-php/pretty-php.phar',
    ]);

    $command = app(PrettyPhpService::class)->command(paths: ['app'], diff: true);

    expect($command)->toContain(PHP_BINARY)
        ->toContain('pretty-php.phar')
        ->toContain('--config=')
        ->toContain('--diff')
        ->toContain("'app'");
});

it('runs pretty-php in check mode with the project config', function () {
    $binaryPath = base_path('storage/framework/testing-pretty-php/pretty-php');

    File::put($binaryPath, '#!/usr/bin/env php');

    config()->set('pretty_php.binary_candidates', [
        'storage/framework/testing-pretty-php/pretty-php',
    ]);

    Process::fake([
        '*' => Process::result(),
    ]);

    $this->artisan('format:pretty-php --check')
        ->expectsOutputToContain('pretty-php completed successfully.')
        ->assertExitCode(SymfonyCommand::SUCCESS);

    Process::assertRan(fn ($process) => $process->path === base_path()
        && str_contains((string) $process->command, 'pretty-php')
        && str_contains((string) $process->command, '--check')
        && str_contains((string) $process->command, '--config=')
        && str_contains((string) $process->command, "'.'"));
});

it('filters invalid configured pretty-php candidates and paths', function () {
    $binaryPath = base_path('storage/framework/testing-pretty-php/pretty-php');

    File::put($binaryPath, '#!/usr/bin/env php');

    config()->set('pretty_php.binary_candidates', [
        null,
        '',
        42,
        ' storage/framework/testing-pretty-php/pretty-php ',
    ]);
    config()->set('pretty_php.default_paths', [
        '',
        null,
        ' app ',
        false,
    ]);

    $command = app(PrettyPhpService::class)->command();

    expect($command)->toContain('testing-pretty-php/pretty-php')
        ->toContain("'app'")
        ->not->toContain("''");
});

it('fails with a helpful message when pretty-php is unavailable', function () {
    config()->set('pretty_php.binary_candidates', [
        'storage/framework/testing-pretty-php/missing-pretty-php.phar',
    ]);

    $this->artisan('format:pretty-php')
        ->expectsOutputToContain('pretty-php executable not found.')
        ->assertExitCode(SymfonyCommand::FAILURE);
});

it('registers composer shortcuts for pretty-php', function () {
    /** @var array{scripts: array<string, array<int, string>>} $composer */
    $composer = json_decode((string) File::get(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['scripts']['format:pretty-php'] ?? null)->toBe([
        '@php artisan format:pretty-php',
    ])->and($composer['scripts']['format:pretty-php:check'] ?? null)->toBe([
        '@php artisan format:pretty-php --check',
    ]);
});
