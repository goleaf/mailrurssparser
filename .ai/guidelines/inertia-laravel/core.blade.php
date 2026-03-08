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
