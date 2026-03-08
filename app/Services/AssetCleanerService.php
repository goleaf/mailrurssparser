<?php

namespace App\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Fluent;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;

class AssetCleanerService
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * @param  list<string>  $requestedTypes
     * @return Fluent<array-key, mixed>
     */
    public function scan(array $requestedTypes = []): Fluent
    {
        $types = $this->resolveTypes($requestedTypes);
        $protectedFiles = $this->protectedFiles();
        $protectedLookup = array_fill_keys($protectedFiles, true);
        $manifestState = $this->viteManifestState();
        $projectReferenceContents = null;
        $scannedFiles = 0;
        $reclaimableBytes = 0;
        $warnings = $manifestState['warnings'];
        $findings = [];

        foreach ($types as $type) {
            $definition = $this->typeDefinition($type);

            if ($definition['reference_source'] === 'vite-manifest' && ! $manifestState['available']) {
                $warnings[] = "Skipped {$type} because public/build/manifest.json is missing or invalid.";

                continue;
            }

            if ($definition['reference_source'] === 'project-files' && $projectReferenceContents === null) {
                $projectReferenceContents = $this->projectReferenceContents();
            }

            foreach ($this->candidateFilesForType($type) as $file) {
                $relativePath = $this->relativePublicPath($file->getPathname());

                if (isset($protectedLookup[$relativePath])) {
                    continue;
                }

                $scannedFiles++;

                $isReferenced = match ($definition['reference_source']) {
                    'vite-manifest' => isset($manifestState['files'][$relativePath]),
                    'project-files' => $projectReferenceContents !== null
                        && $this->isReferencedByProject($relativePath, $projectReferenceContents),
                    default => false,
                };

                if ($isReferenced) {
                    continue;
                }

                $size = (int) $file->getSize();
                $reclaimableBytes += $size;

                $findings[] = [
                    'absolute_path' => $file->getPathname(),
                    'reason' => (string) $definition['reason'],
                    'relative_path' => $relativePath,
                    'size' => $size,
                    'type' => $type,
                ];
            }
        }

        usort($findings, function (array $left, array $right): int {
            return [$left['type'], $left['relative_path']] <=> [$right['type'], $right['relative_path']];
        });

        $warnings = array_values(array_unique($warnings));
        sort($warnings);

        return new Fluent([
            'files' => $findings,
            'protected_files' => $protectedFiles,
            'reclaimable_bytes' => $reclaimableBytes,
            'scanned_files' => $scannedFiles,
            'stale_files' => count($findings),
            'types' => $types,
            'warnings' => $warnings,
        ]);
    }

    /**
     * @param  list<array{
     *     absolute_path: string,
     *     reason: string,
     *     relative_path: string,
     *     size: int,
     *     type: string
     * }>  $files
     * @return Fluent<array-key, mixed>
     */
    public function delete(array $files, bool $backup = true): Fluent
    {
        $deletedFiles = 0;
        $reclaimedBytes = 0;
        $backupPath = $backup
            ? rtrim((string) config('asset-cleaner.backup_path'), DIRECTORY_SEPARATOR)
                .DIRECTORY_SEPARATOR.now()->format('Ymd_His_u')
            : null;

        foreach ($files as $file) {
            $absolutePath = (string) $file['absolute_path'];

            if (! $this->files->exists($absolutePath)) {
                continue;
            }

            if ($backup && $backupPath !== null) {
                $backupTarget = $backupPath.DIRECTORY_SEPARATOR.$this->normalizePath((string) $file['relative_path']);

                $this->files->ensureDirectoryExists(dirname($backupTarget));
                $this->files->copy($absolutePath, $backupTarget);
            }

            $this->files->delete($absolutePath);

            $deletedFiles++;
            $reclaimedBytes += (int) $file['size'];
        }

        return new Fluent([
            'backup_path' => $backupPath,
            'deleted_files' => $deletedFiles,
            'reclaimed_bytes' => $reclaimedBytes,
        ]);
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;

        foreach ($units as $index => $unit) {
            $isLastUnit = $index === array_key_last($units);

            if ($size < 1024 || $isLastUnit) {
                return number_format($size, 1).' '.$unit;
            }

            $size /= 1024;
        }

        return $bytes.' B';
    }

    /**
     * @param  list<string>  $requestedTypes
     * @return list<string>
     */
    private function resolveTypes(array $requestedTypes): array
    {
        $availableTypes = array_keys($this->typeDefinitions());
        $normalized = array_values(array_unique(array_filter(array_map(
            fn (mixed $type): string => trim((string) $type),
            $requestedTypes,
        ))));
        $normalizedTypes = new Fluent($normalized);

        if ($normalizedTypes->isEmpty()) {
            return $availableTypes;
        }

        $invalid = array_values(array_diff($normalized, $availableTypes));
        $invalidTypes = new Fluent($invalid);

        if ($invalidTypes->isNotEmpty()) {
            $availableList = implode(', ', $availableTypes);
            $invalidList = implode(', ', $invalid);

            throw new RuntimeException("Unknown asset type(s): {$invalidList}. Available types: {$availableList}");
        }

        return $normalized;
    }

    /**
     * @return array<string, array{
     *     extensions: list<string>,
     *     reason: string,
     *     reference_source: string,
     *     roots: list<array{path: string, recursive: bool}>
     * }>
     */
    private function typeDefinitions(): array
    {
        return Config::collection('asset-cleaner.types', [])
            ->filter(fn (mixed $definition, mixed $type): bool => is_string($type) && is_array($definition))
            ->all();
    }

    /**
     * @return array{
     *     extensions: list<string>,
     *     reason: string,
     *     reference_source: string,
     *     roots: list<array{path: string, recursive: bool}>
     * }
     */
    private function typeDefinition(string $type): array
    {
        $definitions = $this->typeDefinitions();

        if (! isset($definitions[$type]) || ! is_array($definitions[$type])) {
            throw new RuntimeException("Asset type [{$type}] is not configured.");
        }

        return $definitions[$type];
    }

    /**
     * @return list<SplFileInfo>
     */
    private function candidateFilesForType(string $type): array
    {
        $definition = $this->typeDefinition($type);
        $extensions = array_map(
            fn (string $extension): string => strtolower(ltrim($extension, '.')),
            $definition['extensions'],
        );
        $seen = [];
        $files = [];

        foreach ($definition['roots'] as $root) {
            $directory = public_path($root['path']);

            if (! $this->files->isDirectory($directory)) {
                continue;
            }

            $candidates = $root['recursive']
                ? $this->files->allFiles($directory)
                : $this->files->files($directory);

            foreach ($candidates as $candidate) {
                $extension = strtolower($candidate->getExtension());

                if (! in_array($extension, $extensions, true)) {
                    continue;
                }

                $relativePath = $this->relativePublicPath($candidate->getPathname());

                if (isset($seen[$relativePath])) {
                    continue;
                }

                $seen[$relativePath] = true;
                $files[] = $candidate;
            }
        }

        return $files;
    }

    /**
     * @return array{available: bool, files: array<string, true>, warnings: list<string>}
     */
    private function viteManifestState(): array
    {
        $manifestPath = public_path('build/manifest.json');

        if (! $this->files->exists($manifestPath)) {
            return [
                'available' => false,
                'files' => [],
                'warnings' => ['public/build/manifest.json was not found. Build asset cleanup was skipped.'],
            ];
        }

        $decodedManifest = json_decode((string) $this->files->get($manifestPath), true);

        if (! is_array($decodedManifest)) {
            return [
                'available' => false,
                'files' => [],
                'warnings' => ['public/build/manifest.json is not valid JSON. Build asset cleanup was skipped.'],
            ];
        }

        $files = ['build/manifest.json' => true];

        foreach ($decodedManifest as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $this->collectManifestFiles($entry, $files);
        }

        return [
            'available' => true,
            'files' => $files,
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, true>  $files
     */
    private function collectManifestFiles(array $entry, array &$files): void
    {
        foreach (['file'] as $key) {
            if (isset($entry[$key]) && is_string($entry[$key]) && $entry[$key] !== '') {
                $files['build/'.$this->normalizePath($entry[$key])] = true;
            }
        }

        foreach (['css', 'assets'] as $key) {
            if (! isset($entry[$key]) || ! is_array($entry[$key])) {
                continue;
            }

            foreach ($entry[$key] as $file) {
                if (is_string($file) && $file !== '') {
                    $files['build/'.$this->normalizePath($file)] = true;
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function projectReferenceContents(): array
    {
        $referencePaths = Config::collection('asset-cleaner.reference_paths', [])
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->all();
        $referenceExtensions = Config::collection('asset-cleaner.reference_extensions', [])
            ->map(fn (mixed $extension): string => strtolower(ltrim((string) $extension, '.')))
            ->filter()
            ->values()
            ->all();
        $contents = [];

        foreach ($referencePaths as $path) {
            if ($this->files->isFile($path)) {
                $contents[] = (string) $this->files->get($path);

                continue;
            }

            if (! $this->files->isDirectory($path)) {
                continue;
            }

            foreach ($this->files->allFiles($path) as $file) {
                $extension = strtolower($file->getExtension());

                if (! in_array($extension, $referenceExtensions, true)) {
                    continue;
                }

                $contents[] = (string) $this->files->get($file->getPathname());
            }
        }

        return $contents;
    }

    /**
     * @param  list<string>  $referenceContents
     */
    private function isReferencedByProject(string $relativePath, array $referenceContents): bool
    {
        $normalizedPath = $this->normalizePath($relativePath);
        $needles = array_values(array_unique([
            $normalizedPath,
            '/'.$normalizedPath,
            '"'.$normalizedPath.'"',
            "'".$normalizedPath."'",
            '`'.$normalizedPath.'`',
            '"/'.$normalizedPath.'"',
            "'/".$normalizedPath."'",
            '`/'.$normalizedPath.'`',
        ]));

        foreach ($referenceContents as $contents) {
            foreach ($needles as $needle) {
                if (str_contains($contents, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function protectedFiles(): array
    {
        $normalized = Config::collection('asset-cleaner.protected_files', [])
            ->filter(fn (mixed $file): bool => is_string($file) && $file !== '')
            ->map(fn (string $file): string => $this->normalizePath($file))
            ->unique()
            ->values()
            ->all();

        sort($normalized);

        return $normalized;
    }

    private function relativePublicPath(string $absolutePath): string
    {
        return $this->normalizePath(substr($absolutePath, strlen(public_path()) + 1));
    }

    private function normalizePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', $path), '/');
    }
}
