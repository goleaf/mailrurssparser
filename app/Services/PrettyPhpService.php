<?php

namespace App\Services;

use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class PrettyPhpService
{
    /**
     * @param  array<int, string>  $paths
     */
    public function command(
        array $paths = [],
        bool $check = false,
        bool $diff = false,
        ?string $binaryOverride = null,
    ): string {
        $binary = $this->resolveBinary($binaryOverride);

        if ($binary === null) {
            throw new RuntimeException(
                'pretty-php executable not found. Install it via PHIVE, Homebrew, or a local PHAR, or pass --binary=/path/to/pretty-php.',
            );
        }

        $segments = [];

        if ($this->shouldInvokeWithPhp($binary)) {
            $segments[] = escapeshellarg(PHP_BINARY);
        }

        $segments[] = $this->escapeBinary($binary);

        $configPath = $this->resolveConfigPath();

        if ($configPath !== null) {
            $segments[] = '--config='.escapeshellarg($configPath);
        }

        if ($check) {
            $segments[] = '--check';
        }

        if ($diff) {
            $segments[] = '--diff';
        }

        foreach ($this->normalizePaths($paths) as $path) {
            $segments[] = escapeshellarg($path);
        }

        return implode(' ', $segments);
    }

    public function run(string $command): ProcessResultContract
    {
        $result = Process::path(base_path())->run($command);

        if ($result->failed() && $this->binaryWasNotFound($result)) {
            throw new RuntimeException(
                'pretty-php executable could not be started. Install it via PHIVE, Homebrew, or a local PHAR, or pass --binary=/path/to/pretty-php.',
            );
        }

        return $result;
    }

    private function resolveBinary(?string $binaryOverride): ?string
    {
        $candidates = $binaryOverride !== null && trim($binaryOverride) !== ''
            ? [$binaryOverride]
            : $this->configuredStringList('pretty_php.binary_candidates');

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);

            if ($candidate === '') {
                continue;
            }

            if ($this->isPathLike($candidate)) {
                $resolvedPath = $this->resolvePath($candidate);

                if (is_file($resolvedPath)) {
                    return $resolvedPath;
                }

                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function normalizePaths(array $paths): array
    {
        $resolved = array_values(array_filter(
            array_map(
                fn (mixed $path): string => is_string($path) ? trim($path) : '',
                $paths,
            ),
            fn (string $path): bool => $path !== '',
        ));

        if ($resolved !== []) {
            return $resolved;
        }

        return $this->configuredStringList('pretty_php.default_paths', ['.']);
    }

    private function resolveConfigPath(): ?string
    {
        $configured = config('pretty_php.config_path');

        if (! is_string($configured) || trim($configured) === '') {
            return null;
        }

        $resolvedPath = $this->resolvePath($configured);

        return is_file($resolvedPath) ? $resolvedPath : null;
    }

    private function resolvePath(string $path): string
    {
        if (Str::startsWith($path, [DIRECTORY_SEPARATOR])) {
            return $path;
        }

        if (Str::startsWith($path, ['./', '../'])) {
            return base_path($path);
        }

        if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function shouldInvokeWithPhp(string $binary): bool
    {
        return Str::endsWith(Str::lower($binary), '.phar');
    }

    private function escapeBinary(string $binary): string
    {
        return preg_match('/^[A-Za-z0-9._-]+$/', $binary) === 1
            ? $binary
            : escapeshellarg($binary);
    }

    private function isPathLike(string $candidate): bool
    {
        return Str::contains($candidate, ['/', '\\']) || Str::endsWith(Str::lower($candidate), '.phar');
    }

    private function binaryWasNotFound(ProcessResultContract $result): bool
    {
        $message = trim($result->errorOutput().' '.$result->output());

        return Str::contains(Str::lower($message), [
            'command not found',
            'not found',
            'no such file or directory',
            'not recognized as an internal or external command',
        ]);
    }

    /**
     * @param  array<int, string>  $default
     * @return array<int, string>
     */
    private function configuredStringList(string $key, array $default = []): array
    {
        return Config::collection($key, $default)
            ->map(fn (mixed $value): string => is_string($value) ? trim($value) : '')
            ->filter()
            ->values()
            ->all();
    }
}
