<?php

use App\Services\StorageDisk;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

beforeEach(function () {
    assetCleanerDeleteArtifacts();
});

afterEach(function () {
    assetCleanerDeleteArtifacts();
    $this->travelBack();
});

it('scans stale build and public assets', function () {
    assetCleanerPutFile('build/assets/legacy-unused-scan.js', 'console.log("stale");');
    assetCleanerPutFile('icons/legacy-unused-scan.png', 'png');

    $this->artisan('assets:scan')
        ->expectsOutputToContain('build/assets/legacy-unused-scan.js')
        ->expectsOutputToContain('icons/legacy-unused-scan.png')
        ->expectsOutputToContain('Potential savings:')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

it('filters scan results by asset type', function () {
    assetCleanerPutFile('build/assets/legacy-filter.js', 'console.log("js");');
    assetCleanerPutFile('build/assets/legacy-filter.css', 'body { color: red; }');

    $this->artisan('assets:scan --type=build-js')
        ->expectsOutputToContain('build/assets/legacy-filter.js')
        ->doesntExpectOutputToContain('build/assets/legacy-filter.css')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

it('fails when the requested asset type is unknown', function () {
    $this->artisan('assets:scan --type=unknown')
        ->expectsOutputToContain('Unknown asset type(s): unknown.')
        ->assertExitCode(SymfonyCommand::FAILURE);
});

it('deletes stale assets and creates a backup by default', function () {
    $this->travelTo(now()->setTime(12, 0, 0));

    assetCleanerPutFile('build/assets/legacy-delete.js', 'console.log("delete");');

    $this->artisan('assets:delete --force')
        ->expectsOutputToContain('Deleted 1 stale assets')
        ->expectsOutputToContain('storage/app/private/asset-cleaner/20260308_120000_000000')
        ->assertExitCode(SymfonyCommand::SUCCESS);

    expect(public_path('build/assets/legacy-delete.js'))->not->toBeFile();

    Storage::disk(StorageDisk::Local)->assertExists(
        'asset-cleaner/20260308_120000_000000/build/assets/legacy-delete.js',
    );
});

it('supports dry-run cleanup without deleting files', function () {
    assetCleanerPutFile('build/assets/legacy-dry-run.css', 'body { color: blue; }');

    $this->artisan('assets:delete --dry-run')
        ->expectsOutputToContain('Dry run: 1 stale assets would be removed.')
        ->expectsOutputToContain('build/assets/legacy-dry-run.css')
        ->assertExitCode(SymfonyCommand::SUCCESS);

    expect(public_path('build/assets/legacy-dry-run.css'))->toBeFile()
        ->and(Storage::disk(StorageDisk::Local)->exists('asset-cleaner'))
        ->toBeFalse();
});

function assetCleanerPutFile(string $relativePath, string $contents): void
{
    $absolutePath = public_path($relativePath);

    File::ensureDirectoryExists(dirname($absolutePath));
    File::put($absolutePath, $contents);
}

function assetCleanerDeleteArtifacts(): void
{
    foreach ([
        public_path('build/assets/legacy-delete.js'),
        public_path('build/assets/legacy-dry-run.css'),
        public_path('build/assets/legacy-filter.css'),
        public_path('build/assets/legacy-filter.js'),
        public_path('build/assets/legacy-unused-scan.js'),
        public_path('icons/legacy-unused-scan.png'),
    ] as $path) {
        File::delete($path);
    }

    Storage::disk(StorageDisk::Local)->deleteDirectory('asset-cleaner');
}
