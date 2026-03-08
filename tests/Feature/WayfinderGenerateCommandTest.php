<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

beforeEach(function () {
    File::deleteDirectory(base_path('storage/framework/testing-wayfinder'));
});

afterEach(function () {
    File::deleteDirectory(base_path('storage/framework/testing-wayfinder'));
});

it('generates routes and actions with form variants into a temporary path', function () {
    $outputPath = base_path('storage/framework/testing-wayfinder');

    $exitCode = Artisan::call('wayfinder:generate', [
        '--path' => $outputPath,
        '--with-form' => true,
    ]);

    expect($exitCode)->toBe(SymfonyCommand::SUCCESS)
        ->and($outputPath.'/routes/index.ts')->toBeFile()
        ->and($outputPath.'/routes/profile/index.ts')->toBeFile()
        ->and($outputPath.'/actions/App/Http/Controllers/Settings/ProfileController.ts')->toBeFile();

    $profileController = File::get(
        $outputPath.'/actions/App/Http/Controllers/Settings/ProfileController.ts',
    );

    expect($profileController)
        ->toContain('update.form = updateForm')
        ->toContain('destroy.form = destroyForm');
});
