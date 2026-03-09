<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import BreakingNewsTicker from '@/components/layout/BreakingNewsTicker.svelte';
    import Footer from '@/components/layout/Footer.svelte';
    import Header from '@/components/layout/Header.svelte';
    import { toUrl } from '@/lib/utils';
    import ArticleDetailPage from '@/pages/ArticleDetailPage.svelte';
    import BookmarksPage from '@/pages/BookmarksPage.svelte';
    import CategoryPage from '@/pages/CategoryPage.svelte';
    import HomePage from '@/pages/HomePage.svelte';
    import PublicInfoPage from '@/pages/PublicInfoPage.svelte';
    import PublicNotFoundPage from '@/pages/PublicNotFoundPage.svelte';
    import SearchPage from '@/pages/SearchPage.svelte';
    import StatsPage from '@/pages/StatsPage.svelte';
    import TagPage from '@/pages/TagPage.svelte';
    import { dashboard, login, register } from '@/routes';

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

    let {
        canRegister = true,
    }: {
        canRegister?: boolean;
    } = $props();

    const auth = $derived(($page.props.auth ?? {}) as { user?: unknown });
    let currentRoute = $state<PublicRoute>({ name: 'home' });

    function decodeSegment(segment: string): string {
        try {
            return decodeURIComponent(segment);
        } catch {
            return segment;
        }
    }

    function parseRoute(hash: string): PublicRoute {
        const normalizedHash = hash.replace(/^#/, '');
        const [rawPath = '/'] = normalizedHash.split('?');
        const path =
            rawPath === ''
                ? '/'
                : rawPath.startsWith('/')
                  ? rawPath
                  : `/${rawPath}`;

        const segments = path.split('/').filter(Boolean).map(decodeSegment);

        if (segments.length === 0) {
            return { name: 'home' };
        }

        if (segments[0] === 'category' && segments[1]) {
            return { name: 'category', slug: segments.slice(1).join('/') };
        }

        if (segments[0] === 'tag' && segments[1]) {
            return { name: 'tag', slug: segments.slice(1).join('/') };
        }

        if (segments[0] === 'articles' && segments[1]) {
            return { name: 'article', slug: segments.slice(1).join('/') };
        }

        if (segments[0] === 'search') {
            return { name: 'search' };
        }

        if (segments[0] === 'bookmarks') {
            return { name: 'bookmarks' };
        }

        if (segments[0] === 'stats') {
            return { name: 'stats' };
        }

        if (
            segments[0] === 'about' ||
            segments[0] === 'contact' ||
            segments[0] === 'privacy'
        ) {
            return {
                name: 'info',
                variant: segments[0],
            };
        }

        return { name: 'not-found' };
    }

    $effect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const syncRoute = (): void => {
            currentRoute = parseRoute(window.location.hash || '#/');
        };

        syncRoute();
        window.addEventListener('hashchange', syncRoute);

        return () => {
            window.removeEventListener('hashchange', syncRoute);
        };
    });

    $effect(() => {
        const routeKey =
            currentRoute.name === 'category' ||
            currentRoute.name === 'tag' ||
            currentRoute.name === 'article'
                ? `${currentRoute.name}:${currentRoute.slug}`
                : currentRoute.name === 'info'
                  ? `${currentRoute.name}:${currentRoute.variant}`
                  : currentRoute.name;

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
                    {#if auth.user}
                        <Link
                            href={toUrl(dashboard())}
                            class="rounded-full border border-white/10 bg-white/6 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/12"
                        >
                            Панель
                        </Link>
                        <a
                            href="/admin"
                            class="rounded-full border border-sky-400/40 bg-sky-400/12 px-4 py-2 text-sm font-medium text-sky-100 transition hover:bg-sky-400/22"
                        >
                            Админка
                        </a>
                    {:else}
                        <Link
                            href={toUrl(login())}
                            class="rounded-full border border-white/10 bg-white/6 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/12"
                        >
                            Вход
                        </Link>
                        {#if canRegister}
                            <Link
                                href={toUrl(register())}
                                class="rounded-full border border-sky-400/40 bg-sky-400/12 px-4 py-2 text-sm font-medium text-sky-100 transition hover:bg-sky-400/22"
                            >
                                Регистрация
                            </Link>
                        {/if}
                    {/if}
                </nav>
            </div>
        </div>
    </div>

    <BreakingNewsTicker />
    <Header />

    <main class="relative">
        {#if currentRoute.name === 'home'}
            <HomePage />
        {:else if currentRoute.name === 'category'}
            <CategoryPage slug={currentRoute.slug} />
        {:else if currentRoute.name === 'tag'}
            <TagPage slug={currentRoute.slug} />
        {:else if currentRoute.name === 'article'}
            <ArticleDetailPage slug={currentRoute.slug} />
        {:else if currentRoute.name === 'search'}
            <SearchPage />
        {:else if currentRoute.name === 'bookmarks'}
            <BookmarksPage />
        {:else if currentRoute.name === 'stats'}
            <StatsPage />
        {:else if currentRoute.name === 'info'}
            <PublicInfoPage variant={currentRoute.variant} />
        {:else}
            <PublicNotFoundPage />
        {/if}
    </main>

    <Footer />
</div>
