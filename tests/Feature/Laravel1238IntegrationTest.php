<?php

use App\Models\Article;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

it('prompts for a model when model show is missing an argument', function () {
    $this->artisan('model:show --json')
        ->expectsQuestion('Which model would you like to show?', Article::class)
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

it('renders application model details through model show json output', function () {
    Artisan::call('model:show', [
        'model' => Article::class,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload)
        ->toMatchArray([
            'class' => Article::class,
            'table' => 'articles',
            'database' => 'sqlite',
        ]);
});
