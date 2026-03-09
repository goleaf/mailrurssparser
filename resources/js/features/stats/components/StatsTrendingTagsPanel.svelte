<script lang="ts">
    import { createEventDispatcher } from 'svelte';
    import Skeleton from '@/components/ui/skeleton/Skeleton.svelte';
    import type { StatsTrendingTag } from '@/features/portal';
    import { tagSizeClass } from '@/features/stats/lib/stats';
    import { cn } from '@/lib/utils';

    interface Props {
        loading: boolean;
        tags: StatsTrendingTag[];
    }

    const dispatch = createEventDispatcher<{
        tagselect: string;
    }>();

    let { loading, tags }: Props = $props();

    function handleTagClick(event: Event): void {
        const slug = (event.currentTarget as HTMLButtonElement).dataset.slug;

        if (!slug) {
            return;
        }

        dispatch('tagselect', slug);
    }
</script>

<article
    class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900"
>
    <div
        class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"
    >
        Теги
    </div>
    <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">
        Тренды редакции
    </h2>

    {#if loading}
        <div class="mt-6 flex flex-wrap gap-3">
            {#each Array.from({ length: 12 }) as _, index (index)}
                <Skeleton class="h-9 w-24 rounded-full" />
            {/each}
        </div>
    {:else}
        <div class="mt-6 flex flex-wrap gap-3">
            {#each tags as tag (tag.id)}
                <button
                    type="button"
                    data-slug={tag.slug}
                    class={cn(
                        'rounded-full px-3 py-2 text-white transition hover:opacity-90',
                        tagSizeClass(tag.usage_count ?? 0),
                    )}
                    style={`background-color: ${tag.color ?? '#6B7280'}`}
                    onclick={handleTagClick}
                >
                    #{tag.name}
                </button>
            {/each}
        </div>
    {/if}
</article>
