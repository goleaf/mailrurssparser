<script lang="ts">
    import { showToast } from '@/components/ui/Toast.svelte';
    import { cn } from '@/lib/utils';
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

    type BookmarkToggleResult = {
        bookmarked: boolean;
        total?: number;
        articleId: number | string;
    };

    const contentTypeLabels: Record<string, string> = {
        opinion: 'Мнение',
        analysis: 'Аналитика',
        interview: 'Интервью',
        article: 'Статья',
    };

    let {
        article,
        showBookmark = true,
        onBookmarkToggle,
    }: {
        article: Article;
        showBookmark?: boolean;
        onBookmarkToggle?: ((result: BookmarkToggleResult) => void | Promise<void>) | undefined;
    } = $props();

    const publishedDate = $derived(
        article.published_at ? new Date(article.published_at) : null,
    );

    const formattedDate = $derived(
        publishedDate
            ? new Intl.DateTimeFormat('ru-RU', {
                  day: 'numeric',
                  month: 'short',
                  year: 'numeric',
              }).format(publishedDate)
            : 'Без даты',
    );

    const isNew = $derived(
        publishedDate
            ? Date.now() - publishedDate.getTime() < 6 * 60 * 60 * 1000
            : false,
    );
    let imageLoaded = $state(!article.image_url);

    $effect(() => {
        imageLoaded = !article.image_url;
    });
</script>

<article
    class="group flex flex-col overflow-hidden rounded-xl border border-gray-100 bg-white transition-all duration-300 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800"
>
    <div class="relative overflow-hidden">
        {#if article.image_url}
            <a href={`/#/articles/${article.slug}`} class="bg-slate-200 dark:bg-slate-700">
                <img
                    src={article.image_url}
                    alt={article.title}
                    loading="lazy"
                    decoding="async"
                    class={cn(
                        'h-48 w-full object-cover transition duration-500 group-hover:scale-105',
                        !imageLoaded && 'scale-105 blur-xl',
                    )}
                    onload={() => {
                        imageLoaded = true;
                    }}
                />
            </a>
        {:else}
            <div
                class="flex h-48 w-full items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600"
            >
                <span class="text-4xl">{article.category.icon || '📰'}</span>
            </div>
        {/if}

        <a
            href={`/#/category/${article.category.slug}`}
            class="absolute left-2 top-2 rounded-full px-2 py-1 text-xs font-bold text-white shadow"
            style={`background-color:${article.category.color ?? '#3B82F6'}`}
        >
            {article.category.name}
        </a>

        {#if article.is_breaking}
            <span
                class="absolute right-2 top-2 rounded-full bg-red-500 px-2 py-1 text-xs font-bold text-white animate-pulse"
            >
                СРОЧНО
            </span>
        {:else if isNew || article.is_recent}
            <span
                class="absolute right-2 top-2 rounded-full bg-green-500 px-2 py-1 text-xs font-bold text-white"
            >
                НОВОЕ
            </span>
        {/if}

        {#if article.content_type && article.content_type !== 'news'}
            <span
                class="absolute bottom-2 left-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white"
            >
                {contentTypeLabels[article.content_type] || ''}
            </span>
        {/if}
    </div>

    <div class="flex flex-1 flex-col p-4">
        <a href={`/#/articles/${article.slug}`}>
            <h2
                class="mb-2 line-clamp-3 text-sm leading-snug font-bold text-gray-900 transition-colors hover:text-blue-600 dark:text-white"
            >
                {article.title}
            </h2>
        </a>

        {#if article.short_description}
            <p class="mb-3 line-clamp-2 flex-1 text-xs text-gray-500 dark:text-gray-400">
                {article.short_description}
            </p>
        {/if}

        {#if article.tags?.length}
            <div class="mb-3 flex flex-wrap gap-1">
                {#each article.tags.slice(0, 2) as tag (tag.id)}
                    <a
                        href={`/#/tag/${tag.slug}`}
                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 transition-colors hover:bg-blue-100 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-blue-900/50"
                    >
                        #{tag.name}
                    </a>
                {/each}
            </div>
        {/if}

        <div
            class="mt-auto flex items-center justify-between border-t border-gray-50 pt-3 dark:border-gray-700"
        >
            <time class="text-xs text-gray-400">{formattedDate}</time>

            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400">👁 {article.views_count ?? 0}</span>
                <span class="text-xs text-gray-400">⏱ {article.reading_time ?? 1}м</span>

                {#if showBookmark}
                    <button
                        type="button"
                        onclick={async () => {
                            const result = await toggleBookmark(article.id);

                            showToast(
                                result.bookmarked
                                    ? 'Статья сохранена в закладки'
                                    : 'Статья удалена из закладок',
                                result.bookmarked ? 'success' : 'info',
                            );

                            await onBookmarkToggle?.({
                                ...result,
                                articleId: article.id,
                            });
                        }}
                        class={`text-xs transition-colors ${
                            isBookmarked(article.id)
                                ? 'text-yellow-500'
                                : 'text-gray-300 hover:text-yellow-400'
                        }`}
                        aria-label="Переключить закладку"
                    >
                        🔖
                    </button>
                {/if}
            </div>
        </div>
    </div>
</article>
