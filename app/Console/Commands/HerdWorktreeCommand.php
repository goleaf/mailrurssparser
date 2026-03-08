<?php

namespace App\Console\Commands;

use App\Services\HerdWorktreePlan;
use App\Services\HerdWorktreeService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class HerdWorktreeCommand extends Command
{
    protected $signature = 'herd:worktree
        {action : setup or teardown}
        {branch : Branch name to provision or remove}
        {--base= : Base branch to use when creating a new worktree branch}
        {--project= : Project name prefix for the Herd site}
        {--path= : Relative or absolute root directory for worktrees}
        {--no-link : Skip `herd link` during setup}
        {--no-install : Skip composer and npm installation during setup}
        {--no-migrate : Skip running migrations after setup}
        {--delete-branch : Delete the git branch after teardown}
        {--dry-run : Show the computed plan without executing it}';

    protected $description = 'Create or remove Laravel Herd worktrees with isolated environment settings';

    /**
     * Execute the console command.
     */
    public function handle(HerdWorktreeService $service): int
    {
        $action = (string) $this->argument('action');
        $branch = (string) $this->argument('branch');

        if (! in_array($action, ['setup', 'teardown'], true)) {
            $this->error("Unsupported action [{$action}]. Use setup or teardown.");

            return SymfonyCommand::FAILURE;
        }

        $plan = $action === 'setup'
            ? $service->buildSetupPlan(
                branch: $branch,
                baseBranch: $this->option('base'),
                projectName: $this->option('project'),
                worktreeRoot: $this->option('path'),
                linkWithHerd: ! $this->option('no-link'),
                installDependencies: ! $this->option('no-install'),
                runMigrations: ! $this->option('no-migrate'),
            )
            : $service->buildTeardownPlan(
                branch: $branch,
                projectName: $this->option('project'),
                worktreeRoot: $this->option('path'),
                deleteBranch: (bool) $this->option('delete-branch'),
            );

        $this->renderPlan($plan);

        if ($this->option('dry-run')) {
            $this->info('Dry run only. No filesystem or process changes were made.');

            return SymfonyCommand::SUCCESS;
        }

        try {
            if ($action === 'setup') {
                $service->executeSetup($plan);
                $this->info("Herd worktree ready at {$plan->siteUrl}");
            } else {
                $service->executeTeardown($plan);
                $this->info("Removed Herd worktree {$plan->siteName}");
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        return SymfonyCommand::SUCCESS;
    }

    private function renderPlan(HerdWorktreePlan $plan): void
    {
        $rows = [
            ['Action', $plan->action],
            ['Branch', $plan->branch],
            ['Project', $plan->projectName],
            ['Site', $plan->siteName],
            ['URL', $plan->siteUrl],
            ['Worktree', $plan->worktreePath],
        ];

        if ($plan->baseBranch !== null) {
            $rows[] = ['Base Branch', $plan->baseBranch];
        }

        if ($plan->action === 'setup') {
            $rows[] = ['Env Source', $plan->envSourcePath];
            $rows[] = ['Env Target', $plan->envTargetPath];

            if ($plan->sqliteTargetPath !== null) {
                $rows[] = ['SQLite Target', $plan->sqliteTargetPath];
            }
        }

        $this->table(['Setting', 'Value'], $rows);

        $this->table(
            ['Commands'],
            collect($plan->commands)->map(fn (string $command): array => [$command])->all(),
        );
    }
}
