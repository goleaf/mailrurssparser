<script lang="ts">
    import { createEventDispatcher } from 'svelte';
    import { cn } from '@/lib/utils';

    const dispatch = createEventDispatcher<{
        sharemenutoggle: null;
        share: string;
        bookmarktoggle: null;
    }>();

    let {
        sharePlatforms,
        shareMenuOpen,
        sharesCount,
        bookmarked,
    }: {
        sharePlatforms: ReadonlyArray<{ key: string; label: string }>;
        shareMenuOpen: boolean;
        sharesCount: number;
        bookmarked: boolean;
    } = $props();
</script>

<div class="space-y-8">
    <section
        class="rounded-[2rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.97),rgba(248,250,252,0.94))] p-6 shadow-[0_30px_90px_-65px_rgba(15,23,42,0.46)] dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.84))]"
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div
                    class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 dark:text-sky-300"
                >
                    Поделиться
                </div>
                <div class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Поделиться:
                </div>
            </div>

            <button
                type="button"
                class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 lg:hidden dark:border-white/10 dark:text-slate-200"
                onclick={() => {
                    dispatch('sharemenutoggle', null);
                }}
            >
                {shareMenuOpen ? 'Скрыть' : 'Показать варианты'}
            </button>
        </div>

        <div
            class={cn(
                'mt-5 flex flex-wrap gap-2 lg:sticky lg:top-24',
                !shareMenuOpen && 'hidden lg:flex',
            )}
        >
            {#each sharePlatforms as platform (platform.key)}
                <button
                    type="button"
                    class="rounded-full bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-white/10 dark:text-slate-200 dark:hover:bg-white/15"
                    onclick={() => {
                        dispatch('share', platform.key);
                    }}
                >
                    {platform.label}
                </button>
            {/each}

            <div
                class="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-slate-950"
            >
                Поделились: {sharesCount}
            </div>
        </div>
    </section>

    <section
        class="flex flex-wrap items-center gap-4 rounded-[2rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.97),rgba(248,250,252,0.94))] p-6 shadow-[0_30px_90px_-65px_rgba(15,23,42,0.46)] dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.84))]"
    >
        <button
            type="button"
            class={cn(
                'rounded-full px-5 py-3 text-sm font-semibold transition',
                bookmarked
                    ? 'bg-amber-400 text-slate-950 hover:bg-amber-300'
                    : 'bg-slate-900 text-white hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200',
            )}
            onclick={() => {
                dispatch('bookmarktoggle', null);
            }}
        >
            {bookmarked ? 'Удалить из закладок' : 'Сохранить в закладки'}
        </button>
    </section>
</div>
