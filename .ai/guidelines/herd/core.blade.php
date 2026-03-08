# Laravel Herd

- The application is served by Laravel Herd and will be available at: `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs for the user.
- You must not run any commands to make the site available via HTTP(S). It is always available through Laravel Herd.
- This repository has project-specific worktree automation through `php artisan herd:worktree`. Prefer that command over ad-hoc manual worktree setup when the task is about isolated branch environments.
- Worktrees live under `/.worktrees` by default, and each local worktree should keep its own SQLite database file under `database/<site>.sqlite`.
- Do not add Sanctum-specific environment keys when preparing Herd worktrees for this app. Sanctum is not installed here.
- Vite is already configured for Herd worktrees with `host: 'localhost'` and `cors: true`, so do not introduce extra Herd-only frontend host workarounds unless you verify the existing setup is insufficient.
