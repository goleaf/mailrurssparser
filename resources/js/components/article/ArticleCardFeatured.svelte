<script lang="ts">
    import { toggleBookmark, isBookmarked } from '@/stores/bookmarks.svelte.js';

    type ArticleTag = {
        id: number | string;
        name: string;
        slug: string;
    };

    type ArticleCategory = {
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
    };

    type Article = {
        id: number | string;
        title: string;
        slug: string;
        image_url?: string | null;
        short_description?: string | null;
        published_at?: string | null;
        is_breaking?: boolean;
        is_recent?: boolean;
        content_type?: string | null;
        views_count?: number | null;
        reading_time?: number | null;
        tags?: ArticleTag[];
        category: ArticleCategory;
    };

    const contentTypeLabels: Record<string, string> = {
        opinion: 'Мнение',
        analysis: 'Аналитика',
        interview: 'Интервью',
        article: 'Статья',
    };

    let { article, showBookmark = true }: { article: Article; showBookmark?: boolean } = $props();

    const publishedDate = $derived(
        article.published_at ? new Date(article.published_at) : null,
    );

    const formattedDate = $derived(
        publishedDate
            ? new Intl.DateTimeFormat('ru-RU', {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              }).format(publishedDate)
            : 'Без даты',
    );

    const isNew = $derived(
        publishedDate
            ? Date.now() - publishedDate.getTime() < 6 * 60 * 60 * 1000
            : false,
    );
</script>

<article
    class="group overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl dark:border-gray-700 dark:bg-gray-800"
>
    <div class="relative overflow-hidden">
        {#if article.image_url}
            <a href={`/#/articles/${article.slug}`}>
                <img
                    src={article.image_url}
                    alt={article.title}
                    loading="lazy"
                    decoding="async"
                    class="h-72 w-full object-cover transition-transform duration-700 group-hover:scale-105"
                />
            </a>
        {:else}
            <div
                class="flex h-72 w-full items-center justify-center bg-gradient-to-br from-slate-100 via-sky-100 to-blue-200 dark:from-gray-700 dark:via-slate-700 dark:to-slate-600"
            >
                <span class="text-6xl">{article.category.icon || '📰'}</span>
            </div>
        {/if}

        <div class="absolute inset-x-0 top-0 flex items-start justify-between p-4">
            <a
                href={`/#/category/${article.category.slug}`}
                class="rounded-full px-3 py-1 text-xs font-bold text-white shadow"
                style={`background-color:${article.category.color ?? '#3B82F6'}`}
            >
                {article.category.name}
            </a>

            <div class="flex items-center gap-2">
                {#if article.is_breaking}
                    <span class="rounded-full bg-red-500 px-3 py-1 text-xs font-bold text-white">
                        СРОЧНО
                    </span>
                {:else if isNew || article.is_recent}
                    <span class="rounded-full bg-green-500 px-3 py-1 text-xs font-bold text-white">
                        НОВОЕ
                    </span>
                {/if}

                {#if showBookmark}
                    <button
                        type="button"
                        onclick={() => {
                            void toggleBookmark(article.id);
                        }}
                        class={`rounded-full bg-white/90 px-3 py-1 text-sm shadow transition-colors dark:bg-gray-900/80 ${
                            isBookmarked(article.id)
                                ? 'text-yellow-500'
                                : 'text-gray-500 hover:text-yellow-500 dark:text-gray-300'
                        }`}
                        aria-label="Переключить закладку"
                    >
                        🔖
                    </button>
                {/if}
            </div>
        </div>

        {#if article.content_type && article.content_type !== 'news'}
            <div class="absolute bottom-4 left-4">
                <span class="rounded-full bg-black/60 px-3 py-1 text-xs text-white">
                    {contentTypeLabels[article.content_type] || ''}
                </span>
            </div>
        {/if}
    </div>

    <div class="space-y-4 p-6">
        <div class="flex items-center gap-3 text-xs text-gray-400">
            <time>{formattedDate}</time>
            <span>👁 {article.views_count ?? 0}</span>
            <span>⏱ {article.reading_time ?? 1}м</span>
        </div>

        <a href={`/#/articles/${article.slug}`}>
            <h2
                class="text-xl leading-tight font-bold text-gray-900 transition-colors hover:text-blue-600 dark:text-white"
            >
                {article.title}
            </h2>
        </a>

        {#if article.short_description}
            <p class="line-clamp-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                {article.short_description}
            </p>
        {/if}

        {#if article.tags?.length}
            <div class="flex flex-wrap gap-2">
                {#each article.tags.slice(0, 3) as tag (tag.id)}
                    <a
                        href={`/#/tag/${tag.slug}`}
                        class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 transition-colors hover:bg-blue-100 hover:text-blue-700 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-blue-900/50 dark:hover:text-blue-200"
                    >
                        #{tag.name}
                    </a>
                {/each}
            </div>
        {/if}
    </div>
</article>
