<script lang="ts">
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ArticleCard from '@/components/article/ArticleCard.svelte';
    import ArticleCardCompact from '@/components/article/ArticleCardCompact.svelte';
    import { usePolling } from '@/composables/usePolling.js';
    import { injectJsonLd, setSeoMeta } from '@/composables/useSeo.js';
    import { showToast } from '@/components/ui/Toast.svelte';
    import * as api from '@/lib/api';
    import { cn } from '@/lib/utils';
    import {
        isBookmarked,
        loadBookmarks,
        toggleBookmark,
    } from '@/stores/bookmarks.svelte.js';

    type Tag = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
    };

    type Category = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
    };

    type RssFeed = {
        id?: number | string;
        title?: string | null;
    };

    type Article = {
        id: number | string;
        title: string;
        slug: string;
        short_description?: string | null;
        image_url?: string | null;
        image_caption?: string | null;
        source_url?: string | null;
        author?: string | null;
        source_name?: string | null;
        content_type?: string | null;
        is_breaking?: boolean;
        reading_time?: number | null;
        reading_time_text?: string | null;
        views_count?: number | null;
        shares_count?: number | null;
        published_at?: string | null;
        published_at_date?: string | null;
        rss_parsed_at?: string | null;
        full_content?: string | null;
        meta_title?: string | null;
        meta_description?: string | null;
        structured_data?: Record<string, unknown> | null;
        category: Category;
        tags?: Tag[];
        rss_feed?: RssFeed | null;
        related_articles?: Article[];
        similar_articles?: Article[];
        more_from_category?: Article[];
    };

    const sharePlatforms = [
        { key: 'vk', label: 'VK 🔵' },
        { key: 'telegram', label: 'Telegram 📱' },
        { key: 'whatsapp', label: 'WhatsApp 💬' },
        { key: 'copy', label: 'Копировать ссылку 🔗' },
    ] as const;

    let { slug }: { slug: string } = $props();

    let article = $state<Article | null>(null);
    let related = $state<Article[]>([]);
    let similar = $state<Article[]>([]);
    let moreFromCategory = $state<Article[]>([]);
    let loading = $state(true);
    let error = $state<string | null>(null);
    let shareMenuOpen = $state(false);
    let readProgress = $state(0);
    const viewsPolling = usePolling(async () => {
        const response = await api.getArticle(slug, { track: 0 });

        return (response.data?.data ?? null) as Article | null;
    }, 120000);

    const publishedDate = $derived(
        article?.published_at
            ? new Intl.DateTimeFormat('ru-RU', {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              }).format(new Date(article.published_at))
            : '',
    );

    const rssParsedDate = $derived(
        article?.rss_parsed_at
            ? new Intl.DateTimeFormat('ru-RU', {
                  day: 'numeric',
                  month: 'short',
                  year: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit',
              }).format(new Date(article.rss_parsed_at))
            : 'Нет данных',
    );

    const sanitizedContent = $derived(
        article?.full_content ? sanitizeHtml(article.full_content) : '',
    );

    const metaDescription = $derived(
        article?.meta_description || article?.short_description || '',
    );

    const contentTypeLabel = $derived(
        article?.content_type
            ? ({
                  news: 'Новость',
                  article: 'Статья',
                  opinion: 'Мнение',
                  analysis: 'Аналитика',
                  interview: 'Интервью',
              }[article.content_type] ?? '')
            : '',
    );

    function sanitizeHtml(value: string): string {
        return value
            .replace(
                /<(script|iframe|style|object|embed|form)[^>]*>.*?<\/\1>/gis,
                '',
            )
            .replace(/\son\w+="[^"]*"/g, '')
            .replace(/\son\w+='[^']*'/g, '');
    }

    function updateReadProgress(): void {
        if (typeof window === 'undefined' || typeof document === 'undefined') {
            return;
        }

        const maxScroll =
            document.documentElement.scrollHeight - window.innerHeight;

        if (maxScroll <= 0) {
            readProgress = 0;

            return;
        }

        readProgress = Math.min(
            100,
            Math.max(0, (window.scrollY / maxScroll) * 100),
        );
    }

    async function loadArticlePage(): Promise<void> {
        loading = true;
        error = null;
        related = [];
        similar = [];
        moreFromCategory = [];

        try {
            const articleResponse = await api.getArticle(slug);
            const nextArticle = articleResponse.data?.data as
                | Article
                | undefined;

            if (!nextArticle) {
                throw new Error('Article payload missing');
            }

            article = nextArticle;
            related = nextArticle.related_articles ?? [];
            similar = nextArticle.similar_articles ?? [];
            moreFromCategory = nextArticle.more_from_category ?? [];
        } catch (loadError) {
            if (
                loadError instanceof Error &&
                'status' in loadError &&
                Number(loadError.status) === 404
            ) {
                error = 'not_found';
            } else {
                error =
                    loadError instanceof Error
                        ? loadError.message
                        : 'Не удалось загрузить статью.';
            }
        } finally {
            loading = false;
        }
    }

    async function share(
        platform: (typeof sharePlatforms)[number]['key'],
    ): Promise<void> {
        if (!article) {
            return;
        }

        try {
            const response = await api.shareArticle(article.id, platform);
            const shareUrl = response.data?.share_url as string | undefined;

            article = {
                ...article,
                shares_count: response.data?.total ?? article.shares_count ?? 0,
            };

            if (platform === 'copy') {
                const urlToCopy =
                    shareUrl ||
                    `${window.location.origin}/#/articles/${article.slug}`;

                if (typeof navigator !== 'undefined' && navigator.clipboard) {
                    await navigator.clipboard.writeText(urlToCopy);
                }
            } else if (shareUrl && typeof window !== 'undefined') {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }

            shareMenuOpen = false;
            showToast('Поделились!');
        } catch {
            showToast('Не удалось поделиться');
        }
    }

    onMount(() => {
        void loadBookmarks().catch(() => null);
        void loadArticlePage();
        updateReadProgress();

        if (typeof window === 'undefined') {
            return;
        }

        window.addEventListener('scroll', updateReadProgress, {
            passive: true,
        });

        return () => {
            window.removeEventListener('scroll', updateReadProgress);

            const existing = document.getElementById('json-ld');

            if (existing) {
                existing.remove();
            }
        };
    });

    $effect(() => {
        if (article?.structured_data) {
            injectJsonLd(article.structured_data);
        }
    });

    $effect(() => {
        if (!article) {
            return;
        }

        setSeoMeta({
            title: article.meta_title || article.title,
            description: metaDescription,
            image: article.image_url || undefined,
            url:
                typeof window !== 'undefined'
                    ? `${window.location.origin}/#/articles/${article.slug}`
                    : undefined,
            type: 'article',
            publishedAt: article.published_at || undefined,
            author: article.author || undefined,
            tags: (article.tags ?? []).map((tag) => tag.name),
        });
    });

    $effect(() => {
        const nextArticle = viewsPolling.data as Article | null;

        if (!article || !nextArticle?.id || nextArticle.id !== article.id) {
            return;
        }

        if (nextArticle.views_count !== article.views_count) {
            article = {
                ...article,
                views_count: nextArticle.views_count,
            };
        }
    });
