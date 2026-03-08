<?php

use Laravel\Boost\Install\SkillComposer;
use Symfony\Component\Process\Process;

it('uses the project override for the inertia svelte guideline', function () {
    $process = new Process([
        'php',
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $guideline = $app->make(Laravel\Boost\Install\GuidelineComposer::class)->guidelines()->get('inertia-svelte/core');
        echo json_encode(['guideline' => $guideline], JSON_THROW_ON_ERROR);
        PHP,
    ], base_path(), [
        'APP_ENV' => 'local',
    ]);

    $process->mustRun();

    $guideline = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['guideline'];

    expect($guideline)
        ->not()->toBeNull()
        ->and($guideline['custom'])
        ->toBeTrue()
        ->and($guideline['path'])
        ->toEndWith('/.ai/guidelines/inertia-svelte/core.blade.php')
        ->and($guideline['content'])
        ->toContain('hybrid public frontend')
        ->toContain('resources/js/pages/Welcome.svelte');
});

it('discovers the custom news portal frontend boost skill', function () {
    $skill = app(SkillComposer::class)
        ->skills()
        ->get('news-portal-frontend');

    expect($skill)
        ->not()->toBeNull()
        ->and($skill->custom)
        ->toBeTrue()
        ->and($skill->package)
        ->toBe('user')
        ->and($skill->path)
        ->toEndWith('/.ai/skills/news-portal-frontend')
        ->and($skill->description)
        ->toContain('public news portal frontend');
});
