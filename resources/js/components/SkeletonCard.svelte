<script lang="ts">
    import Skeleton from '@/components/ui/skeleton/Skeleton.svelte';

    let {
        imageClass = 'h-48',
        lineWidths = ['w-24', 'w-full', 'w-4/5', 'w-full'],
    }: {
        imageClass?: string;
        lineWidths?: string[];
    } = $props();
</script>

<div class="rounded-3xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-900">
    <Skeleton class={`${imageClass} w-full rounded-2xl`} />
    <div class="mt-4 space-y-3">
        {#each lineWidths as width (`skeleton-line-${width}`)}
            <Skeleton class={`h-4 ${width}`} />
        {/each}
    </div>
</div>
