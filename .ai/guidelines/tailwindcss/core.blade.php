# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.
- Prefer updating shared theme tokens in `resources/css/app.css` through the existing Tailwind v4 `@theme inline` variables and app color custom properties instead of scattering one-off raw color values.
- The public news UI already leans on slate, sky, emerald, and red accents with layered gradients, translucent borders, large radii, and `backdrop-blur-*` surfaces. Extend that visual language before introducing a different palette or flatter layout style.
- For conditional class composition in JavaScript or TypeScript helpers, follow the existing `cn()` helper pattern from `resources/js/lib/utils.ts` when it keeps class strings readable.
- Keep dark mode support aligned with the existing `dark:` variants and shared CSS variables in `resources/css/app.css`; new public-facing UI should not work only in light mode.
- Prefer utility classes over component-local `<style>` blocks. Reserve local CSS for cases that are genuinely utility-unfriendly, such as shared portal-shell helpers and Mary pagination tweaks in `resources/css/app.css`.
- Dynamic category or feed colors may use inline `style=` values when they come from API data, but keep layout, spacing, borders, shadows, and typography in Tailwind utilities.
