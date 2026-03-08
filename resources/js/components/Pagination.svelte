<script lang="ts">
    import { cn } from '@/lib/utils';

    let {
        currentPage,
        lastPage,
        onChange,
        label = null,
        nextLabel = 'Дальше',
    }: {
        currentPage: number;
        lastPage: number;
        onChange: (page: number) => void;
        label?: string | null;
        nextLabel?: string;
    } = $props();

    const visiblePages = $derived.by(() => {
        const pages: number[] = [];
        const start = Math.max(1, currentPage - 2);
        const end = Math.min(lastPage, currentPage + 2);

        for (let page = start; page <= end; page += 1) {
            pages.push(page);
        }

        return pages;
    });
</script>

{#if lastPage > 1}
    <div class="flex flex-wrap items-center justify-center gap-2 rounded-[1.75rem] border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-900">
        {#if label}
            <div class="w-full text-center text-sm text-slate-500 dark:text-slate-400">
                {label}
            </div>
        {/if}

        <button
            type="button"
            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
            onclick={() => {
                onChange(currentPage - 1);
            }}
            disabled={currentPage <= 1}
        >
            Назад
        </button>

        {#each visiblePages as pageNumber (pageNumber)}
            <button
                type="button"
                class={cn(
                    'inline-flex size-10 items-center justify-center rounded-full text-sm font-medium transition',
                    pageNumber === currentPage
                        ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
                )}
                onclick={() => {
                    onChange(pageNumber);
                }}
            >
                {pageNumber}
            </button>
        {/each}

        <button
            type="button"
            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
            onclick={() => {
                onChange(currentPage + 1);
            }}
            disabled={currentPage >= lastPage}
        >
            {nextLabel}
        </button>
    </div>
{/if}
