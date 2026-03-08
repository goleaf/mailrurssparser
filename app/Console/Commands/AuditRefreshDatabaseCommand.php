<?php

namespace App\Console\Commands;

use App\Services\FeatureTestRefreshDatabaseAuditService;
use Illuminate\Console\Command;
use JsonException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class AuditRefreshDatabaseCommand extends Command
{
    protected $signature = 'test:audit-refresh-database
        {--path=* : Relative feature test file or directory to inspect}
        {--json : Render the audit report as JSON}';

    protected $description = 'Find feature tests that create database records without a database reset strategy';

    public function __construct(
        private readonly FeatureTestRefreshDatabaseAuditService $audit,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->audit->scan($this->paths());

        if ($this->option('json')) {
            try {
                $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } catch (JsonException $exception) {
                $this->components->error($exception->getMessage());

                return SymfonyCommand::FAILURE;
            }

            return $report->unguarded_files_count === 0
                ? SymfonyCommand::SUCCESS
                : SymfonyCommand::FAILURE;
        }

        foreach ($report->scope('warnings')->all() as $warning) {
            $this->components->warn($warning);
        }

        if ($report->scope('global_protections')->isNotEmpty()) {
            foreach ($report->scope('global_protections')->all() as $protection) {
                $this->components->info('Global protection detected: '.$protection);
            }
        } else {
            $this->components->warn('No global database reset protection was detected in tests/Pest.php for Feature tests.');
        }

        $this->components->info(sprintf(
            'Scanned %d feature test files. %d file(s) contain database mutation signals.',
            $report->scanned_files,
            $report->mutating_files_count,
        ));

        if ($report->mutating_files_count === 0) {
            $this->components->info('No database-mutating feature tests were detected.');

            return SymfonyCommand::SUCCESS;
        }

        if ($report->unguarded_files_count === 0) {
            $this->components->info('No unguarded feature tests found.');

            return SymfonyCommand::SUCCESS;
        }

        $this->table(
            ['Path', 'Signals', 'Protection'],
            array_map(fn (array $file): array => [
                $file['path'],
                implode(', ', $file['signals']),
                $file['protections'] === [] ? 'missing' : implode(', ', $file['protections']),
            ], $report->scope('unguarded_files')->all()),
        );

        $this->components->error("Found {$report->unguarded_files_count} unguarded feature test file(s).");

        return SymfonyCommand::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        $paths = $this->option('path');

        return is_array($paths)
            ? array_values(array_filter(array_map(fn (mixed $path): string => trim((string) $path), $paths)))
            : [];
    }
}
