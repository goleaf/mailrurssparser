<?php

namespace App\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

class FeatureTestRefreshDatabaseAuditService
{
    /**
     * @var array<string, string>
     */
    private const MUTATION_PATTERNS = [
        'factory->create' => '/::factory\s*\([^;]*?\)\s*(?:->\w+\([^;]*?\)\s*)*->create(?:One|Many|Quietly)?\s*\(/s',
        'model::create' => '/::(?:query\(\)->)?create\s*\(/',
        'model::insert' => '/::(?:query\(\)->)?(?:insert|insertOrIgnore|upsert)\s*\(/',
        'relationship->create' => '/->\w+\(\)->create(?:Many|Quietly)?\s*\(/',
        'model->save' => '/->save(?:OrFail|Quietly)?\s*\(/',
        'seed' => '/->seed\s*\(/',
    ];

    /**
     * @var array<string, string>
     */
    private const RESET_STRATEGIES = [
        'RefreshDatabase' => 'RefreshDatabase',
        'DatabaseMigrations' => 'DatabaseMigrations',
        'DatabaseTruncation' => 'DatabaseTruncation',
    ];

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * @param  list<string>  $paths
     * @return Fluent<array-key, mixed>
     */
    public function scan(array $paths = []): Fluent
    {
        $warnings = [];
        $targetFiles = $this->resolveTargetFiles($paths, $warnings);
        $pestPath = base_path('tests/Pest.php');
        $pestContents = $this->files->exists($pestPath)
            ? $this->files->get($pestPath)
            : '';
        $globalProtections = $this->globalProtections($pestContents);
        $mutatingFiles = [];
        $unguardedFiles = [];

        foreach ($targetFiles as $path) {
            $contents = $this->files->get($path);
            $signals = $this->mutationSignals($contents);

            if ($signals === []) {
                continue;
            }

            $protections = array_values(array_unique([
                ...$globalProtections,
                ...$this->localProtections($contents),
            ]));

            $entry = [
                'path' => $this->relativePath($path),
                'protections' => $protections,
                'signals' => $signals,
                'unguarded' => $protections === [],
            ];

            $mutatingFiles[] = $entry;

            if ($entry['unguarded']) {
                $unguardedFiles[] = $entry;
            }
        }

        usort($mutatingFiles, fn (array $left, array $right): int => $left['path'] <=> $right['path']);
        usort($unguardedFiles, fn (array $left, array $right): int => $left['path'] <=> $right['path']);
        sort($warnings);

        return new Fluent([
            'global_protections' => $globalProtections,
            'mutating_files' => $mutatingFiles,
            'mutating_files_count' => count($mutatingFiles),
            'scanned_files' => count($targetFiles),
            'scanned_paths' => array_map($this->relativePath(...), $this->resolveTargetPaths($paths)),
            'unguarded_files' => $unguardedFiles,
            'unguarded_files_count' => count($unguardedFiles),
            'warnings' => array_values(array_unique($warnings)),
        ]);
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $warnings
     * @return list<string>
     */
    private function resolveTargetFiles(array $paths, array &$warnings): array
    {
        $files = [];

        foreach ($this->resolveTargetPaths($paths) as $path) {
            if (! $this->files->exists($path)) {
                $warnings[] = 'Path not found: '.$this->relativePath($path);

                continue;
            }

            if ($this->files->isFile($path)) {
                if (Str::endsWith($path, '.php')) {
                    $files[] = $path;
                }

                continue;
            }

            foreach ($this->files->allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function resolveTargetPaths(array $paths): array
    {
        if ($paths === []) {
            return [base_path('tests/Feature')];
        }

        return array_values(array_unique(array_map(function (string $path): string {
            return Str::startsWith($path, base_path())
                ? $path
                : base_path($path);
        }, $paths)));
    }

    /**
     * @return list<string>
     */
    private function mutationSignals(string $contents): array
    {
        $signals = [];

        foreach (self::MUTATION_PATTERNS as $label => $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                $signals[] = $label;
            }
        }

        return $signals;
    }

    /**
     * @return list<string>
     */
    private function globalProtections(string $contents): array
    {
        $protections = [];

        if ($this->appliesStrategyGlobally($contents, 'RefreshDatabase')) {
            $protections[] = 'tests/Pest.php applies RefreshDatabase to Feature tests';
        }

        if ($this->appliesStrategyGlobally($contents, 'DatabaseMigrations')) {
            $protections[] = 'tests/Pest.php applies DatabaseMigrations to Feature tests';
        }

        if ($this->appliesStrategyGlobally($contents, 'DatabaseTruncation')) {
            $protections[] = 'tests/Pest.php applies DatabaseTruncation to Feature tests';
        }

        return $protections;
    }

    /**
     * @return list<string>
     */
    private function localProtections(string $contents): array
    {
        $protections = [];

        foreach (self::RESET_STRATEGIES as $strategy) {
            if (
                preg_match('/uses\s*\([^;]*'.preg_quote($strategy, '/').'::class/s', $contents) === 1
                || preg_match('/use\s+'.preg_quote($strategy, '/').'\s*;/', $contents) === 1
            ) {
                $protections[] = $strategy;
            }
        }

        return $protections;
    }

    private function appliesStrategyGlobally(string $contents, string $strategy): bool
    {
        return preg_match('/->use\s*\([^;]*'.preg_quote($strategy, '/').'::class[^;]*\)\s*->in\s*\([^;]*[\'"]Feature[\'"]/s', $contents) === 1;
    }

    private function relativePath(string $path): string
    {
        return Str::replaceFirst(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
