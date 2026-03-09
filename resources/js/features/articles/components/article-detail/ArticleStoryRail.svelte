<script lang="ts">
    import { ArticleCardCompact } from '@/features/articles';

    type Article = {
        id: number | string;
        title: string;
        slug: string;
        image_url?: string | null;
        published_at?: string | null;
        category?: {
            icon?: string | null;
        } | string | null;
    };

    let {
        similar,
        moreFromCategory,
        categoryName,
    }: {
        similar: Article[];
        moreFromCategory: Article[];
        categoryName: string;
    } = $props();
</script>

{#if similar.length > 0}
    <section
        class="mt-12 overflow-hidden rounded-[2rem] border border-slate-200 bg-white/80 p-6 shadow-sm backdrop-blur-sm dark:border-white/10 dark:bg-white/5"
    >
        <div
            class="mb-2 text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 text-shadow-2xs text-shadow-sky-200/60 dark:text-sky-300 dark:text-shadow-sky-950/80"
        >
            Похожие сюжеты
        </div>
        <div class="mb-5 text-sm text-slate-500 dark:text-slate-400">
            Подборка материалов с близким сюжетом и совпадающими сигналами.
        </div>
        <div class="mask-r-from-85% motion-reduce:mask-none">
            <div class="flex gap-4 overflow-x-auto pb-2 pr-10">
                {#each similar as item (item.id)}
                    <div class="min-w-[18rem] flex-1">
                        <ArticleCardCompact article={item} />
                    </div>
                {/each}
            </div>
        </div>
    </section>
{/if}

{#if moreFromCategory.length > 0}
    <section
        class="mt-12 rounded-[2rem] border border-slate-200 bg-linear-to-br from-white to-slate-50 p-6 shadow-sm dark:border-white/10 dark:from-white/5 dark:to-white/0"
    >
        <div
            class="mb-2 text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 text-shadow-2xs text-shadow-sky-200/60 dark:text-sky-300 dark:text-shadow-sky-950/80"
        >
            Ещё из раздела {categoryName}
        </div>
        <div class="mb-5 text-sm text-slate-500 dark:text-slate-400">
            Ещё несколько свежих материалов из этой же рубрики.
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            {#each moreFromCategory as item (item.id)}
                <ArticleCardCompact article={item} />
            {/each}
        </div>
    </section>
{/if}
