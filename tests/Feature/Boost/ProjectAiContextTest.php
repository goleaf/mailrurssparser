<?php

use Symfony\Component\Process\Process;

function localBoostGuideline(string $key): array
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

function localBoostSkill(string $name): array
{
    $process = new Process([
        'php',
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $skill = $app->make(Laravel\Boost\Install\SkillComposer::class)->skills()->get($argv[1]);
        echo json_encode(['skill' => [
            'name' => $skill?->name,
            'package' => $skill?->package,
            'path' => $skill?->path,
            'description' => $skill?->description,
            'custom' => $skill?->custom,
        ]], JSON_THROW_ON_ERROR);
        PHP,
        $name,
    ], base_path(), [
        'APP_ENV' => 'local',
    ]);

    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['skill'];
}

function localPackageSpecificBoostSkill(string $name): array
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
        echo json_encode(['skill' => [
            'name' => $skill?->name,
            'package' => $skill?->package,
            'path' => $skill?->path,
            'description' => $skill?->description,
            'custom' => $skill?->custom,
        ]], JSON_THROW_ON_ERROR);
        PHP,
        $name,
    ], base_path(), [
        'APP_ENV' => 'local',
    ]);

    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['skill'];
}

it('uses the project override for the inertia laravel guideline', function () {
    $guideline = localBoostGuideline('inertia-laravel/core');

    expect($guideline)
        ->not()->toBeNull()
        ->and($guideline['custom'])
        ->toBeTrue()
        ->and($guideline['path'])
        ->toEndWith('/.ai/guidelines/inertia-laravel/core.blade.php')
        ->and($guideline['content'])
        ->toContain('public frontend through the Inertia `Welcome` page')
        ->toContain('tests/Feature/ExampleTest.php');
});

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
        ->toContain('ProfileController.update.form()');
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
        ->toContain('assertInertia(fn (Assert $page) => ...)')
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
        ->toContain('BreakingNewsTicker.svelte');
});

it('uses the project override for the inertia svelte guideline', function () {
    $guideline = localBoostGuideline('inertia-svelte/core');

    expect($guideline)
        ->not()->toBeNull()
        ->and($guideline['custom'])
        ->toBeTrue()
        ->and($guideline['path'])
        ->toEndWith('/.ai/guidelines/inertia-svelte/core.blade.php')
        ->and($guideline['content'])
        ->toContain('hybrid public frontend')
        ->toContain('resources/js/pages/Welcome.svelte')
        ->toContain('AppRoot.svelte')
        ->toContain('sw:update-ready');
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
        ->toContain('public news portal frontend');
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

it('discovers the versioned inertia svelte news portal frontend boost skill', function () {
    $skill = localPackageSpecificBoostSkill('news-portal-frontend');

    expect($skill)
        ->not()->toBeNull()
        ->and($skill['custom'])
        ->toBeTrue()
        ->and($skill['package'])
        ->toBe('inertia-svelte')
        ->and($skill['path'])
        ->toEndWith('/.ai/inertia-svelte/2/skill/news-portal-frontend')
        ->and($skill['description'])
        ->toContain('public news portal frontend');
});

it('regenerates agent guidance with the custom inertia context', function () {
    foreach (['AGENTS.md', 'CLAUDE.md'] as $filename) {
        $contents = file_get_contents(base_path($filename));

        expect($contents)
            ->not->toBeFalse()
            ->toContain('=== herd rules ===')
            ->toContain('php artisan herd:worktree')
            ->toContain('=== wayfinder/core rules ===')
            ->toContain('Normalize Wayfinder route objects to strings with `toUrl()`')
            ->toContain('=== pest/core rules ===')
            ->toContain('assertInertia(fn (Assert $page) => ...)')
            ->toContain('=== tailwindcss/core rules ===')
            ->toContain('resources/css/app.css')
            ->toContain('=== inertia-laravel/core rules ===')
            ->toContain('public frontend through the Inertia `Welcome` page')
            ->toContain('=== inertia-svelte/core rules ===')
            ->toContain('hybrid public frontend')
            ->toContain('AppRoot.svelte')
            ->toContain('news-portal-frontend')
            ->toContain('laravel-herd-worktree');
    }
});
