<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5.2
- filament/filament (FILAMENT) - v5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/scout (SCOUT) - v10
- laravel/wayfinder (WAYFINDER) - v0
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/svelte (INERTIA_SVELTE) - v2
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `wayfinder-development` — Activates whenever referencing backend routes in frontend components. Use when importing from @/actions or @/routes, calling Laravel routes from TypeScript, or working with Wayfinder route functions.
- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `inertia-svelte-development` — Develops Inertia.js v2 Svelte client-side applications. Activates when creating Svelte pages, forms, or navigation; using Link, Form, or router; working with deferred props, prefetching, or polling; or when user mentions Svelte with Inertia, Svelte pages, Svelte forms, or Svelte navigation.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.
- `news-portal-frontend` — Works on this repository&#039;s public news portal frontend, including the hash-routed Welcome shell, homepage design, article/category/tag/search pages, and the shared public stores.
- `laravel-herd-worktree` — Use when creating, inspecting, or removing Laravel Herd worktrees for this repository, including isolated .env setup, SQLite cloning, and Herd site naming.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd and will be available at: `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs for the user.
- You must not run any commands to make the site available via HTTP(S). It is always available through Laravel Herd.
- This repository has project-specific worktree automation through `php artisan herd:worktree`. Prefer that command over ad-hoc manual worktree setup when the task is about isolated branch environments.
- Worktrees live under `/.worktrees` by default, and each local worktree should keep its own SQLite database file under `database/<site>.sqlite`.
- Do not add Sanctum-specific environment keys when preparing Herd worktrees for this app. Sanctum is not installed here.
- Vite is already configured for Herd worktrees with `host: 'localhost'` and `cors: true`, so do not introduce extra Herd-only frontend host workarounds unless you verify the existing setup is insufficient.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- IMPORTANT: Activate `inertia-svelte-development` when working with Inertia Svelte client-side patterns.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- This repository serves the public frontend through the Inertia `Welcome` page for both the `home` route (`/`) and the `spa` catch-all route (`/{any}`) defined in `routes/web.php`.
- Keep `sitemap.xml`, `rss.xml`, and `offline.html` as dedicated Laravel routes. Public news, category, tag, article, search, bookmark, and stats pages should stay in the client hash router unless a backend route is truly required.
- Prefer `Route::inertia()` for simple page routes, and keep the `home` and `spa` route names stable because tests and auth redirects depend on them.
- If you change the props passed to `Welcome`, keep them serializable and available anywhere the public shell is rendered.
- Prefer extending the existing `/api/v1/...` endpoints for public content data instead of introducing extra server-rendered Inertia pages.
- When changing public routing or the rendered Inertia page, update focused Pest coverage such as `tests/Feature/ExampleTest.php` and `tests/Feature/ApiRoutesTest.php`.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== wayfinder/core rules ===

# Laravel Wayfinder

Wayfinder generates TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

- IMPORTANT: Activate `wayfinder-development` skill whenever referencing backend routes in frontend components.
- In this repository, prefer named route imports from `@/routes` for navigation and controller imports from `@/actions/...` for form submissions or grouped controller actions.
- Normalize Wayfinder route objects to strings with `toUrl()` from `resources/js/lib/utils.ts` whenever a component prop, keyed `{#each}` block, or helper expects a plain URL string.
- Breadcrumbs and nav item `href` values may stay as Wayfinder objects in shared data structures, because layouts and helpers already normalize them downstream.
- For Svelte `<Form>` usage, follow the existing pattern of generated controller helpers such as `ProfileController.update.form()` instead of hand-writing action and method attributes.
- Avoid hardcoding internal app URLs like dashboard, login, register, profile, password, or verification routes when a generated helper already exists.
- Invokable Controllers: `import StorePost from '@/actions/.../StorePostController'; StorePost()`.
- Parameter Binding: Detects route keys (`{post:slug}`) — `show({ slug: "my-post" })`.
- Query Merging: `show(1, { mergeQuery: { page: 2, sort: null } })` merges with current URL, `null` removes params.
- Inertia: Use `.form()` with `<Form>` component or `form.submit(store())` with useForm.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.
- Keep the current expectation-chaining style for feature tests. Prefer `expect(...)->toBe...()->and(...)` over switching between multiple assertion styles in the same test.
- For Inertia page responses, follow the existing `assertInertia(fn (Assert $page) => ...)` pattern instead of asserting only status codes.
- For route registration checks, keep using direct `Route::has(...)` expectations in focused feature tests instead of broader end-to-end requests when route existence is what matters.
- For Boost discovery tests, use `Symfony\Component\Process\Process` with `APP_ENV=local` when you need to assert the real generated guideline or skill context outside the testing environment.
- Prefer focused Pest execution like `php artisan test --compact tests/Feature/Boost/ProjectAiContextTest.php` or a small set of related files, matching this repository's fast feedback loop.

=== inertia-svelte/core rules ===

# Inertia + Svelte

- IMPORTANT: Activate `inertia-svelte-development` when working with Inertia Svelte client-side patterns.
- This app uses a hybrid public frontend: Laravel routes `/` and `/{any}` both render the Inertia `Welcome` page, and public navigation after first load is handled by the hash router in `resources/js/pages/Welcome.svelte`.
- Do not add Laravel routes for public hash pages like `/#/category/...`, `/#/tag/...`, `/#/articles/...`, `/#/search`, or `/#/stats` unless the backend route is truly required. Extend the route parser in `resources/js/pages/Welcome.svelte` and the matching page component instead.
- Prefer the existing public stores and helpers before creating new state layers:
  - `resources/js/stores/articles.svelte.js`
  - `resources/js/stores/app.svelte.js`
  - `resources/js/stores/bookmarks.svelte.js`
  - `resources/js/lib/api.js`
- Keep article filter payloads aligned with the backend request validation. In particular, `tags` must be sent as an array to the article index API.
- Keep page resolution lazy via `import.meta.glob()` and mirror any bootstrap changes in both `resources/js/app.ts` and `resources/js/ssr.ts`.
- Preserve the current bootstrap contract in `resources/js/app.ts`: the client mounts through `AppRoot.svelte`, and hydration is selected via `el.dataset.serverRendered === 'true'`.
- `resources/js/AppRoot.svelte` is not optional wrapper chrome. It initializes app-level state, owns the toast/update UI, and listens for the `sw:update-ready` browser event from the service worker registration flow.
- When changing Inertia bootstrapping, update both `resources/js/app.ts` and `resources/js/ssr.ts`.
- Preserve the current public-shell pattern: `Welcome.svelte` is the router shell, while `HomePage.svelte`, `CategoryPage.svelte`, `TagPage.svelte`, `ArticleDetailPage.svelte`, `SearchPage.svelte`, `BookmarksPage.svelte`, `StatsPage.svelte`, and the public info pages render the actual content.
- Verify public frontend changes with `npm run types:check`, `npm run lint:check`, and `npm run build:ssr`. When routing or Laravel page responses change, also run a focused Pest test.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.
- Prefer updating shared theme tokens in `resources/css/app.css` through the existing Tailwind v4 `@theme inline` variables and app color custom properties instead of scattering one-off raw color values.
- The public news UI already leans on slate, sky, emerald, and red accents with layered gradients, translucent borders, large radii, and `backdrop-blur-*` surfaces. Extend that visual language before introducing a different palette or flatter layout style.
- For conditional class composition in Svelte components, follow the existing `cn()` helper pattern from `resources/js/lib/utils.ts`.
- Keep dark mode support aligned with the existing `dark:` variants and shared CSS variables in `resources/css/app.css`; new public-facing UI should not work only in light mode.
- Prefer utility classes over component-local `<style>` blocks. Reserve local CSS for cases that are genuinely utility-unfriendly, like the custom ticker keyframes in `resources/js/components/layout/BreakingNewsTicker.svelte`.
- Dynamic category or feed colors may use inline `style=` values when they come from API data, but keep layout, spacing, borders, shadows, and typography in Tailwind utilities.

</laravel-boost-guidelines>
