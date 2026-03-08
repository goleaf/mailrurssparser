<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

beforeEach(function () {
    config()->set('worktree.base_path', 'storage/framework/testing-worktrees');
    config()->set('worktree.default_base_branch', 'develop');
    config()->set('worktree.env_source', 'storage/framework/testing-worktree.env');
    config()->set('worktree.bootstrap_commands', [
        'composer install --no-interaction',
        'npm install',
        'php artisan config:clear',
        'php artisan cache:clear',
    ]);

    File::deleteDirectory(base_path('storage/framework/testing-worktrees'));
    File::put(base_path('storage/framework/testing-worktree.env'), implode(PHP_EOL, [
        'APP_NAME=Mailrurssparser',
        'APP_URL=http://localhost',
        'DB_CONNECTION=sqlite',
        'DB_DATABASE=database/database.sqlite',
        'SESSION_DOMAIN=null',
        '',
    ]));
});

afterEach(function () {
    File::deleteDirectory(base_path('storage/framework/testing-worktrees'));
    File::delete(base_path('storage/framework/testing-worktree.env'));
});

it('renders a dry-run setup plan for a herd worktree', function () {
    Process::fake([
        'git show-ref --verify --quiet *' => Process::result(exitCode: 1),
    ]);

    $this->artisan('herd:worktree setup feature/login --path=storage/framework/testing-worktrees --dry-run')
        ->expectsOutputToContain('mailrurssparser-feature-login.test')
        ->expectsOutputToContain('database/mailrurssparser-feature-login.sqlite')
        ->expectsOutputToContain('git worktree add')
        ->assertExitCode(SymfonyCommand::SUCCESS);

    Process::assertDidntRun(fn ($process) => str_starts_with((string) $process->command, 'git worktree add '));
});

it('sets up an isolated herd worktree with its own env and sqlite database', function () {
    $siteName = 'mailrurssparser-feature-login';
    $worktreePath = base_path('storage/framework/testing-worktrees/'.$siteName);

    Process::fake([
        'git show-ref --verify --quiet *' => Process::result(exitCode: 1),
        'git worktree add *' => Process::result(),
        'herd link *' => Process::result(),
        'composer install --no-interaction' => Process::result(),
        'npm install' => Process::result(),
        'php artisan config:clear' => Process::result(),
        'php artisan cache:clear' => Process::result(),
        'php artisan migrate --graceful --ansi' => Process::result(),
    ]);

    $this->artisan('herd:worktree setup feature/login --path=storage/framework/testing-worktrees')
        ->expectsOutputToContain('Herd worktree ready at http://mailrurssparser-feature-login.test')
        ->assertExitCode(SymfonyCommand::SUCCESS);

    expect($worktreePath.'/.env')->toBeFile()
        ->and($worktreePath.'/database/'.$siteName.'.sqlite')->toBeFile()
        ->and(File::get($worktreePath.'/.env'))
        ->toContain('APP_URL=http://mailrurssparser-feature-login.test')
        ->toContain('SESSION_DOMAIN=mailrurssparser-feature-login.test')
        ->toContain('SESSION_SECURE_COOKIE=false')
        ->toContain('DB_DATABASE=database/mailrurssparser-feature-login.sqlite');

    Process::assertRan(fn ($process) => $process->path === base_path()
        && str_starts_with((string) $process->command, 'git worktree add '));
    Process::assertRan(fn ($process) => $process->path === $worktreePath
        && $process->command === "herd link '{$siteName}'");
    Process::assertRan(fn ($process) => $process->path === $worktreePath
        && $process->command === 'composer install --no-interaction');
    Process::assertRan(fn ($process) => $process->path === $worktreePath
        && $process->command === 'php artisan migrate --graceful --ansi');
});

it('tears down a herd worktree and can delete the branch', function () {
    $siteName = 'mailrurssparser-feature-login';
    $worktreePath = base_path('storage/framework/testing-worktrees/'.$siteName);

    File::ensureDirectoryExists($worktreePath.'/database');
    File::put($worktreePath.'/.env', 'APP_URL=http://mailrurssparser-feature-login.test'.PHP_EOL);

    Process::fake([
        'herd unlink *' => Process::result(),
        'git worktree remove *' => Process::result(),
        'git branch -D *' => Process::result(),
    ]);

    $this->artisan('herd:worktree teardown feature/login --path=storage/framework/testing-worktrees --delete-branch')
        ->expectsOutputToContain('Removed Herd worktree mailrurssparser-feature-login')
        ->assertExitCode(SymfonyCommand::SUCCESS);

    expect($worktreePath)->not->toBeDirectory();

    Process::assertRan(fn ($process) => $process->path === base_path()
        && $process->command === "herd unlink '{$siteName}'");
    Process::assertRan(fn ($process) => $process->path === base_path()
        && $process->command === "git branch -D 'feature/login'");
});
