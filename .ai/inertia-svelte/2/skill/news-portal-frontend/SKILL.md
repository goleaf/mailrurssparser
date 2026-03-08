---
name: news-portal-frontend
description: "Works on this repository's public news portal frontend, including the hash-routed Welcome shell, homepage design, article/category/tag/search pages, and the shared public stores."
---
# News Portal Frontend

## When to Apply

Use this skill when working on:

- The public homepage at `/`
- The public hash routes under `/#/`
- `resources/js/pages/Welcome.svelte` and the public page components it renders
- News portal UI, layout, cards, sidebars, ticker, and public navigation
- Public article/category/tag/search/bookmarks/stats flows

## Architecture

- Laravel serves the public frontend through the Inertia `Welcome` page for both `/` and the catch-all `/{any}` route.
- Client-side public navigation is hash-based and parsed inside `resources/js/pages/Welcome.svelte`.
- Public content pages should normally be implemented as Svelte page components rendered by `Welcome.svelte`, not as new Laravel routes.

## State and Data

- Use the existing public stores first:
  - `resources/js/stores/articles.svelte.js`
  - `resources/js/stores/app.svelte.js`
  - `resources/js/stores/bookmarks.svelte.js`
- Reuse the API helpers in `resources/js/lib/api.js` before adding new fetch wrappers.
- Keep article filter params compatible with the backend. `tags` must remain an array.

## Implementation Notes

- `resources/js/pages/HomePage.svelte` is the designed landing page rendered by the public shell.
- The auth/admin strip on the public shell uses Inertia + Wayfinder links from `@/routes`.
- If you change Inertia bootstrapping or page resolution, update both `resources/js/app.ts` and `resources/js/ssr.ts`.

## Verification

- Run `npm run types:check`.
- Run `npm run lint:check`.
- Run `npm run build:ssr`.
- If Laravel routing or the served Inertia page changes, run a focused Pest feature test as well.
