---
name: laravel-herd-worktree
description: "Use when creating, inspecting, or removing Laravel Herd worktrees for this repository, including isolated .env setup, SQLite cloning, and Herd site naming."
---
# Laravel Herd Worktree

Use this skill when the task is about isolated branch workspaces under Laravel Herd.

## Project-Specific Workflow

1. Preview the plan first:
   - `php artisan herd:worktree setup <branch> --dry-run`
2. Create the worktree when the plan looks correct:
   - `php artisan herd:worktree setup <branch>`
3. Remove it when the branch is done:
   - `php artisan herd:worktree teardown <branch> --delete-branch`

## Project Rules

- This repository is served by Laravel Herd.
- The automation command is `php artisan herd:worktree`.
- Worktrees live under `/.worktrees` by default.
- This app uses SQLite locally, so each worktree should get its own `database/<site>.sqlite` file.
- Do not add Sanctum-specific env keys for this project; Sanctum is not installed here.
- Vite is already configured for Herd worktrees with `host: 'localhost'` and `cors: true`.

## Options

- Use `--project=<name>` to override the site prefix.
- Use `--path=<dir>` to store worktrees somewhere other than `/.worktrees`.
- Use `--no-link` to skip `herd link`.
- Use `--no-install` to skip Composer and npm bootstrapping.
- Use `--no-migrate` to skip `php artisan migrate`.

## Verification

- For command changes, run the focused Pest worktree tests.
- For Vite-related changes, run `npm run build`.
