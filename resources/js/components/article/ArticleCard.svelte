<script lang="ts">
    import { showToast } from '@/components/ui/Toast.svelte';
    import { getArticleContentTypeLabel } from '@/lib/articleEnums';
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
        content_type_label?: string | null;
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

    let {
        article,
        showBookmark = true,
        onBookmarkToggle,
    }: {
        article: Article;
        showBookmark?: boolean;
        onBookmarkToggle?:
            | ((result: BookmarkToggleResult) => void | Promise<void>)
            | undefined;
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
    let imageLoaded = $derived(!article.image_url);
</script>

<article
    class="group flex flex-col overflow-hidden rounded-[1.9rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.94))] shadow-[0_24px_70px_-50px_rgba(15,23,42,0.5)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_32px_90px_-48px_rgba(15,23,42,0.58)] dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.94),rgba(15,23,42,0.82))]"
>
    <div class="relative overflow-hidden">
        {#if article.image_url}
            <a
                href={`/#/articles/${article.slug}`}
                class="bg-slate-200 dark:bg-slate-700"
            >
                <img
                    src={article.image_url}
                    alt={article.title}
                    loading="lazy"
                    decoding="async"
                    class={cn(
                        'h-52 w-full object-cover transition duration-700 group-hover:scale-105',
                        !imageLoaded && 'scale-105 blur-xl',
                    )}
                    onload={() => {
                        imageLoaded = true;
                    }}
                />
            </a>
        {:else}
            <div
                class="flex h-52 w-full items-center justify-center bg-[linear-gradient(135deg,#dbeafe,#e2e8f0,#cbd5e1)] dark:bg-[linear-gradient(135deg,#1e293b,#0f172a,#334155)]"
            >
                <span class="text-4xl">{article.category.icon || '📰'}</span>
            </div>
        {/if}

        <div
            class="absolute inset-x-0 top-0 flex items-start justify-between gap-3 p-3"
        >
            <a
                href={`/#/category/${article.category.slug}`}
                class="rounded-full px-3 py-1.5 text-[0.68rem] font-bold tracking-[0.18em] uppercase text-white shadow"
                style={`background-color:${article.category.color ?? '#3B82F6'}`}
            >
                {article.category.name}
            </a>

            {#if article.is_breaking}
                <span
                    class="rounded-full bg-red-500 px-3 py-1.5 text-[0.68rem] font-bold tracking-[0.18em] uppercase text-white animate-pulse"
                >
                    Срочно
                </span>
            {:else if isNew || article.is_recent}
                <span
                    class="rounded-full bg-emerald-500 px-3 py-1.5 text-[0.68rem] font-bold tracking-[0.18em] uppercase text-white"
                >
                    Новое
                </span>
            {/if}
        </div>

        {#if article.content_type && article.content_type !== 'news'}
            <div class="absolute bottom-3 left-3">
                <span
                    class="rounded-full border border-white/15 bg-black/55 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-white backdrop-blur"
                >
                    {article.content_type_label ??
                        getArticleContentTypeLabel(article.content_type)}
                </span>
            </div>
        {/if}
    </div>

    <div class="flex flex-1 flex-col p-5">
        <div
            class="mb-3 flex items-center justify-between gap-3 text-[0.72rem] uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500"
        >
            <time>{formattedDate}</time>
            <span>Поток</span>
        </div>

        <a href={`/#/articles/${article.slug}`}>
            <h2
                class="mb-3 line-clamp-3 text-[1.02rem] leading-tight font-bold text-slate-950 transition-colors hover:text-sky-700 dark:text-white dark:hover:text-sky-300"
            >
                {article.title}
            </h2>
        </a>

        {#if article.short_description}
            <p
                class="mb-4 line-clamp-3 flex-1 text-sm leading-6 text-slate-600 dark:text-slate-300"
            >
                {article.short_description}
            </p>
        {/if}

        {#if article.tags?.length}
            <div class="mb-4 flex flex-wrap gap-2">
                {#each article.tags.slice(0, 2) as tag (tag.id)}
                    <a
                        href={`/#/tag/${tag.slug}`}
                        class="rounded-full border border-slate-200 bg-white px-3 py-1 text-[0.72rem] font-medium text-slate-500 transition-colors hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-sky-900/30 dark:hover:text-sky-200"
                    >
                        #{tag.name}
                    </a>
                {/each}
            </div>
        {/if}

        <div
            class="mt-auto flex items-center justify-between border-t border-slate-200/80 pt-4 dark:border-white/10"
        >
            <div
                class="flex items-center gap-3 text-xs text-slate-400 dark:text-slate-500"
            >
                <span>👁 {article.views_count ?? 0}</span>
                <span>⏱ {article.reading_time ?? 1}м</span>
            </div>

            <div class="flex items-center gap-2">
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
                        class={`rounded-full border px-3 py-1.5 text-xs font-medium transition-colors ${
                            isBookmarked(article.id)
                                ? 'border-amber-300 bg-amber-50 text-amber-600 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300'
                                : 'border-slate-200 bg-white text-slate-400 hover:border-amber-300 hover:text-amber-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400 dark:hover:border-amber-400/30 dark:hover:text-amber-300'
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
