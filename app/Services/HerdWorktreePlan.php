<?php

namespace App\Services;

final readonly class HerdWorktreePlan
{
    /**
     * @param  array<string, string>  $envUpdates
     * @param  list<string>  $commands
     */
    public function __construct(
        public string $action,
        public string $branch,
        public string $projectName,
        public string $siteName,
        public string $siteUrl,
        public string $worktreeRoot,
        public string $worktreePath,
        public ?string $baseBranch,
        public string $envSourcePath,
        public string $envTargetPath,
        public array $envUpdates,
        public ?string $sqliteSourcePath,
        public ?string $sqliteTargetPath,
        public array $commands,
        public bool $linkWithHerd,
        public bool $installDependencies,
        public bool $runMigrations,
        public bool $deleteBranch,
    ) {}
}
