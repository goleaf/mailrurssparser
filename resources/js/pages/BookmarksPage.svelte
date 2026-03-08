<script lang="ts">
    import { onMount } from 'svelte';
    import { flip } from 'svelte/animate';
    import { fly } from 'svelte/transition';
    import AppHead from '@/components/AppHead.svelte';
    import ArticleCard from '@/components/article/ArticleCard.svelte';
    import SkeletonCard from '@/components/SkeletonCard.svelte';
    import * as api from '@/lib/api';
    import { loadBookmarks, toggleBookmark } from '@/stores/bookmarks.svelte.js';

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

    const count = $derived(articles.length);

    async function loadPage(): Promise<void> {
        loading = true;

        try {
            await loadBookmarks();

            const response = await api.getBookmarks();

            articles = response.data?.data ?? [];
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

        const confirmed = window.confirm(
            'Удалить все статьи из закладок?',
        );

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

<div class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(251,191,36,0.12),_transparent_35%),linear-gradient(to_bottom,_#fffaf3,_#f8fafc)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(250,204,21,0.12),_transparent_35%),linear-gradient(to_bottom,_#020617,_#111827)] sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <section class="rounded-[2rem] border border-amber-200/70 bg-white/90 p-6 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.35)] backdrop-blur dark:border-amber-500/20 dark:bg-slate-950/80 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-600 dark:text-amber-300">
                        🔖 Личная коллекция
                    </div>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-4xl">
                        Мои закладки
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base">
                        Сохраняйте материалы на потом и возвращайтесь к ним в
                        один клик. Сейчас в списке: {count}.
                    </p>
                </div>

                {#if count > 0}
                    <button
                        type="button"
                        class="rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-rose-500/40 dark:hover:bg-rose-950/40 dark:hover:text-rose-200"
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
                <div class="rounded-[2rem] border border-dashed border-slate-300 bg-white p-10 text-center dark:border-white/10 dark:bg-slate-900">
                    <div class="text-7xl">🗂️</div>
                    <h2 class="mt-5 text-2xl font-semibold text-slate-950 dark:text-white">
                        Вы ещё не сохранили ни одной статьи
                    </h2>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                        Открывайте новости и добавляйте важные материалы в
                        закладки, чтобы собрать свою личную ленту.
                    </p>
                    <a
                        href="/#/"
                        class="mt-6 inline-flex items-center rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                    >
                        Перейти к новостям
                    </a>
                </div>
            {:else}
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {#each articles as article (article.id)}
                        <div animate:flip={{ duration: 250 }} out:fly={{ y: 24, duration: 220, opacity: 0 }}>
                            <ArticleCard
                                {article}
                                showBookmark={true}
                                onBookmarkToggle={async ({ articleId, bookmarked }) => {
                                    await handleBookmarkToggle(articleId, bookmarked);
                                }}
                            />
                        </div>
                    {/each}
                </div>
            {/if}
        </section>
    </div>
</div>
