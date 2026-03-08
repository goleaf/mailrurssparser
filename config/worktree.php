<?php

return [
    'base_path' => env('WORKTREE_BASE_PATH', '.worktrees'),

    'default_base_branch' => env('WORKTREE_DEFAULT_BASE_BRANCH'),

    'env_source' => env('WORKTREE_ENV_SOURCE'),

    'bootstrap_commands' => [
        'composer install --no-interaction',
        'npm install',
        'php artisan config:clear',
        'php artisan cache:clear',
    ],
];
