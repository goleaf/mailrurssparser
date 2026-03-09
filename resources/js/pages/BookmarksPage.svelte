<script lang="ts">
    import { onMount } from 'svelte';
    import { flip } from 'svelte/animate';
    import { fly } from 'svelte/transition';
    import { ArticleCard, SkeletonCard } from '@/features/articles';
    import { loadBookmarks, toggleBookmark } from '@/features/bookmarks';
    import { AppHead, homeUrl, searchUrl } from '@/features/portal';
    import * as api from '@/features/portal';
    import {
        prefersReducedMotion,
        resolveFlipAnimation,
        resolveFlyTransition,
    } from '@/lib/motion';

    type Category = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
    };

    type Tag = {
        id: number | string;
        name: string;
        slug: string;
    };

    type Article = {
        id: number | string;
        title: string;
        slug: string;
        short_description?: string | null;
        image_url?: string | null;
        content_type?: string | null;
        is_breaking?: boolean;
        is_recent?: boolean;
        views_count?: number | null;
        reading_time?: number | null;
        published_at?: string | null;
        category: Category;
        tags?: Tag[];
    };

    let articles = $state<Article[]>([]);
    let loading = $state(true);
    let clearing = $state(false);

    const bookmarkListFlip = $derived(
        resolveFlipAnimation($prefersReducedMotion, {
            duration: 250,
        }),
    );
    const count = $derived(articles.length);
    const bookmarkExitTransition = $derived(
        resolveFlyTransition($prefersReducedMotion, {
            duration: 220,
            opacity: 0,
            y: 24,
        }),
    );
    const savedCategoryCount = $derived(
        new Set(articles.map((article) => article.category.slug)).size,
    );
    const featuredCategories = $derived(
        Array.from(
            new Set(articles.map((article) => article.category.name)),
        ).slice(0, 3),
    );

    async function loadPage(): Promise<void> {
        loading = true;

        try {
            await loadBookmarks();

            const response = await api.getBookmarks();

            articles = response.data;
        } finally {
            loading = false;
        }
    }

    async function handleBookmarkToggle(
        articleId: number | string,
        bookmarked: boolean,
    ): Promise<void> {
        if (bookmarked) {
            return;
        }

        articles = articles.filter((article) => article.id !== articleId);
    }

    async function clearAllBookmarks(): Promise<void> {
        if (articles.length === 0 || typeof window === 'undefined') {
            return;
        }

        const confirmed = window.confirm('Удалить все статьи из закладок?');

        if (!confirmed) {
            return;
        }

        clearing = true;

        try {
            for (const article of [...articles]) {
                await toggleBookmark(article.id);
            }

            articles = [];
        } finally {
            clearing = false;
        }
    }

    onMount(() => {
        void loadPage();
    });
</script>

<AppHead title="Мои закладки" />

<div
    class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(251,191,36,0.12),_transparent_35%),linear-gradient(to_bottom,_#fffaf3,_#f8fafc)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(250,204,21,0.12),_transparent_35%),linear-gradient(to_bottom,_#020617,_#111827)] sm:px-6 lg:px-8"
>
    <div class="mx-auto max-w-7xl">
        <section
            class="relative overflow-hidden rounded-[2.3rem] border border-amber-200/70 bg-[linear-gradient(135deg,rgba(255,255,255,0.96),rgba(255,251,235,0.94),rgba(248,250,252,0.96))] p-6 shadow-[0_36px_110px_-60px_rgba(15,23,42,0.42)] backdrop-blur dark:border-amber-500/20 dark:bg-[linear-gradient(135deg,rgba(15,23,42,0.92),rgba(41,37,36,0.9),rgba(15,23,42,0.92))] sm:p-8"
        >
            <div
                class="absolute right-0 top-0 h-44 w-44 rounded-full bg-amber-200/60 blur-3xl dark:bg-amber-500/15"
            ></div>
            <div
                class="absolute bottom-0 left-0 h-32 w-32 rounded-full bg-sky-200/55 blur-3xl dark:bg-sky-500/10"
            ></div>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="relative max-w-3xl">
                    <div
                        class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-600 dark:text-amber-300"
                    >
                        🔖 Личная коллекция
                    </div>
                    <h1
                        class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-4xl"
                    >
                        Мои закладки
                    </h1>
                    <p
                        class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base"
                    >
                        Сохраняйте материалы на потом и возвращайтесь к ним в
                        один клик. Сейчас в списке: {count}.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <div
                            class="rounded-full border border-slate-200/80 bg-white/80 px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                        >
                            Материалов: <span
                                class="font-semibold text-slate-950 dark:text-white"
                                >{count}</span
                            >
                        </div>
                        <div
                            class="rounded-full border border-slate-200/80 bg-white/80 px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                        >
                            Рубрик: <span
                                class="font-semibold text-slate-950 dark:text-white"
                                >{savedCategoryCount}</span
                            >
                        </div>
                        {#if featuredCategories.length > 0}
                            <div
                                class="rounded-full border border-slate-200/80 bg-white/80 px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                            >
                                Фокус: <span
                                    class="font-semibold text-slate-950 dark:text-white"
                                    >{featuredCategories.join(' · ')}</span
                                >
                            </div>
                        {/if}
                    </div>
                </div>

                {#if count > 0}
                    <button
                        type="button"
                        class="relative rounded-full border border-slate-200 bg-white/85 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-rose-500/40 dark:hover:bg-rose-950/40 dark:hover:text-rose-200"
                        onclick={() => {
                            void clearAllBookmarks();
                        }}
                        disabled={clearing}
                    >
                        {clearing ? 'Очищаем...' : 'Очистить всё'}
                    </button>
                {/if}
            </div>
        </section>

        <section class="mt-8">
            {#if loading}
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {#each Array.from({ length: 6 }) as _, index (index)}
                        <SkeletonCard />
                    {/each}
                </div>
            {:else if articles.length === 0}
                <div
                    class="relative overflow-hidden rounded-[2.2rem] border border-dashed border-slate-300 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.96))] p-10 text-center shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.9),rgba(15,23,42,0.82))]"
                >
                    <div
                        class="absolute inset-x-10 top-0 h-px bg-linear-to-r from-transparent via-amber-300/70 to-transparent dark:via-amber-500/30"
                    ></div>
                    <div class="text-7xl">🗂️</div>
                    <h2
                        class="mt-5 text-2xl font-semibold text-slate-950 dark:text-white"
                    >
                        Вы ещё не сохранили ни одной статьи
                    </h2>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                        Открывайте новости и добавляйте важные материалы в
                        закладки, чтобы собрать свою личную ленту.
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <a
                            href={homeUrl()}
                            class="inline-flex items-center rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        >
                            Перейти к новостям
                        </a>
                        <a
                            href={searchUrl()}
                            class="inline-flex items-center rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                        >
                            Открыть поиск
                        </a>
                    </div>
                </div>
            {:else}
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {#each articles as article (article.id)}
                        <div
                            animate:flip={bookmarkListFlip}
                            out:fly={bookmarkExitTransition}
                        >
                            <ArticleCard
                                {article}
                                showBookmark={true}
                                on:bookmarktoggled={async (event) => {
                                    const { articleId, bookmarked } =
                                        event.detail;

                                    await handleBookmarkToggle(
                                        articleId,
                                        bookmarked,
                                    );
                                }}
                            />
                        </div>
                    {/each}
                </div>
            {/if}
        </section>
    </div>
</div>
