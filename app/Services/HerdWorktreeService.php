<?php

namespace App\Services;

use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class HerdWorktreeService
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    public function buildSetupPlan(
        string $branch,
        ?string $baseBranch = null,
        ?string $projectName = null,
        ?string $worktreeRoot = null,
        bool $linkWithHerd = true,
        bool $installDependencies = true,
        bool $runMigrations = true,
    ): HerdWorktreePlan {
        $normalizedBranch = trim($branch);
        $normalizedProjectName = $this->sanitizeName($projectName ?: basename(base_path()));
        $sanitizedBranch = $this->sanitizeName($normalizedBranch);
        $siteName = $this->sanitizeName($normalizedProjectName.'-'.$sanitizedBranch);
        $resolvedWorktreeRoot = $this->resolveWorktreeRoot($worktreeRoot);
        $resolvedWorktreePath = $resolvedWorktreeRoot.DIRECTORY_SEPARATOR.$siteName;
        $sourceEnvPath = $this->resolveEnvSourcePath();
        $usesExistingBranch = $this->branchExists($normalizedBranch);
        $resolvedBaseBranch = $usesExistingBranch ? null : $this->resolveBaseBranch($baseBranch);
        $usesSqlite = $this->shouldUseSqlite($sourceEnvPath);
        $sqliteSourcePath = $usesSqlite ? base_path('database/database.sqlite') : null;
        $sqliteTargetPath = $usesSqlite
            ? $resolvedWorktreePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.$siteName.'.sqlite'
            : null;

        return new HerdWorktreePlan(
            action: 'setup',
            branch: $normalizedBranch,
            projectName: $normalizedProjectName,
            siteName: $siteName,
            siteUrl: 'http://'.$siteName.'.test',
            worktreeRoot: $resolvedWorktreeRoot,
            worktreePath: $resolvedWorktreePath,
            baseBranch: $resolvedBaseBranch,
            envSourcePath: $sourceEnvPath,
            envTargetPath: $resolvedWorktreePath.DIRECTORY_SEPARATOR.'.env',
            envUpdates: $this->environmentUpdates($siteName, $sqliteTargetPath),
            sqliteSourcePath: $sqliteSourcePath,
            sqliteTargetPath: $sqliteTargetPath,
            commands: $this->setupCommands(
                branch: $normalizedBranch,
                worktreePath: $resolvedWorktreePath,
                siteName: $siteName,
                baseBranch: $resolvedBaseBranch,
                usesExistingBranch: $usesExistingBranch,
                linkWithHerd: $linkWithHerd,
                installDependencies: $installDependencies,
                runMigrations: $runMigrations,
            ),
            linkWithHerd: $linkWithHerd,
            installDependencies: $installDependencies,
            runMigrations: $runMigrations,
            deleteBranch: false,
        );
    }

    public function buildTeardownPlan(
        string $branch,
        ?string $projectName = null,
        ?string $worktreeRoot = null,
        bool $deleteBranch = false,
    ): HerdWorktreePlan {
        $normalizedBranch = trim($branch);
        $normalizedProjectName = $this->sanitizeName($projectName ?: basename(base_path()));
        $siteName = $this->sanitizeName($normalizedProjectName.'-'.$this->sanitizeName($normalizedBranch));
        $resolvedWorktreeRoot = $this->resolveWorktreeRoot($worktreeRoot);
        $resolvedWorktreePath = $resolvedWorktreeRoot.DIRECTORY_SEPARATOR.$siteName;

        return new HerdWorktreePlan(
            action: 'teardown',
            branch: $normalizedBranch,
            projectName: $normalizedProjectName,
            siteName: $siteName,
            siteUrl: 'http://'.$siteName.'.test',
            worktreeRoot: $resolvedWorktreeRoot,
            worktreePath: $resolvedWorktreePath,
            baseBranch: null,
            envSourcePath: $this->resolveEnvSourcePath(),
            envTargetPath: $resolvedWorktreePath.DIRECTORY_SEPARATOR.'.env',
            envUpdates: [],
            sqliteSourcePath: null,
            sqliteTargetPath: null,
            commands: $this->teardownCommands($resolvedWorktreePath, $siteName, $normalizedBranch, $deleteBranch),
            linkWithHerd: true,
            installDependencies: false,
            runMigrations: false,
            deleteBranch: $deleteBranch,
        );
    }

    public function executeSetup(HerdWorktreePlan $plan): void
    {
        if ($this->files->exists($plan->worktreePath)) {
            throw new RuntimeException("Worktree path already exists: {$plan->worktreePath}");
        }

        $this->files->ensureDirectoryExists($plan->worktreeRoot);
        $this->runInBasePath($plan->commands[0]);

        if (! $this->files->isDirectory($plan->worktreePath)) {
            $this->files->ensureDirectoryExists($plan->worktreePath);
        }

        $this->prepareEnvironment($plan);

        if ($plan->linkWithHerd) {
            $this->runInWorktree('herd link '.escapeshellarg($plan->siteName), $plan);
        }

        if ($plan->installDependencies) {
            foreach (config('worktree.bootstrap_commands', []) as $command) {
                if (! is_string($command) || $command === '') {
                    continue;
                }

                $this->runInWorktree($command, $plan);
            }
        }

        if ($plan->runMigrations) {
            $this->runInWorktree('php artisan migrate --graceful --ansi', $plan);
        }
    }

    public function executeTeardown(HerdWorktreePlan $plan): void
    {
        $this->runInBasePath('herd unlink '.escapeshellarg($plan->siteName), allowFailure: true);
        $this->runInBasePath('git worktree remove '.escapeshellarg($plan->worktreePath).' --force');

        if ($this->files->isDirectory($plan->worktreePath)) {
            $this->files->deleteDirectory($plan->worktreePath);
        }

        if ($plan->deleteBranch) {
            $this->runInBasePath('git branch -D '.escapeshellarg($plan->branch));
        }
    }

    private function prepareEnvironment(HerdWorktreePlan $plan): void
    {
        $this->files->copy($plan->envSourcePath, $plan->envTargetPath);

        if ($plan->sqliteTargetPath !== null) {
            $sqliteDirectory = dirname($plan->sqliteTargetPath);
            $this->files->ensureDirectoryExists($sqliteDirectory);

            if ($plan->sqliteSourcePath !== null && $this->files->exists($plan->sqliteSourcePath)) {
                $this->files->copy($plan->sqliteSourcePath, $plan->sqliteTargetPath);
            } else {
                $this->files->put($plan->sqliteTargetPath, '');
            }
        }

        $contents = $this->files->get($plan->envTargetPath);

        foreach ($plan->envUpdates as $key => $value) {
            $contents = $this->replaceEnvValue($contents, $key, $value);
        }

        $this->files->put($plan->envTargetPath, $contents);
    }

    /**
     * @return list<string>
     */
    private function setupCommands(
        string $branch,
        string $worktreePath,
        string $siteName,
        ?string $baseBranch,
        bool $usesExistingBranch,
        bool $linkWithHerd,
        bool $installDependencies,
        bool $runMigrations,
    ): array {
        $commands = [
            $usesExistingBranch
                ? 'git worktree add '.escapeshellarg($worktreePath).' '.escapeshellarg($branch)
                : 'git worktree add '.escapeshellarg($worktreePath).' -b '.escapeshellarg($branch).' '.escapeshellarg($baseBranch ?? 'main'),
        ];

        if ($linkWithHerd) {
            $commands[] = 'herd link '.escapeshellarg($siteName);
        }

        if ($installDependencies) {
            foreach (config('worktree.bootstrap_commands', []) as $command) {
                if (is_string($command) && $command !== '') {
                    $commands[] = $command;
                }
            }
        }

        if ($runMigrations) {
            $commands[] = 'php artisan migrate --graceful --ansi';
        }

        return $commands;
    }

    /**
     * @return list<string>
     */
    private function teardownCommands(string $worktreePath, string $siteName, string $branch, bool $deleteBranch): array
    {
        $commands = [
            'herd unlink '.escapeshellarg($siteName),
            'git worktree remove '.escapeshellarg($worktreePath).' --force',
        ];

        if ($deleteBranch) {
            $commands[] = 'git branch -D '.escapeshellarg($branch);
        }

        return $commands;
    }

    /**
     * @return array<string, string>
     */
    private function environmentUpdates(string $siteName, ?string $sqliteTargetPath): array
    {
        $updates = [
            'APP_URL' => 'http://'.$siteName.'.test',
            'SESSION_DOMAIN' => $siteName.'.test',
            'SESSION_SECURE_COOKIE' => 'false',
        ];

        if ($sqliteTargetPath !== null) {
            $updates['DB_CONNECTION'] = 'sqlite';
            $updates['DB_DATABASE'] = 'database/'.$siteName.'.sqlite';
        }

        return $updates;
    }

    private function resolveWorktreeRoot(?string $worktreeRoot): string
    {
        $path = $worktreeRoot ?: (string) config('worktree.base_path', '.worktrees');

        if ($path === '') {
            return base_path('.worktrees');
        }

        if (Str::startsWith($path, [DIRECTORY_SEPARATOR])) {
            return $path;
        }

        return base_path($path);
    }

    private function resolveEnvSourcePath(): string
    {
        $configured = config('worktree.env_source');

        if (is_string($configured) && $configured !== '') {
            $configuredPath = Str::startsWith($configured, [DIRECTORY_SEPARATOR])
                ? $configured
                : base_path($configured);

            if ($this->files->exists($configuredPath)) {
                return $configuredPath;
            }
        }

        if ($this->files->exists(base_path('.env'))) {
            return base_path('.env');
        }

        return base_path('.env.example');
    }

    private function resolveBaseBranch(?string $baseBranch): string
    {
        if (is_string($baseBranch) && $baseBranch !== '') {
            return $baseBranch;
        }

        $configured = config('worktree.default_base_branch');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $result = $this->runInBasePath('git symbolic-ref refs/remotes/origin/HEAD', allowFailure: true);

        if ($result !== null && $result->successful()) {
            return Str::afterLast(trim($result->output()), '/');
        }

        return 'main';
    }

    private function branchExists(string $branch): bool
    {
        $result = $this->runInBasePath(
            'git show-ref --verify --quiet '.escapeshellarg('refs/heads/'.$branch),
            allowFailure: true,
        );

        return $result?->successful() ?? false;
    }

    private function shouldUseSqlite(string $envSourcePath): bool
    {
        if (! $this->files->exists($envSourcePath)) {
            return true;
        }

        $contents = $this->files->get($envSourcePath);
        $connection = $this->extractEnvValue($contents, 'DB_CONNECTION');

        return $connection === null || $connection === 'sqlite';
    }

    private function sanitizeName(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->replace(['/', '\\', '_', ' '], '-')
            ->lower()
            ->replaceMatches('/[^a-z0-9.-]+/', '-')
            ->trim('-')
            ->value();
    }

    private function extractEnvValue(string $contents, string $key): ?string
    {
        preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches);

        if (! isset($matches[1])) {
            return null;
        }

        return trim($matches[1], "\"' \t");
    }

    private function replaceEnvValue(string $contents, string $key, string $value): string
    {
        $pattern = '/^'.preg_quote($key, '/').'=.*/m';
        $replacement = $key.'='.$value;

        if (preg_match($pattern, $contents) === 1) {
            return (string) preg_replace($pattern, $replacement, $contents);
        }

        return rtrim($contents).PHP_EOL.$replacement.PHP_EOL;
    }

    private function runInWorktree(string $command, HerdWorktreePlan $plan, bool $allowFailure = false): ?ProcessResultContract
    {
        return $this->run($command, $plan->worktreePath, $allowFailure);
    }

    private function runInBasePath(string $command, bool $allowFailure = false): ?ProcessResultContract
    {
        return $this->run($command, base_path(), $allowFailure);
    }

    private function run(string $command, string $path, bool $allowFailure = false): ?ProcessResultContract
    {
        $result = Process::path($path)->run($command);

        if ($result->failed() && ! $allowFailure) {
            $message = trim($result->errorOutput()) !== '' ? trim($result->errorOutput()) : trim($result->output());

            throw new RuntimeException($message !== '' ? $message : "Command failed: {$command}");
        }

        return $result;
    }
}
