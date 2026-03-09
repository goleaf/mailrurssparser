<script lang="ts">
    import { createEventDispatcher } from 'svelte';
    type HighlightItem = {
        articleId: number | string;
        title: string;
        slug: string;
        segments: Array<{
            text: string;
            highlighted: boolean;
        }>;
    };

    type SearchSuggestionCategory = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
    };

    type SearchSuggestionTag = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
    };

    type SearchSuggestions = {
        categories: SearchSuggestionCategory[];
        tags: SearchSuggestionTag[];
    };

    const dispatch = createEventDispatcher<{
        categoryselect: string;
        tagselect: string;
    }>();

    let {
        highlights,
        suggestions,
        articleHref,
    }: {
        highlights: HighlightItem[];
        suggestions: SearchSuggestions;
        articleHref: (slug: string) => string;
    } = $props();
</script>

<aside class="space-y-5">
    <section
        class="rounded-[1.85rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.94))] p-5 shadow-[0_24px_80px_-60px_rgba(15,23,42,0.45)] dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.82))]"
    >
        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
            Подсветка совпадений
        </div>

        <div class="mt-4 space-y-3">
            {#if highlights.length > 0}
                {#each highlights as item (String(item.articleId))}
                    <a
                        href={articleHref(item.slug)}
                        class="block rounded-2xl border border-slate-200 p-4 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5"
                    >
                        <div class="text-sm font-semibold text-slate-900 dark:text-white">
                            {item.title}
                        </div>
                        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                            {#each item.segments as segment, index (index)}
                                {#if segment.highlighted}
                                    <mark
                                        class="rounded bg-sky-100 px-1 text-slate-900 dark:bg-sky-500/25 dark:text-sky-50"
                                    >
                                        {segment.text}
                                    </mark>
                                {:else}
                                    {segment.text}
                                {/if}
                            {/each}
                        </p>
                    </a>
                {/each}
            {:else}
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    После поиска здесь появятся самые точные совпадения по
                    тексту.
                </p>
            {/if}
        </div>
    </section>

    <section
        class="rounded-[1.85rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.94))] p-5 shadow-[0_24px_80px_-60px_rgba(15,23,42,0.45)] dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.82))]"
    >
        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
            Похожие запросы
        </div>

        <div class="mt-4 space-y-4">
            <div>
                <div class="text-sm font-semibold text-slate-900 dark:text-white">
                    Рубрики
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    {#if suggestions.categories.length > 0}
                        {#each suggestions.categories as category (category.id)}
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                onclick={() => {
                                    dispatch('categoryselect', category.slug);
                                }}
                            >
                                <span
                                    class="size-2 rounded-full"
                                    style={`background-color: ${category.color ?? '#2563EB'};`}
                                ></span>
                                {category.name}
                            </button>
                        {/each}
                    {:else}
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Подходящие рубрики появятся после ввода запроса.
                        </p>
                    {/if}
                </div>
            </div>

            <div>
                <div class="text-sm font-semibold text-slate-900 dark:text-white">
                    Теги
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    {#if suggestions.tags.length > 0}
                        {#each suggestions.tags as tag (tag.id)}
                            <button
                                type="button"
                                class="rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-200 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                                onclick={() => {
                                    dispatch('tagselect', tag.slug);
                                }}
                            >
                                #{tag.name}
                            </button>
                        {/each}
                    {:else}
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Подходящие теги появятся после ввода запроса.
                        </p>
                    {/if}
                </div>
            </div>
        </div>
    </section>
</aside>
