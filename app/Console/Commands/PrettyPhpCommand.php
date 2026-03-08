<?php

namespace App\Console\Commands;

use App\Services\PrettyPhpService;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class PrettyPhpCommand extends Command
{
    protected $signature = 'format:pretty-php
        {path?* : Files or directories to format}
        {--check : Fail if formatting changes are required}
        {--diff : Show a diff if formatting changes are required}
        {--binary= : Explicit pretty-php executable or PHAR path}
        {--dry-run : Print the resolved pretty-php command without executing it}';

    protected $description = 'Run pretty-php with the project preset and config';

    /**
     * Execute the console command.
     */
    public function handle(PrettyPhpService $prettyPhp): int
    {
        if ($this->option('check') && $this->option('diff')) {
            $this->error('Choose either --check or --diff, not both.');

            return SymfonyCommand::INVALID;
        }

        try {
            $command = $prettyPhp->command(
                paths: (array) $this->argument('path'),
                check: (bool) $this->option('check'),
                diff: (bool) $this->option('diff'),
                binaryOverride: $this->option('binary'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->line('Resolved pretty-php command:');
            $this->line($command);

            return SymfonyCommand::SUCCESS;
        }

        try {
            $result = $prettyPhp->run($command);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        if (trim($result->output()) !== '') {
            $this->output->write($result->output());
        }

        if (trim($result->errorOutput()) !== '') {
            $this->output->write($result->errorOutput());
        }

        if ($result->failed()) {
            return $result->exitCode();
        }

        $this->info('pretty-php completed successfully.');

        return SymfonyCommand::SUCCESS;
    }
}
