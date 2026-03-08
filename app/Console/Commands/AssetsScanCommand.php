<?php

namespace App\Console\Commands;

use App\Services\AssetCleanerService;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class AssetsScanCommand extends Command
{
    protected $signature = 'assets:scan
        {--type=* : Filter to one or more asset groups}
        {--json : Render the scan report as JSON}';

    protected $description = 'Scan for stale published assets that are no longer referenced';

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

        if ($this->option('json')) {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return SymfonyCommand::SUCCESS;
        }

        $this->components->info(
            sprintf(
                'Scanned %d published assets across %d groups.',
                $report->scanned_files,
                count($report->types),
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
