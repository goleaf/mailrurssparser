---
name: news-portal-frontend
description: "Works on this repository's public Blade + Mary UI news portal frontend, including the homepage, article/category/tag/search pages, and the shared layout and partials."
---

# News Portal Frontend

## When to Apply

Use this skill when working on:

- The public homepage at `/`
- `resources/views/layouts/app.blade.php`
- `resources/views/public/*`
- `resources/views/components/public/*`
- News portal UI, layout, cards, sidebars, statistics panels, and public navigation
- Public article/category/tag/search/bookmarks/stats flows

## Architecture

- Laravel serves the public frontend directly through named Blade routes in `routes/web.php`.
- Shared chrome lives in `resources/views/layouts/app.blade.php`.
- Public content pages should normally be implemented as Blade views under `resources/views/public`, with reusable partials or anonymous components under `resources/views/components/public`.

## State and Data

- Prefer server-rendered Eloquent queries and view data over rebuilding client-side state layers.
- Reuse the existing `/api/v1/...` endpoints only when the page genuinely needs client-side behavior.
- Keep article filter params compatible with the backend validation when forms or query builders touch the same filters. In particular, `tags` must remain an array for API requests.

## Implementation Notes

- Keep the public route names stable: `home`, `category.show`, `tag.show`, `articles.show`, `search`, `bookmarks`, `stats`, `about`, `contact`, `privacy`, and the `spa` fallback.
- Mary UI should be used as a Blade component layer, not as a reason to reintroduce a SPA shell.
- Keep the public theme, gradients, and portal shell styles centralized in `resources/css/app.css` and the shared Blade layout.

## Verification

- Run `npm run types:check`.
- Run `npm run lint:check`.
- Run `npm run build`.
- If Laravel routing or public page rendering changes, run a focused Pest feature test as well.
