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
