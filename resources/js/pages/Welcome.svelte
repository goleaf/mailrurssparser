<script lang="ts">
    import { onMount } from 'svelte';
    import BreakingNewsTicker from '@/components/layout/BreakingNewsTicker.svelte';
    import Footer from '@/components/layout/Footer.svelte';
    import Header from '@/components/layout/Header.svelte';
    import { replacePublic } from '@/lib/publicRoutes';
    import ArticleDetailPage from '@/pages/ArticleDetailPage.svelte';
    import BookmarksPage from '@/pages/BookmarksPage.svelte';
    import CategoryPage from '@/pages/CategoryPage.svelte';
    import HomePage from '@/pages/HomePage.svelte';
    import PublicInfoPage from '@/pages/PublicInfoPage.svelte';
    import PublicNotFoundPage from '@/pages/PublicNotFoundPage.svelte';
    import SearchPage from '@/pages/SearchPage.svelte';
    import StatsPage from '@/pages/StatsPage.svelte';
    import TagPage from '@/pages/TagPage.svelte';

    /** Route-level code splitting can be added later if the public shell keeps growing. */
    // const ArticleDetailPage = await import('./ArticleDetailPage.svelte');

    type PublicRoute =
        | { name: 'home' }
        | { name: 'category'; slug: string }
        | { name: 'tag'; slug: string }
        | { name: 'article'; slug: string }
        | { name: 'search' }
        | { name: 'bookmarks' }
        | { name: 'stats' }
        | { name: 'info'; variant: 'about' | 'contact' | 'privacy' }
        | { name: 'not-found' };

    let { publicRoute }: { publicRoute: PublicRoute } = $props();

    function decodeSegment(segment: string): string {
        try {
            return decodeURIComponent(segment);
        } catch {
            return segment;
        }
    }

    function legacyHashToPath(hash: string): string | null {
        const normalizedHash = hash.replace(/^#/, '');
        const [rawPath = '/', rawQuery = ''] = normalizedHash.split('?');
        const path =
            rawPath === ''
                ? '/'
                : rawPath.startsWith('/')
                  ? rawPath
                  : `/${rawPath}`;

        const segments = path.split('/').filter(Boolean).map(decodeSegment);
        const query = rawQuery === '' ? '' : `?${rawQuery}`;

        if (segments.length === 0) {
            return '/';
        }

        if (
            segments[0] === 'category' ||
            segments[0] === 'tag' ||
            segments[0] === 'articles'
        ) {
            return `/${segments[0]}/${segments.slice(1).join('/')}${query}`;
        }

        if (
            segments[0] === 'search' ||
            segments[0] === 'bookmarks' ||
            segments[0] === 'stats' ||
            segments[0] === 'about' ||
            segments[0] === 'contact' ||
            segments[0] === 'privacy'
        ) {
            return `/${segments[0]}${query}`;
        }

        return null;
    }

    onMount(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const legacyPath = legacyHashToPath(window.location.hash || '');

        if (!legacyPath) {
            return;
        }

        const currentPath = `${window.location.pathname}${window.location.search}`;

        if (legacyPath === currentPath) {
            window.history.replaceState(null, '', legacyPath);

            return;
        }

        replacePublic(legacyPath);
    });

    $effect(() => {
        const routeKey =
            publicRoute.name === 'category' ||
            publicRoute.name === 'tag' ||
            publicRoute.name === 'article'
                ? `${publicRoute.name}:${publicRoute.slug}`
                : publicRoute.name === 'info'
                  ? `${publicRoute.name}:${publicRoute.variant}`
                  : publicRoute.name;

        void routeKey;

        if (typeof window === 'undefined') {
            return;
        }

        const timer = window.setTimeout(() => {
            window.scrollTo({ top: 0, behavior: 'auto' });
        }, 0);

        return () => {
            window.clearTimeout(timer);
        };
    });
</script>

<div
    class="min-h-screen bg-[linear-gradient(180deg,#f4f8fc_0%,#eef3fb_26%,#f8fafc_100%)] text-slate-950 dark:bg-[linear-gradient(180deg,#020617_0%,#0b1220_40%,#020617_100%)] dark:text-white"
>
    <div
        class="relative overflow-hidden border-b border-slate-200/60 bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.16),transparent_30%),radial-gradient(circle_at_top_right,rgba(251,191,36,0.14),transparent_26%),linear-gradient(135deg,rgba(15,23,42,0.98),rgba(15,23,42,0.9))] text-slate-100 dark:border-white/10"
    >
        <div
            class="pointer-events-none absolute inset-0 bg-[linear-gradient(90deg,transparent,rgba(255,255,255,0.05),transparent)]"
        ></div>
        <div
            class="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-linear-to-r from-transparent via-white/20 to-transparent"
        ></div>
        <div class="mx-auto max-w-7xl px-4 py-4 lg:px-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <span
                        class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-sky-300"
                    >
                        Публичный интерфейс
                    </span>
                    <div
                        class="rounded-full border border-white/10 bg-black/15 px-3 py-1 text-xs text-slate-300"
                    >
                        Публичная оболочка новостного портала
                    </div>
                    <div
                        class="rounded-full border border-white/10 bg-white/6 px-3 py-1 text-xs text-slate-300"
                    >
                        Быстрый доступ к ленте, поиску и аналитике
                    </div>
                </div>

                <nav class="flex flex-wrap items-center gap-2">
                    <a
                        href="/admin"
                        class="rounded-full border border-sky-400/40 bg-sky-400/12 px-4 py-2 text-sm font-medium text-sky-100 transition hover:bg-sky-400/22"
                    >
                        Админка Filament
                    </a>
                </nav>
            </div>
        </div>
    </div>

    <BreakingNewsTicker />
    <Header />

    <main class="relative">
        {#if publicRoute.name === 'home'}
            <HomePage />
        {:else if publicRoute.name === 'category'}
            <CategoryPage slug={publicRoute.slug} />
        {:else if publicRoute.name === 'tag'}
            <TagPage slug={publicRoute.slug} />
        {:else if publicRoute.name === 'article'}
            <ArticleDetailPage slug={publicRoute.slug} />
        {:else if publicRoute.name === 'search'}
            <SearchPage />
        {:else if publicRoute.name === 'bookmarks'}
            <BookmarksPage />
        {:else if publicRoute.name === 'stats'}
            <StatsPage />
        {:else if publicRoute.name === 'info'}
            <PublicInfoPage variant={publicRoute.variant} />
        {:else}
            <PublicNotFoundPage />
        {/if}
    </main>

    <Footer />
</div>
