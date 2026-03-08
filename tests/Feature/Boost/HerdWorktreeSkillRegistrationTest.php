<?php

use Symfony\Component\Process\Process;

function localWorktreeBoostSkill(): array
{
    $process = new Process([
        'php',
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $skill = $app->make(Laravel\Boost\Install\SkillComposer::class)->skills()->get('laravel-herd-worktree');
        echo json_encode(['skill' => [
            'name' => $skill?->name,
            'package' => $skill?->package,
            'path' => $skill?->path,
            'description' => $skill?->description,
            'custom' => $skill?->custom,
        ]], JSON_THROW_ON_ERROR);
        PHP,
    ], base_path(), [
        'APP_ENV' => 'local',
    ]);

    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['skill'];
}

it('discovers the custom herd worktree boost skill', function () {
    $skill = localWorktreeBoostSkill();

    expect($skill)
        ->not()->toBeNull()
        ->and($skill['custom'])->toBeTrue()
        ->and($skill['name'])->toBe('laravel-herd-worktree')
        ->and($skill['description'])->toContain('Laravel Herd worktrees')
        ->and($skill['path'])->toContain('laravel-herd-worktree');
});

it('registers the herd worktree skill in the project AI context files', function () {
    expect(base_path('.ai/skills/laravel-herd-worktree/SKILL.md'))->toBeFile()
        ->and(file_get_contents(base_path('AGENTS.md')))
        ->toContain('laravel-herd-worktree')
        ->and(file_get_contents(base_path('CLAUDE.md')))
        ->toContain('laravel-herd-worktree')
        ->and(file_get_contents(base_path('boost.json')))
        ->toContain('"laravel-herd-worktree"');
});

it('adds the local worktree defaults to gitignore and vite config', function () {
    expect(file_get_contents(base_path('.gitignore')))
        ->toContain('/.worktrees')
        ->and(file_get_contents(base_path('vite.config.ts')))
        ->toContain("host: 'localhost'")
        ->toContain('cors: true');
});
