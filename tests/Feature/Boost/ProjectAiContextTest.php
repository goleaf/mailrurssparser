<?php

use Symfony\Component\Process\Process;

function localBoostGuideline(string $key): ?array
{
    $process = new Process([
        'php',
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $guideline = $app->make(Laravel\Boost\Install\GuidelineComposer::class)->guidelines()->get($argv[1]);
        echo json_encode(['guideline' => $guideline], JSON_THROW_ON_ERROR);
        PHP,
        $key,
    ], base_path(), [
        'APP_ENV' => 'local',
    ]);

    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['guideline'];
}

function localBoostSkill(string $name): ?array
{
    $process = new Process([
        'php',
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $skill = $app->make(Laravel\Boost\Install\SkillComposer::class)->skills()->get($argv[1]);
        echo json_encode(['skill' => $skill === null ? null : [
            'name' => $skill->name,
            'package' => $skill->package,
            'path' => $skill->path,
            'description' => $skill->description,
            'custom' => $skill->custom,
        ]], JSON_THROW_ON_ERROR);
        PHP,
        $name,
    ], base_path(), [
        'APP_ENV' => 'local',
    ]);

    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['skill'];
}

function localPackageSpecificBoostSkill(string $name): ?array
{
    $process = new Process([
        'php',
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $composer = $app->make(Laravel\Boost\Install\SkillComposer::class);
        $method = new ReflectionMethod($composer, 'discoverPackageSpecificUserSkills');
        $method->setAccessible(true);
        $skill = $method->invoke($composer)->get($argv[1]);
        echo json_encode(['skill' => $skill === null ? null : [
            'name' => $skill->name,
            'package' => $skill->package,
            'path' => $skill->path,
            'description' => $skill->description,
            'custom' => $skill->custom,
        ]], JSON_THROW_ON_ERROR);
        PHP,
        $name,
    ], base_path(), [
        'APP_ENV' => 'local',
    ]);

    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['skill'];
}

it('uses the project override for the herd guideline', function () {
    $guideline = localBoostGuideline('herd');

    expect($guideline)
        ->not()->toBeNull()
        ->and($guideline['custom'])
        ->toBeTrue()
        ->and($guideline['path'])
        ->toEndWith('/.ai/guidelines/herd/core.blade.php')
        ->and($guideline['content'])
        ->toContain('php artisan herd:worktree')
        ->toContain('database/<site>.sqlite');
});

it('uses the project override for the wayfinder guideline', function () {
    $guideline = localBoostGuideline('wayfinder/core');

    expect($guideline)
        ->not()->toBeNull()
        ->and($guideline['custom'])
        ->toBeTrue()
        ->and($guideline['path'])
        ->toEndWith('/.ai/guidelines/wayfinder/core.blade.php')
        ->and($guideline['content'])
        ->toContain('Normalize Wayfinder route objects to strings with `toUrl()`')
        ->toContain('TypeScript form helpers')
        ->toContain('admin login');
});

it('uses the project override for the pest guideline', function () {
    $guideline = localBoostGuideline('pest/core');

    expect($guideline)
        ->not()->toBeNull()
        ->and($guideline['custom'])
        ->toBeTrue()
        ->and($guideline['path'])
        ->toEndWith('/.ai/guidelines/pest/core.blade.php')
        ->and($guideline['content'])
        ->toContain('public Blade responses')
        ->toContain('Symfony\Component\Process\Process')
        ->toContain('APP_ENV=local');
});

it('uses the project override for the tailwind guideline', function () {
    $guideline = localBoostGuideline('tailwindcss/core');

    expect($guideline)
        ->not()->toBeNull()
        ->and($guideline['custom'])
        ->toBeTrue()
        ->and($guideline['path'])
        ->toEndWith('/.ai/guidelines/tailwindcss/core.blade.php')
        ->and($guideline['content'])
        ->toContain('resources/css/app.css')
        ->toContain('existing `cn()` helper pattern')
        ->toContain('Mary pagination tweaks');
});

it('discovers the custom news portal frontend boost skill', function () {
    $skill = localBoostSkill('news-portal-frontend');

    expect($skill)
        ->not()->toBeNull()
        ->and($skill['custom'])
        ->toBeTrue()
        ->and($skill['package'])
        ->toBe('user')
        ->and($skill['path'])
        ->toEndWith('/.ai/skills/news-portal-frontend')
        ->and($skill['description'])
        ->toContain('Blade + Mary UI news portal frontend');
});

it('discovers the custom laravel herd worktree boost skill', function () {
    $skill = localBoostSkill('laravel-herd-worktree');

    expect($skill)
        ->not()->toBeNull()
        ->and($skill['custom'])
        ->toBeTrue()
        ->and($skill['package'])
        ->toBe('user')
        ->and($skill['path'])
        ->toEndWith('/.ai/skills/laravel-herd-worktree')
        ->and($skill['description'])
        ->toContain('Laravel Herd worktrees');
});

it('does not keep custom guidance for the removed frontend stack', function () {
    $legacyUiExtension = 'sve'.'lte';
    $legacyFrontendGuideline = 'inertia-'.$legacyUiExtension.'/core';

    expect(localBoostGuideline('inertia-laravel/core'))->toBeNull()
        ->and(localBoostGuideline($legacyFrontendGuideline))->toBeNull()
        ->and(localPackageSpecificBoostSkill('news-portal-frontend'))->toBeNull();
});

it('regenerates agent guidance with the custom blade and Mary UI context', function () {
    $legacyUiExtension = 'sve'.'lte';
    $legacyFrontendSkill = 'inertia-'.$legacyUiExtension.'-development';
    $legacyFrontendRules = '=== inertia-'.$legacyUiExtension.'/core rules ===';
    $legacyAppRoot = 'AppRoot.'.$legacyUiExtension;
    $legacyTicker = 'BreakingNewsTicker.'.$legacyUiExtension;

    foreach (['AGENTS.md', 'CLAUDE.md'] as $filename) {
        $contents = file_get_contents(base_path($filename));

        expect($contents)
            ->not->toBeFalse()
            ->toContain('=== herd rules ===')
            ->toContain('php artisan herd:worktree')
            ->toContain('=== wayfinder/core rules ===')
            ->toContain('Normalize Wayfinder route objects to strings with `toUrl()`')
            ->toContain('=== pest/core rules ===')
            ->toContain('public Blade responses')
            ->toContain('=== tailwindcss/core rules ===')
            ->toContain('resources/css/app.css')
            ->toContain('news-portal-frontend')
            ->toContain('laravel-herd-worktree')
            ->not->toContain($legacyFrontendSkill)
            ->not->toContain('=== inertia-laravel/core rules ===')
            ->not->toContain($legacyFrontendRules)
            ->not->toContain('assertInertia(fn (Assert $page) => ...)')
            ->not->toContain($legacyAppRoot)
            ->not->toContain($legacyTicker);
    }
});
