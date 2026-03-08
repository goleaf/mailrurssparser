<?php

namespace App\Console\Commands;

use App\Services\AssetCleanerService;
use Illuminate\Console\Command;
use Illuminate\Support\Fluent;
use RuntimeException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class AssetsDeleteCommand extends Command
{
    protected $signature = 'assets:delete
        {--type=* : Filter to one or more asset groups}
        {--dry-run : Render the deletion plan without removing files}
        {--no-backup : Delete stale assets without storing a backup}
        {--json : Render the report as JSON}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Delete stale published assets and optionally back them up first';

    public function __construct(
        private readonly AssetCleanerService $assetCleaner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $report = $this->assetCleaner->scan($this->types());
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->renderDryRun($report);
        }

        if ($report->scope('files')->isEmpty()) {
            if ($this->option('json')) {
                $this->line(json_encode([
                    'scan' => $report->toArray(),
                    'delete' => [
                        'backup_path' => null,
                        'deleted_files' => 0,
                        'reclaimed_bytes' => 0,
                    ],
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return SymfonyCommand::SUCCESS;
            }

            foreach ($report->scope('warnings')->all() as $warning) {
                $this->components->warn($warning);
            }

            $this->components->info('No stale assets found.');

            return SymfonyCommand::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$report->stale_files} stale assets?")) {
            return SymfonyCommand::SUCCESS;
        }

        $result = $this->assetCleaner->delete(
            $report->scope('files')->all(),
            backup: ! $this->option('no-backup'),
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'scan' => $report->toArray(),
                'delete' => $result->toArray(),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return SymfonyCommand::SUCCESS;
        }

        foreach ($report->scope('warnings')->all() as $warning) {
            $this->components->warn($warning);
        }

        $this->components->info(
            sprintf(
                'Deleted %d stale assets and reclaimed %s.',
                $result->deleted_files,
                $this->assetCleaner->formatBytes($result->reclaimed_bytes),
            ),
        );

        if ($result->scope('backup_path')->isNotEmpty()) {
            $relativeBackupPath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $result->backup_path);

            $this->components->info("Backup stored at {$relativeBackupPath}");
        }

        return SymfonyCommand::SUCCESS;
    }

    /**
     * @param  Fluent<array-key, mixed>  $report
     */
    private function renderDryRun(Fluent $report): int
    {
        if ($this->option('json')) {
            $this->line(json_encode([
                'mode' => 'dry-run',
                'scan' => $report->toArray(),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return SymfonyCommand::SUCCESS;
        }

        $this->components->info(
            sprintf(
                'Dry run: %d stale assets would be removed.',
                $report->stale_files,
            ),
        );

        foreach ($report->scope('warnings')->all() as $warning) {
            $this->components->warn($warning);
        }

        if ($report->scope('files')->isEmpty()) {
            $this->components->info('No stale assets found.');

            return SymfonyCommand::SUCCESS;
        }

        $this->table(
            ['Type', 'Path', 'Size', 'Reason'],
            array_map(
                fn (array $file): array => [
                    $file['type'],
                    $file['relative_path'],
                    $this->assetCleaner->formatBytes((int) $file['size']),
                    $file['reason'],
                ],
                array_slice($report->scope('files')->all(), 0, $this->tableLimit()),
            ),
        );

        if ($report->stale_files > $this->tableLimit()) {
            $remaining = $report->stale_files - $this->tableLimit();

            $this->components->info("Showing the first {$this->tableLimit()} stale assets. {$remaining} more were omitted.");
        }

        $this->components->info(
            'Potential savings: '.$this->assetCleaner->formatBytes($report->reclaimable_bytes),
        );

        return SymfonyCommand::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function types(): array
    {
        $types = $this->option('type');

        return is_array($types) ? array_values($types) : [];
    }

    private function tableLimit(): int
    {
        return max(1, (int) config('asset-cleaner.table_limit', 50));
    }
}
