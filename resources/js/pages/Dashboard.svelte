<script lang="ts">
    import { InfiniteScroll } from '@inertiajs/svelte';
    import Activity from 'lucide-svelte/icons/activity';
    import Eye from 'lucide-svelte/icons/eye';
    import Newspaper from 'lucide-svelte/icons/newspaper';
    import Radio from 'lucide-svelte/icons/radio';
    import AppHead from '@/components/AppHead.svelte';
    import ArticleCard from '@/components/article/ArticleCard.svelte';
    import Skeleton from '@/components/ui/skeleton/Skeleton.svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import { dashboard } from '@/routes';
    import type { BreadcrumbItem } from '@/types';

    type ArticleTag = {
        id: number | string;
        name: string;
        slug: string;
    };

    type ArticleCategory = {
        id?: number | string | null;
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
    };

    type DashboardArticle = {
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

    type DashboardOverviewCategory = {
        id: number | string | null;
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
        article_count: number;
    };

    type DashboardOverviewCountry = {
        country_code: string;
        view_count: number;
    };

    type DashboardOverviewTimezone = {
        timezone: string;
        view_count: number;
    };

    type DashboardOverview = {
        published: number;
        today: number;
        weekly_views: number;
        active_feeds: number;
        top_countries: DashboardOverviewCountry[];
        top_timezones: DashboardOverviewTimezone[];
        top_categories: DashboardOverviewCategory[];
        last_parse?: string | null;
    };

    type ScrollFeed<T> = {
        data: T[];
    };

    let {
        overview,
        articles,
    }: {
        overview: DashboardOverview;
        articles: ScrollFeed<DashboardArticle>;
    } = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Панель',
            href: dashboard(),
        },
    ];

    const numberFormatter = new Intl.NumberFormat('ru-RU');
    const loadingCards = [0, 1, 2];
    const countryFlag = (countryCode: string): string =>
        countryCode
            .toUpperCase()
            .replace(/./g, (character) =>
                String.fromCodePoint(character.charCodeAt(0) + 127397),
            );

    const loadedArticles = $derived(articles.data.length);
    const formattedLastParse = $derived(
        overview.last_parse
            ? new Intl.DateTimeFormat('ru-RU', {
                  dateStyle: 'medium',
                  timeStyle: 'short',
              }).format(new Date(overview.last_parse))
            : 'Ожидает первый запуск парсера',
    );
    const statCards = $derived([
        {
            label: 'Опубликовано',
            value: numberFormatter.format(overview.published),
            caption: 'материалов в живой ленте',
            icon: Newspaper,
            surface:
                'border-sky-200/70 bg-sky-500/10 text-sky-950 dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-100',
            badge: 'bg-sky-500/15 text-sky-700 dark:bg-sky-400/15 dark:text-sky-100',
        },
        {
            label: 'Сегодня',
            value: numberFormatter.format(overview.today),
            caption: 'новых публикаций с полуночи',
            icon: Activity,
            surface:
                'border-emerald-200/70 bg-emerald-500/10 text-emerald-950 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-100',
            badge: 'bg-emerald-500/15 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-100',
        },
        {
            label: 'Просмотры',
            value: numberFormatter.format(overview.weekly_views),
            caption: 'за последние 7 дней',
            icon: Eye,
            surface:
                'border-violet-200/70 bg-violet-500/10 text-violet-950 dark:border-violet-400/20 dark:bg-violet-400/10 dark:text-violet-100',
            badge: 'bg-violet-500/15 text-violet-700 dark:bg-violet-400/15 dark:text-violet-100',
        },
        {
            label: 'Активные RSS',
            value: numberFormatter.format(overview.active_feeds),
            caption: 'лент наполняют поток',
            icon: Radio,
            surface:
                'border-amber-200/70 bg-amber-500/10 text-amber-950 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-100',
            badge: 'bg-amber-500/15 text-amber-700 dark:bg-amber-400/15 dark:text-amber-100',
        },
    ]);
</script>

<AppHead title="Панель" />

