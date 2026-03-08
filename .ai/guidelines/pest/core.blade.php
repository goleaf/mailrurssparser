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
