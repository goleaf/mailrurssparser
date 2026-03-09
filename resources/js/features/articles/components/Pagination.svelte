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

    function goToPreviousPage(): void {
        onChange(currentPage - 1);
    }

    function goToNextPage(): void {
        onChange(currentPage + 1);
    }

    function handlePageClick(event: Event): void {
        const page = Number(
            (event.currentTarget as HTMLButtonElement).dataset.page,
        );

        if (!Number.isFinite(page)) {
            return;
        }

        onChange(page);
    }
</script>

{#if lastPage > 1}
    <div
        class="rounded-[2rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(248,250,252,0.92))] p-4 shadow-[0_24px_80px_-60px_rgba(15,23,42,0.45)] dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.82))]"
    >
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="space-y-1">
                {#if label}
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        {label}
                    </div>
                {/if}
                <div
                    class="text-xs font-semibold tracking-[0.22em] text-slate-400 uppercase"
                >
                    Страница {currentPage} из {lastPage}
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="rounded-full border border-slate-200 bg-white/80 px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-white disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
                    onclick={goToPreviousPage}
                    disabled={currentPage <= 1}
                >
                    Назад
                </button>

                <div
                    class="flex items-center gap-2 rounded-full border border-slate-200/80 bg-slate-50/80 px-2 py-1.5 dark:border-white/10 dark:bg-black/10"
                >
                    {#each visiblePages as pageNumber (pageNumber)}
                        <button
                            type="button"
                            class={cn(
                                'inline-flex size-10 items-center justify-center rounded-full text-sm font-semibold transition',
                                pageNumber === currentPage
                                    ? 'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-950'
                                    : 'text-slate-600 hover:bg-white hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white',
                            )}
                            data-page={pageNumber}
                            onclick={handlePageClick}
                        >
                            {pageNumber}
                        </button>
                    {/each}
                </div>

                <button
                    type="button"
                    class="rounded-full border border-slate-200 bg-white/80 px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-white disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
                    onclick={goToNextPage}
                    disabled={currentPage >= lastPage}
                >
                    {nextLabel}
                </button>
            </div>
        </div>
    </div>
{/if}
