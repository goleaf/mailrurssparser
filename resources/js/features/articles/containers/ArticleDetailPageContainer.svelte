<script lang="ts">
    import { onMount } from 'svelte';
    import { showToast } from '@/components/ui/Toast.svelte';
    import { usePolling } from '@/composables/usePolling.js';
    import { injectJsonLd, setSeoMeta } from '@/composables/useSeo.js';
    import {
        ArticleBodyPanel,
        ArticleEngagementPanel,
        ArticleHeroPanel,
        ArticleRelatedSidebar,
        ArticleStoryRail,
        getArticleContentTypeLabel,
    } from '@/features/articles';
    import {
        isBookmarked,
        loadBookmarks,
        toggleBookmark,
    } from '@/features/bookmarks';
    import {
        absolutePublicUrl,
        AppHead,
        articleUrl,
        categoryUrl,
        homeUrl,
        tagUrl,
    } from '@/features/portal';
    import * as api from '@/features/portal';

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
        content_type_label?: string | null;
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

        return response.data as Article | null;
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

    const sanitizedContent = $derived(
        article?.full_content ? sanitizeHtml(article.full_content) : '',
    );

    const metaDescription = $derived(
        article?.meta_description || article?.short_description || '',
    );
    const displaySourceName = $derived(article?.source_name || null);

    const contentTypeLabel = $derived(
        article?.content_type_label ??
            getArticleContentTypeLabel(article?.content_type),
    );
    const headerFacts = $derived(
        article
            ? [
                  {
                      label: 'Формат',
                      value: contentTypeLabel || 'Новость',
                  },
                  {
                      label: 'Чтение',
                      value:
                          article.reading_time_text ||
                          `${article.reading_time ?? 1} мин`,
                  },
                  {
                      label: 'Просмотры',
                      value: `${article.views_count ?? 0}`,
                  },
                  ...(displaySourceName
                      ? [
                            {
                                label: 'Источник',
                                value: displaySourceName,
                            },
                        ]
                      : []),
              ]
            : [],
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
            const nextArticle = articleResponse.data as Article | null;

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
            const shareUrl = response.data?.share_url ?? undefined;
            const sharesCount =
                response.data?.total ?? article.shares_count ?? 0;

            article = {
                ...article,
                shares_count: sharesCount,
            };

            if (platform === 'copy') {
                const urlToCopy =
                    shareUrl ??
                    absolutePublicUrl(articleUrl(article.slug)) ??
                    articleUrl(article.slug);

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
            url: absolutePublicUrl(articleUrl(article.slug)),
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
    class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.14),_transparent_28%),linear-gradient(to_bottom,_#f8fbff,_#eef2ff)] text-slate-900 dark:bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.16),_transparent_28%),linear-gradient(to_bottom,_#020617,_#0f172a)] dark:text-white"
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
                href={homeUrl()}
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
            <ArticleHeroPanel
                {article}
                {publishedDate}
                {displaySourceName}
                {contentTypeLabel}
                {headerFacts}
                homeHref={homeUrl()}
                categoryHref={categoryUrl(article.category.slug)}
                tagHref={tagUrl}
            />

            <div
                class="mx-auto mt-10 grid max-w-6xl gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]"
            >
                <div class="min-w-0 space-y-8">
                    <ArticleBodyPanel
                        sanitizedContent={sanitizedContent}
                        sourceUrl={article.source_url}
                        {displaySourceName}
                    />

                    <ArticleEngagementPanel
                        {sharePlatforms}
                        {shareMenuOpen}
                        sharesCount={article.shares_count ?? 0}
                        bookmarked={isBookmarked(article.id)}
                        on:sharemenutoggle={() => {
                            shareMenuOpen = !shareMenuOpen;
                        }}
                        on:share={(event) => {
                            void share(
                                event.detail as (typeof sharePlatforms)[number]['key'],
                            );
                        }}
                        on:bookmarktoggle={() => {
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
                    />
                </div>

                <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
                    <ArticleRelatedSidebar {related} />
                </aside>
            </div>

            <ArticleStoryRail
                {similar}
                {moreFromCategory}
                categoryName={article.category.name}
            />
        </article>
    {/if}
</div>