<AppLayout {breadcrumbs}>
    <div
        class="relative flex flex-1 flex-col gap-6 overflow-x-hidden p-4 md:p-6"
    >
        <div
            class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-80 bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.2),_transparent_60%),radial-gradient(circle_at_right,_rgba(6,182,212,0.14),_transparent_38%)]"
        ></div>

        <section
            class="relative overflow-hidden rounded-[2rem] border border-slate-200/70 bg-slate-950 text-white shadow-[0_30px_90px_-55px_rgba(2,132,199,0.65)] dark:border-white/10"
        >
            <div
                class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.32),_transparent_34%),radial-gradient(circle_at_bottom_right,_rgba(45,212,191,0.2),_transparent_26%),linear-gradient(135deg,_rgba(2,6,23,0.96),_rgba(15,23,42,0.94),_rgba(8,47,73,0.9))]"
            ></div>

            <div
                class="relative grid gap-6 p-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(18rem,1fr)] lg:p-8"
            >
                <div class="space-y-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <span
                            class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[0.65rem] font-semibold tracking-[0.28em] text-sky-100 uppercase backdrop-blur-xs"
                        >
                            Лента Inertia v2.2
                        </span>
                        <span
                            class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-200"
                        >
                            Последний парсинг: {formattedLastParse}
                        </span>
                    </div>

                    <div class="max-w-3xl space-y-3">
                        <h1
                            class="text-3xl font-semibold tracking-tight text-white md:text-4xl"
                        >
                            Лента редактора теперь догружает публикации по мере
                            прокрутки.
                        </h1>
                        <p
                            class="max-w-2xl text-sm leading-6 text-slate-300 md:text-base"
                        >
                            Панель использует настоящую бесконечную прокрутку
                            Inertia: сервер отдает курсорный поток статей, а
                            интерфейс подхватывает следующие страницы без ручной
                            пагинации и без замены уже загруженных карточек.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <div
                            class="rounded-[1.35rem] border border-white/10 bg-white/6 px-4 py-3 backdrop-blur-xs"
                        >
                            <div
                                class="text-[0.68rem] font-semibold tracking-[0.2em] text-slate-400 uppercase"
                            >
                                Уже в окне
                            </div>
                            <div class="mt-2 text-2xl font-semibold text-white">
                                {numberFormatter.format(loadedArticles)}
                            </div>
                            <div class="mt-1 text-sm text-slate-300">
                                карточек загружено сейчас
                            </div>
                        </div>

                        {#if overview.top_categories.length > 0}
                            <div
                                class="min-w-[16rem] flex-1 rounded-[1.35rem] border border-white/10 bg-white/6 px-4 py-3 backdrop-blur-xs"
                            >
                                <div
                                    class="text-[0.68rem] font-semibold tracking-[0.2em] text-slate-400 uppercase"
                                >
                                    Сильные рубрики
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    {#each overview.top_categories as category (category.slug)}
                                        <a
                                            href={`/#/category/${category.slug}`}
                                            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/8 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-white/15"
                                        >
                                            <span
                                                class="flex size-6 items-center justify-center rounded-full text-[0.7rem]"
                                                style={`background-color:${category.color ?? '#0f172a'}`}
                                            >
                                                {category.icon || '📰'}
                                            </span>
                                            <span>{category.name}</span>
                                            <span class="text-slate-300">
                                                {numberFormatter.format(
                                                    category.article_count,
                                                )}
                                            </span>
                                        </a>
                                    {/each}
                                </div>
                            </div>
                        {/if}

                        {#if overview.top_countries.length > 0}
                            <div
                                class="min-w-[16rem] flex-1 rounded-[1.35rem] border border-white/10 bg-white/6 px-4 py-3 backdrop-blur-xs"
                            >
                                <div
                                    class="text-[0.68rem] font-semibold tracking-[0.2em] text-slate-400 uppercase"
                                >
                                    Аудитория по странам
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    {#each overview.top_countries as country (country.country_code)}
                                        <div
                                            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/8 px-3 py-1.5 text-xs font-medium text-white"
                                        >
                                            <span class="text-sm">
                                                {countryFlag(
                                                    country.country_code,
                                                )}
                                            </span>
                                            <span>{country.country_code}</span>
                                            <span class="text-slate-300">
                                                {numberFormatter.format(
                                                    country.view_count,
                                                )}
                                            </span>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}

                        {#if overview.top_timezones.length > 0}
                            <div
                                class="min-w-[16rem] flex-1 rounded-[1.35rem] border border-white/10 bg-white/6 px-4 py-3 backdrop-blur-xs"
                            >
                                <div
                                    class="text-[0.68rem] font-semibold tracking-[0.2em] text-slate-400 uppercase"
                                >
                                    Часовые пояса читателей
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    {#each overview.top_timezones as timezone (timezone.timezone)}
                                        <div
                                            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/8 px-3 py-1.5 text-xs font-medium text-white"
                                        >
                                            <span class="text-slate-200">
                                                {timezone.timezone}
                                            </span>
                                            <span class="text-slate-300">
                                                {numberFormatter.format(
                                                    timezone.view_count,
                                                )}
                                            </span>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    {#each statCards as card (card.label)}
                        <div
                            class={`rounded-[1.6rem] border p-4 shadow-sm ${card.surface}`}
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div
                                        class="text-[0.68rem] font-semibold tracking-[0.2em] uppercase opacity-70"
                                    >
                                        {card.label}
                                    </div>
                                    <div
                                        class="mt-3 text-3xl font-semibold tracking-tight"
                                    >
                                        {card.value}
                                    </div>
                                    <div class="mt-2 text-sm opacity-80">
                                        {card.caption}
                                    </div>
                                </div>
                                <div class={`rounded-2xl p-2.5 ${card.badge}`}>
                                    <card.icon class="size-5" />
                                </div>
                            </div>
                        </div>
                    {/each}
                </div>
            </div>
        </section>

        <section
            class="rounded-[2rem] border border-slate-200/70 bg-white/90 p-4 shadow-[0_24px_70px_-60px_rgba(15,23,42,0.5)] backdrop-blur-sm dark:border-white/10 dark:bg-slate-950/75 md:p-5"
        >
            <div
                class="mb-4 flex flex-wrap items-end justify-between gap-3 border-b border-slate-200/80 pb-4 dark:border-white/10"
            >
                <div>
                    <div
                        class="text-[0.68rem] font-semibold tracking-[0.24em] text-slate-400 uppercase"
                    >
                        Бесконечный поток
                    </div>
                    <h2
                        class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white"
                    >
                        Последние публикации
                    </h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Прокрутка автоматически подгружает следующую пачку
                        материалов.
                    </p>
                </div>

                <div
                    class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-medium text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                >
                    Загружено {numberFormatter.format(loadedArticles)}
                </div>
            </div>

            <InfiniteScroll
                data="articles"
                onlyNext
                buffer={500}
                as="div"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                {#if articles.data.length > 0}
                    {#each articles.data as article (article.id)}
                        <ArticleCard {article} />
                    {/each}
                {:else}
                    <div
                        class="rounded-[1.6rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-14 text-center text-slate-500 md:col-span-2 xl:col-span-3 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                    >
                        <div
                            class="text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            Публикаций пока нет
                        </div>
                        <div class="mt-2 text-sm">
                            Как только RSS-ленты принесут новые статьи, поток
                            появится здесь.
                        </div>
                    </div>
                {/if}

                <svelte:fragment slot="loading">
                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {#each loadingCards as card (card)}
                            <div
                                class="overflow-hidden rounded-[1.6rem] border border-slate-200/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900"
                            >
                                <Skeleton
                                    class="h-44 w-full rounded-[1.2rem]"
                                />
                                <Skeleton class="mt-4 h-4 w-2/3 rounded-full" />
                                <Skeleton
                                    class="mt-2 h-4 w-full rounded-full"
                                />
                                <Skeleton class="mt-2 h-4 w-5/6 rounded-full" />
                                <div
                                    class="mt-5 flex items-center justify-between gap-3"
                                >
                                    <Skeleton class="h-4 w-20 rounded-full" />
                                    <Skeleton class="h-4 w-24 rounded-full" />
                                </div>
                            </div>
                        {/each}
                    </div>
                </svelte:fragment>

                <svelte:fragment slot="next" let:hasMore let:loading>
                    {#if hasMore}
                        <div class="mt-5 flex justify-center">
                            <div
                                class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                            >
                                {loading
                                    ? 'Подгружаем еще статьи...'
                                    : 'Прокрутите ниже, чтобы загрузить следующую страницу'}
                            </div>
                        </div>
                    {:else if articles.data.length > 0}
                        <div class="mt-5 flex justify-center">
                            <div
                                class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                            >
                                Вся текущая лента уже загружена
                            </div>
                        </div>
                    {/if}
                </svelte:fragment>
            </InfiniteScroll>
        </section>
    </div>
</AppLayout>