</script>

<AppHead title={article?.title ?? 'Статья'}>
    <meta name="description" content={metaDescription} />
</AppHead>

<div
    class="min-h-screen bg-slate-50 text-slate-900 dark:bg-neutral-950 dark:text-white"
>
    <div
        class="fixed left-0 right-0 top-0 z-50 h-1 bg-slate-200/60 dark:bg-white/10"
    >
        <div
            class="h-full bg-linear-to-r from-sky-500 via-cyan-400 to-blue-600 transition-[width] duration-150"
            style={`width: ${readProgress}%`}
        ></div>
    </div>

    {#if loading}
        <div class="mx-auto max-w-6xl px-4 py-20 lg:px-6">
            <div class="space-y-6">
                <div
                    class="h-5 w-48 animate-pulse rounded-full bg-slate-200 dark:bg-white/10"
                ></div>
                <div
                    class="h-14 w-3/4 animate-pulse rounded-3xl bg-slate-200 dark:bg-white/10"
                ></div>
                <div
                    class="h-96 animate-pulse rounded-[2rem] bg-slate-200 dark:bg-white/10"
                ></div>
                <div
                    class="h-56 animate-pulse rounded-[2rem] bg-slate-200 dark:bg-white/10"
                ></div>
            </div>
        </div>
    {:else if error === 'not_found'}
        <div class="mx-auto max-w-3xl px-4 py-24 text-center lg:px-6">
            <div class="text-7xl">📰</div>
            <h1
                class="mt-6 text-3xl font-semibold text-slate-900 dark:text-white"
            >
                Статья не найдена
            </h1>
            <p class="mt-3 text-slate-500 dark:text-slate-400">
                Возможно, материал был удалён или ссылка устарела.
            </p>
            <a
                href="/#/"
                class="mt-8 inline-flex rounded-full bg-slate-900 px-5 py-3 text-sm font-medium text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
            >
                Вернуться к новостям
            </a>
        </div>
    {:else if error}
        <div class="mx-auto max-w-3xl px-4 py-24 text-center lg:px-6">
            <h1 class="text-3xl font-semibold text-slate-900 dark:text-white">
                Ошибка загрузки
            </h1>
            <p class="mt-3 text-slate-500 dark:text-slate-400">{error}</p>
        </div>
    {:else if article}
        <article class="mx-auto max-w-6xl px-4 py-10 lg:px-6 lg:py-14">
            <header class="mx-auto max-w-4xl">
                <nav
                    class="mb-5 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400"
                >
                    <a
                        href="/#/"
                        class="transition hover:text-slate-900 dark:hover:text-white"
                    >
                        Главная
                    </a>
                    <span>→</span>
                    <a
                        href={`/#/category/${article.category.slug}`}
                        class="transition hover:text-slate-900 dark:hover:text-white"
                    >
                        {article.category.name}
                    </a>
                    <span>→</span>
                    <span
                        class="line-clamp-1 text-slate-700 dark:text-slate-200"
                    >
                        {article.title}
                    </span>
                </nav>

                <div class="mb-5 flex flex-wrap items-center gap-3">
                    {#if contentTypeLabel}
                        <span
                            class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white dark:bg-white dark:text-slate-950"
                        >
                            {contentTypeLabel}
                        </span>
                    {/if}

                    {#if article.is_breaking}
                        <span
                            class="rounded-full bg-red-500 px-3 py-1 text-xs font-semibold text-white"
                        >
                            СРОЧНО
                        </span>
                    {/if}
                </div>

                <h1
                    class="max-w-4xl text-3xl leading-tight font-bold text-slate-950 text-shadow-2xs text-shadow-slate-200/70 sm:text-4xl lg:text-5xl dark:text-white dark:text-shadow-slate-950/70"
                >
                    {article.title}
                </h1>

                <div
                    class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-500 dark:text-slate-400"
                >
                    <span>{publishedDate || article.published_at_date}</span>
                    <span>•</span>
                    <span>{article.author || 'Редакция'}</span>
                    <span>•</span>
                    <span>{article.source_name || 'Источник'}</span>
                    <span>•</span>
                    <span
                        >{article.reading_time_text ||
                            `${article.reading_time ?? 1} мин чтения`}</span
                    >
                    <span>•</span>
                    <span>👁 {article.views_count ?? 0}</span>
                </div>

                {#if article.tags?.length}
                    <div class="mt-5 flex flex-wrap gap-2">
                        {#each article.tags as tag (tag.id)}
                            <a
                                href={`/#/tag/${tag.slug}`}
                                class="rounded-full px-3 py-1 text-xs font-medium text-slate-700 dark:text-slate-200"
                                style={`background-color: ${tag.color ? `${tag.color}22` : '#E2E8F0'}`}
                            >
                                #{tag.name}
                            </a>
                        {/each}
                    </div>
                {/if}
            </header>

            <div class="mx-auto mt-10 max-w-5xl">
                {#if article.image_url}
                    <figure
                        class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5"
                    >
                        <img
                            src={article.image_url}
                            alt={article.title}
                            loading="eager"
                            decoding="async"
                            class="max-h-[32rem] w-full object-cover"
                        />
                        {#if article.image_caption}
                            <figcaption
                                class="border-t border-slate-200 px-5 py-4 text-sm text-slate-500 dark:border-white/10 dark:text-slate-400"
                            >
                                {article.image_caption}
                            </figcaption>
                        {/if}
                    </figure>
                {:else}
                    <div
                        class="flex min-h-80 items-center justify-center rounded-[2rem] border border-slate-200 text-white shadow-sm dark:border-white/10"
                        style={`background: linear-gradient(135deg, ${article.category.color ?? '#2563EB'} 0%, #0f172a 100%)`}
                    >
                        <div class="text-center">
                            <div class="text-7xl">
                                {article.category.icon || '📰'}
                            </div>
                            <div class="mt-4 text-lg font-semibold">
                                {article.category.name}
                            </div>
                        </div>
                    </div>
                {/if}
            </div>

            {#if article.short_description}
                <div
                    class="mx-auto mt-8 max-w-4xl rounded-[1.75rem] border-l-4 border-sky-500 bg-sky-50 p-6 text-base leading-7 text-slate-700 dark:bg-sky-950/30 dark:text-sky-100"
                >
                    {article.short_description}
                </div>
            {/if}

            <div
                class="mx-auto mt-10 grid max-w-6xl gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]"
            >
                <div class="min-w-0 space-y-8">
                    <div
                        class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5 lg:p-8"
                    >
                        <div
                            class={cn(
                                'max-w-none text-base leading-8 text-slate-700 dark:text-slate-200',
                                '[&_a]:font-medium [&_a]:text-sky-600 [&_a]:underline [&_a]:underline-offset-4 dark:[&_a]:text-sky-300',
                                '[&_blockquote]:my-6 [&_blockquote]:border-l-4 [&_blockquote]:border-sky-500 [&_blockquote]:bg-sky-50 [&_blockquote]:px-5 [&_blockquote]:py-4 dark:[&_blockquote]:bg-sky-950/30',
                                '[&_h1]:mt-8 [&_h1]:text-3xl [&_h1]:font-semibold [&_h1]:text-slate-950 dark:[&_h1]:text-white',
                                '[&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-slate-950 dark:[&_h2]:text-white',
                                '[&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-slate-950 dark:[&_h3]:text-white',
                                '[&_img]:my-6 [&_img]:rounded-2xl',
                                '[&_li]:ml-5 [&_li]:list-disc',
                                '[&_p]:my-4',
                            )}
                        >
                            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                            {@html sanitizedContent}
                        </div>

                        {#if article.source_url}
                            <div
                                class="mt-8 border-t border-slate-200 pt-6 dark:border-white/10"
                            >
                                <a
                                    href={article.source_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700 dark:text-sky-300 dark:hover:text-sky-200"
                                >
                                    Читать оригинал на {article.source_name ||
                                        'источнике'} →
                                </a>
                            </div>
                        {/if}
                    </div>

                    <section
                        class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div>
                                <div
                                    class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 dark:text-sky-300"
                                >
                                    Поделиться
                                </div>
                                <div
                                    class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Поделиться:
                                </div>
                            </div>

                            <button
                                type="button"
                                class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 lg:hidden dark:border-white/10 dark:text-slate-200"
                                onclick={() => {
                                    shareMenuOpen = !shareMenuOpen;
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
                                        void share(platform.key);
                                    }}
                                >
                                    {platform.label}
                                </button>
                            {/each}

                            <div
                                class="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-slate-950"
                            >
                                Shares: {article.shares_count ?? 0}
                            </div>
                        </div>
                    </section>

                    <section
                        class="flex flex-wrap items-center gap-4 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5"
                    >
                        <button
                            type="button"
                            class={cn(
                                'rounded-full px-5 py-3 text-sm font-semibold transition',
                                isBookmarked(article.id)
                                    ? 'bg-amber-400 text-slate-950 hover:bg-amber-300'
                                    : 'bg-slate-900 text-white hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200',
                            )}
                            onclick={() => {
                                if (!article) {
                                    return;
                                }

                                void (async () => {
                                    const result = await toggleBookmark(article.id);

                                    showToast(
                                        result.bookmarked
                                            ? 'Статья сохранена в закладки'
                                            : 'Статья удалена из закладок',
                                        result.bookmarked ? 'success' : 'info',
                                    );
                                })();
                            }}
                        >
                            {isBookmarked(article.id)
                                ? 'Удалить из закладок'
                                : 'Сохранить в закладки'}
                        </button>
                    </section>

                    <footer
                        class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5"
                    >
                        <div
                            class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 dark:text-sky-300"
                        >
                            Метаданные статьи
                        </div>

                        {#if article.tags?.length}
                            <div class="mt-4 flex flex-wrap gap-2">
                                {#each article.tags as tag (tag.id)}
                                    <a
                                        href={`/#/tag/${tag.slug}`}
                                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 dark:bg-white/10 dark:text-slate-200"
                                    >
                                        #{tag.name}
                                    </a>
                                {/each}
                            </div>
                        {/if}

                        <dl
                            class="mt-6 grid gap-4 text-sm text-slate-500 sm:grid-cols-2 dark:text-slate-400"
                        >
                            <div>
                                <dt
                                    class="font-medium text-slate-900 dark:text-white"
                                >
                                    Опубликовано
                                </dt>
                                <dd class="mt-1">
                                    {publishedDate || article.published_at_date}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="font-medium text-slate-900 dark:text-white"
                                >
                                    RSS парсинг
                                </dt>
                                <dd class="mt-1">{rssParsedDate}</dd>
                            </div>
                            <div>
                                <dt
                                    class="font-medium text-slate-900 dark:text-white"
                                >
                                    Источник
                                </dt>
                                <dd class="mt-1">
                                    {article.source_name || 'Нет данных'}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="font-medium text-slate-900 dark:text-white"
                                >
                                    RSS feed
                                </dt>
                                <dd class="mt-1">
                                    {article.rss_feed?.title || 'Нет данных'}
                                </dd>
                            </div>
                        </dl>
                    </footer>
                </div>

                <aside class="space-y-6">
                    {#if related.length > 0}
                        <section
                            class="relative overflow-hidden rounded-[2rem] border border-sky-100 bg-linear-to-b from-white via-sky-50/70 to-white p-5 shadow-[0_30px_80px_-50px_rgba(14,165,233,0.55)] dark:border-sky-900/40 dark:from-slate-900 dark:via-sky-950/20 dark:to-slate-900 dark:shadow-[0_30px_80px_-50px_rgba(14,165,233,0.35)]"
                        >
                            <div
                                class="pointer-events-none absolute inset-x-8 top-0 h-px bg-linear-to-r from-transparent via-sky-400/80 to-transparent"
                            ></div>
                            <div
                                class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 text-shadow-2xs text-shadow-sky-200/60 dark:text-sky-300 dark:text-shadow-sky-950/80"
                            >
                                Related content
                            </div>
                            <div
                                class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                            >
                                Самые близкие материалы по теме, тегам и
                                рубрике.
                            </div>
                            <div
                                class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-1"
                            >
                                {#each related.slice(0, 4) as item (item.id)}
                                    <ArticleCard
                                        article={item}
                                        showBookmark={false}
                                    />
                                {/each}
                            </div>
                        </section>
                    {/if}
                </aside>
            </div>

            {#if similar.length > 0}
                <section
                    class="mt-12 overflow-hidden rounded-[2rem] border border-slate-200 bg-white/80 p-6 shadow-sm backdrop-blur-sm dark:border-white/10 dark:bg-white/5"
                >
                    <div
                        class="mb-2 text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 text-shadow-2xs text-shadow-sky-200/60 dark:text-sky-300 dark:text-shadow-sky-950/80"
                    >
                        Similar stories
                    </div>
                    <div
                        class="mb-5 text-sm text-slate-500 dark:text-slate-400"
                    >
                        Подборка материалов с близким сюжетом и совпадающими
                        сигналами.
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
                        More from {article.category.name}
                    </div>
                    <div
                        class="mb-5 text-sm text-slate-500 dark:text-slate-400"
                    >
                        Ещё несколько свежих материалов из этой же рубрики.
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        {#each moreFromCategory as item (item.id)}
                            <ArticleCardCompact article={item} />
                        {/each}
                    </div>
                </section>
            {/if}
        </article>
    {/if}
</div>
