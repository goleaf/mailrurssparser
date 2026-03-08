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
