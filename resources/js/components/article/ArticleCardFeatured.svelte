<script lang="ts">
    import { showToast } from '@/components/ui/Toast.svelte';
    import { getArticleContentTypeLabel } from '@/lib/articleEnums';
    import { articleUrl, categoryUrl, tagUrl } from '@/lib/publicRoutes';
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

    let {
        article,
        showBookmark = true,
    }: { article: Article; showBookmark?: boolean } = $props();

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
    let imageLoaded = $derived(!article.image_url);
</script>

<article
    class="group overflow-hidden rounded-[2.2rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.94))] shadow-[0_30px_90px_-55px_rgba(15,23,42,0.55)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_40px_120px_-55px_rgba(15,23,42,0.62)] dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.95),rgba(15,23,42,0.82))]"
>
    <div class="relative overflow-hidden">
        {#if article.image_url}
            <a
                href={articleUrl(article.slug)}
                class="bg-slate-200 dark:bg-slate-700"
            >
                <img
                    src={article.image_url}
                    alt={article.title}
                    loading="lazy"
                    decoding="async"
                    class={cn(
                        'h-80 w-full object-cover transition duration-700 group-hover:scale-105',
                        !imageLoaded && 'scale-105 blur-xl',
                    )}
                    onload={() => {
                        imageLoaded = true;
                    }}
                />
            </a>
        {:else}
            <div
                class="flex h-80 w-full items-center justify-center bg-[linear-gradient(135deg,#dbeafe,#bfdbfe,#0f172a)] dark:bg-[linear-gradient(135deg,#1e293b,#0f172a,#0369a1)]"
            >
                <span class="text-6xl">{article.category.icon || '📰'}</span>
            </div>
        {/if}

        <div
            class="absolute inset-0 bg-linear-to-t from-slate-950/85 via-slate-950/30 to-transparent"
        ></div>
        <div
            class="absolute inset-x-0 top-0 flex items-start justify-between p-4"
        >
            <a
                href={categoryUrl(article.category.slug)}
                class="rounded-full px-3 py-1.5 text-[0.68rem] font-bold uppercase tracking-[0.18em] text-white shadow"
                style={`background-color:${article.category.color ?? '#3B82F6'}`}
            >
                {article.category.name}
            </a>

            <div class="flex items-center gap-2">
                {#if article.is_breaking}
                    <span
                        class="rounded-full bg-red-500 px-3 py-1.5 text-[0.68rem] font-bold uppercase tracking-[0.18em] text-white"
                    >
                        Срочно
                    </span>
                {:else if isNew || article.is_recent}
                    <span
                        class="rounded-full bg-emerald-500 px-3 py-1.5 text-[0.68rem] font-bold uppercase tracking-[0.18em] text-white"
                    >
                        Новое
                    </span>
                {/if}

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
                <span
                    class="rounded-full border border-white/15 bg-black/60 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-white backdrop-blur"
                >
                    {article.content_type_label ??
                        getArticleContentTypeLabel(article.content_type)}
                </span>
            </div>
        {/if}
    </div>

    <div class="space-y-4 p-6">
        <div
            class="flex items-center gap-3 text-[0.72rem] uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500"
        >
            <time>{formattedDate}</time>
            <span>Подборка дня</span>
        </div>

        <a href={articleUrl(article.slug)}>
            <h2
                class="text-[1.65rem] leading-tight font-bold text-slate-950 transition-colors hover:text-sky-700 dark:text-white dark:hover:text-sky-300"
            >
                {article.title}
            </h2>
        </a>

        {#if article.short_description}
            <p
                class="line-clamp-4 text-sm leading-7 text-slate-600 dark:text-slate-300"
            >
                {article.short_description}
            </p>
        {/if}

        {#if article.tags?.length}
            <div class="flex flex-wrap gap-2">
                {#each article.tags.slice(0, 3) as tag (tag.id)}
                    <a
                        href={tagUrl(tag.slug)}
                        class="rounded-full border border-slate-200 bg-white px-3 py-1 text-[0.72rem] font-medium text-slate-500 transition-colors hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-sky-900/30 dark:hover:text-sky-200"
                    >
                        #{tag.name}
                    </a>
                {/each}
            </div>
        {/if}

        <div
            class="flex flex-wrap items-center gap-4 border-t border-slate-200/80 pt-4 text-sm text-slate-500 dark:border-white/10 dark:text-slate-400"
        >
            <span>👁 {article.views_count ?? 0}</span>
            <span>⏱ {article.reading_time ?? 1}м</span>
        </div>
    </div>
</article>
