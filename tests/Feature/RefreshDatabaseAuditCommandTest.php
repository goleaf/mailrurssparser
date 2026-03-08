<?php

use App\Services\FeatureTestRefreshDatabaseAuditService;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

beforeEach(function () {
    $this->auditPestContents = File::get(base_path('tests/Pest.php'));
    refreshDatabaseAuditCleanup();
});

afterEach(function () {
    File::put(base_path('tests/Pest.php'), $this->auditPestContents);
    refreshDatabaseAuditCleanup();
});

it('passes when feature tests are globally protected by refresh database', function () {
    refreshDatabaseAuditPutFeatureTest(<<<'PHP'
<?php

use App\Models\Article;

it('creates a record', function () {
    Article::factory()->create();
});
PHP);

    $this->artisan('test:audit-refresh-database --path=tests/Feature/__refresh_database_audit')
        ->expectsOutputToContain('Global protection detected: tests/Pest.php applies RefreshDatabase to Feature tests')
        ->expectsOutputToContain('No unguarded feature tests found.')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

it('fails when a mutating feature test is not protected by a reset strategy', function () {
    refreshDatabaseAuditDisableGlobalRefreshDatabase();

    refreshDatabaseAuditPutFeatureTest(<<<'PHP'
<?php

use App\Models\Article;

it('creates a record without protection', function () {
    Article::factory()->create();
});
PHP);

    $this->artisan('test:audit-refresh-database --path=tests/Feature/__refresh_database_audit --json')
        ->assertExitCode(SymfonyCommand::FAILURE);

    $report = app(FeatureTestRefreshDatabaseAuditService::class)
        ->scan(['tests/Feature/__refresh_database_audit']);

    expect($report->unguarded_files_count)->toBe(1)
        ->and($report->mutating_files_count)->toBe(1)
        ->and($report->unguarded_files[0]['path'])->toBe('tests/Feature/__refresh_database_audit/LegacyMutationFeatureTest.php')
        ->and($report->unguarded_files[0]['signals'])->toBe(['factory->create']);
});

it('accepts locally guarded feature tests when the global protection is absent', function () {
    refreshDatabaseAuditDisableGlobalRefreshDatabase();

    refreshDatabaseAuditPutFeatureTest(<<<'PHP'
<?php

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a record with local protection', function () {
    Article::factory()->create();
});
PHP);

    $this->artisan('test:audit-refresh-database --path=tests/Feature/__refresh_database_audit')
        ->expectsOutputToContain('No global database reset protection was detected in tests/Pest.php for Feature tests.')
        ->expectsOutputToContain('No unguarded feature tests found.')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

it('runs the refresh database audit in the ci workflow', function () {
    expect(File::get(base_path('.github/workflows/tests.yml')))
        ->toContain('php artisan test:audit-refresh-database');
});

function refreshDatabaseAuditDisableGlobalRefreshDatabase(): void
{
    $contents = File::get(base_path('tests/Pest.php'));
    $contents = str_replace("use Illuminate\\Foundation\\Testing\\RefreshDatabase;\n", '', $contents);
    $contents = str_replace('RefreshDatabase::class, ', '', $contents);

    File::put(base_path('tests/Pest.php'), $contents);
}

function refreshDatabaseAuditPutFeatureTest(string $contents): void
{
    $path = base_path('tests/Feature/__refresh_database_audit/LegacyMutationFeatureTest.php');

    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);
}

function refreshDatabaseAuditCleanup(): void
{
    File::delete(base_path('tests/Feature/__refresh_database_audit/LegacyMutationFeatureTest.php'));
    File::deleteDirectory(base_path('tests/Feature/__refresh_database_audit'));
}
