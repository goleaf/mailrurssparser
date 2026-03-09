<script lang="ts">
    import { ArticleCard } from '@/features/articles';

    type Article = {
        id: number | string;
        title: string;
        slug: string;
        short_description?: string | null;
        image_url?: string | null;
        category: {
            id: number | string;
            name: string;
            slug: string;
            color?: string | null;
            icon?: string | null;
        };
        tags?: Array<{
            id: number | string;
            name: string;
            slug: string;
            color?: string | null;
        }>;
    };

    let { related }: { related: Article[] } = $props();
</script>

{#if related.length > 0}
    <section
        class="relative overflow-hidden rounded-[2rem] border border-sky-100 bg-linear-to-b from-white via-sky-50/70 to-white p-5 shadow-[0_30px_80px_-50px_rgba(14,165,233,0.55)] dark:border-sky-900/40 dark:from-slate-900 dark:via-sky-950/20 dark:to-slate-900 dark:shadow-[0_30px_80px_-50px_rgba(14,165,233,0.35)]"
    >
        <div
            class="pointer-events-none absolute inset-x-8 top-0 h-px bg-linear-to-r from-transparent via-sky-400/80 to-transparent"
        ></div>
        <div
            class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 text-shadow-2xs text-shadow-sky-200/60 dark:text-sky-300 dark:text-shadow-sky-950/80"
        >
            Читайте также
        </div>
        <div class="mt-2 text-sm text-slate-500 dark:text-slate-400">
            Самые близкие материалы по теме, тегам и рубрике.
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
            {#each related.slice(0, 4) as item (item.id)}
                <ArticleCard article={item} showBookmark={false} />
            {/each}
        </div>
    </section>
{/if}
