<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import type { HrefLike, HttpMethod } from '@/types/inertia';

    let {
        href,
        tabindex,
        method,
        as,
        children,
        ...rest
    }: {
        href: HrefLike;
        tabindex?: number;
        method?: HttpMethod;
        as?: keyof HTMLElementTagNameMap;
        children?: Snippet;
        [key: string]: unknown;
    } = $props();
</script>

<Link
    {href}
    {tabindex}
    {method}
    {as}
    class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
    {...rest}
>
    {@render children?.()}
</Link>
