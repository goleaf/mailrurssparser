<script lang="ts">
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import AppHead from '@/components/AppHead.svelte';
    import {
        contactUrl,
        homeUrl,
        searchUrl,
        statsUrl,
    } from '@/lib/publicRoutes';

    type InfoVariant = 'about' | 'contact' | 'privacy';

    type InfoCard = {
        title: string;
        body: string;
    };

    type StandardInfoContent = {
        badge: string;
        title: string;
        description: string;
        cards: InfoCard[];
    };

    type PrivacyHighlight = {
        value: string;
        label: string;
        body: string;
    };

    type PrivacySection = {
        badge: string;
        title: string;
        body: string;
        points: string[];
    };

    type PrivacyFact = {
        title: string;
        body: string;
    };

    type PrivacyQuestion = {
        question: string;
        answer: string;
    };

    let { variant }: { variant: InfoVariant } = $props();

    const standardPages: Record<'about' | 'contact', StandardInfoContent> = {
        about: {
            badge: 'О проекте',
            title: 'Публичный интерфейс для собранных RSS-материалов.',
            description:
                'Сайт показывает результаты парсинга, категоризации, поиска и статистики в одной оболочке без зависимости от админки.',
            cards: [
                {
                    title: 'Сбор контента',
                    body: 'Материалы попадают в систему через RSS-ленты, проходят сохранение, нормализацию и разбор по рубрикам.',
                },
                {
                    title: 'Публичная выдача',
                    body: 'Главная, поиск, карточки статей, теги, категории и статистика используют те же API, что и внутренняя система.',
                },
                {
                    title: 'Навигация',
                    body: 'Фильтры, закладки, быстрый поиск и аналитические разделы доступны из одного публичного интерфейса.',
                },
            ],
        },
        contact: {
            badge: 'Контакты',
            title: 'Каналы связи для редакционных и технических вопросов.',
            description:
                'Страница собрана как публичная заглушка для проекта: без вымышленных адресов и с опорой на реальные разделы системы.',
            cards: [
                {
                    title: 'Редакционный поток',
                    body: 'Для проверки контента используйте рубрики, теги и публичную статистику, чтобы быстро найти нужный материал.',
                },
                {
                    title: 'Техническая обратная связь',
                    body: 'Если важно воспроизведение ошибки, откройте конкретную статью, поиск или статистику и зафиксируйте шаги.',
                },
                {
                    title: 'Подписка',
                    body: 'Форма в футере и на сайдбаре работает как единая точка подписки на ежедневную рассылку.',
                },
            ],
        },
    };

    const privacyHighlights: PrivacyHighlight[] = [
        {
            value: '3 слоя',
            label: 'данных',
            body: 'Локальные настройки, события чтения и добровольная email-подписка.',
        },
        {
            value: 'Без аккаунта',
            label: 'для чтения',
            body: 'Главная лента, статьи, теги, категории и статистика доступны публично.',
        },
        {
            value: 'По подтверждению',
            label: 'для рассылки',
            body: 'Email активируется только после письма-подтверждения и может быть отписан в любой момент.',
        },
    ];

    const privacySections: PrivacySection[] = [
        {
            badge: 'Локально в браузере',
            title: 'Что сайт сохраняет прямо в браузере',
            body: 'Публичная часть хранит только те настройки, которые улучшают ваш опыт чтения и не требуют отдельного профиля пользователя.',
            points: [
                'Тема оформления и выбранный режим отображения сохраняются локально, чтобы сайт открывался в привычном светлом или тёмном виде.',
                'История последних поисковых запросов остаётся в браузере и нужна только для быстрого повторного поиска.',
                'Технический cookie appearance помогает применить тему до полной загрузки клиентской части.',
            ],
        },
        {
            badge: 'События чтения',
            title: 'Какие события чтения попадают в систему',
            body: 'Когда открывается статья, система фиксирует технические данные, чтобы не дублировать просмотры и строить общую статистику по порталу.',
            points: [
                'Для защиты от повторного учёта используются ip_hash и session_hash вместо простого публичного идентификатора читателя.',
                'Дополнительно могут учитываться timezone, locale, user-agent, referer и страна запроса, если они доступны в момент просмотра.',
                'Эти события идут в агрегированную аналитику просмотров, популярности материалов и сводные статистические блоки.',
            ],
        },
        {
            badge: 'Закладки',
            title: 'Как работают закладки',
            body: 'Закладки в публичной части не привязываются к отдельному читательскому аккаунту и работают как лёгкая функция текущего браузера.',
            points: [
                'Закладки привязаны к session_hash текущего браузера и используются для вывода сохранённых статей в разделе bookmarks.',
                'В системе хранится связь между article_id и текущей браузерной сессией, чтобы быстро вернуть список ваших закладок.',
                'Если очистить браузерные данные, сменить устройство или заметно изменить окружение браузера, этот список может исчезнуть.',
            ],
        },
        {
            badge: 'Email-подписка',
            title: 'Что происходит при подписке на рассылку',
            body: 'Подписка срабатывает только по вашему действию и всегда проходит через подтверждение email-адреса.',
            points: [
                'Для подписки сохраняются email, а также при наличии имя и выбранные категории рассылки.',
                'Сервис дополнительно записывает IP-адрес, страну, timezone и locale, чтобы корректно обрабатывать подтверждение и отписку.',
                'Письмо становится активным только после подтверждения, а в каждой рассылке предусмотрена персональная ссылка на отписку.',
            ],
        },
    ];

    const privacyFacts: PrivacyFact[] = [
        {
            title: 'Публичное чтение не требует регистрации',
            body: 'Открывать статьи, искать материалы и смотреть статистику можно без отдельного читательского аккаунта.',
        },
        {
            title: 'Последние поиски хранятся только локально',
            body: 'Подсказки по недавним запросам берутся из браузерного хранилища, а не из отдельного публичного профиля.',
        },
        {
            title: 'Внешние рекламные трекеры не подключены',
            body: 'В текущей публичной оболочке нет сторонних рекламных пикселей или маркетинговых скриптов; метрики собираются внутренними средствами портала.',
        },
        {
            title: 'Отписка остаётся под вашим контролем',
            body: 'Если вы оформили рассылку, выйти из неё можно по персональной ссылке из письма без обращения в поддержку.',
        },
    ];

    const privacyQuestions: PrivacyQuestion[] = [
        {
            question: 'Нужен ли аккаунт, чтобы просто читать новости?',
            answer: 'Нет. Для публичного чтения, поиска, тегов, категорий и общей статистики отдельная регистрация не требуется.',
        },
        {
            question: 'Можно ли убрать локальные данные из браузера?',
            answer: 'Да. Для этого достаточно очистить localStorage и cookie браузера: вместе с ними исчезнут последние поиски и сохранённые настройки темы.',
        },
        {
            question: 'Как перестать получать рассылку?',
            answer: 'Используйте персональную ссылку на отписку в письме. Подписка также не активируется, пока адрес не подтверждён.',
        },
    ];

    const standardContent = $derived(
        variant === 'about' || variant === 'contact'
            ? standardPages[variant]
            : null,
    );

    const pageTitle = $derived(
        variant === 'privacy'
            ? 'Как публичная часть портала обращается с данными.'
            : (standardContent?.title ?? ''),
    );
</script>

<AppHead title={pageTitle} />

<div
    class="bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.16),_transparent_28%),linear-gradient(to_bottom,_#f8fbff,_#eef2ff)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.14),_transparent_28%),linear-gradient(to_bottom,_#020617,_#111827)] sm:px-6 lg:px-8"
>
    <div class="mx-auto max-w-7xl">
        {#if variant === 'privacy'}
            <section
                class="relative overflow-hidden rounded-[2.5rem] border border-slate-200/80 bg-white/90 p-6 shadow-[0_30px_100px_-60px_rgba(15,23,42,0.45)] backdrop-blur dark:border-white/10 dark:bg-slate-950/82 sm:p-8 lg:p-10"
            >
                <div
                    class="pointer-events-none absolute -right-12 top-0 h-56 w-56 rounded-full bg-sky-200/60 blur-3xl dark:bg-sky-500/18"
                ></div>
                <div
                    class="pointer-events-none absolute bottom-0 left-0 h-40 w-40 rounded-full bg-emerald-200/70 blur-3xl dark:bg-emerald-500/12"
                ></div>

                <div class="relative grid gap-8 lg:grid-cols-[1.2fr_0.8fr]">
                    <div>
                        <div
                            class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/50 dark:text-sky-300"
                        >
                            Политика данных
                        </div>

                        <h1
                            class="mt-5 max-w-4xl text-4xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-5xl"
                        >
                            Как публичная часть портала обращается с данными.
                        </h1>

                        <p
                            class="mt-4 max-w-3xl text-base leading-7 text-slate-600 dark:text-slate-300"
                        >
                            Эта страница описывает без юридического тумана, что
                            именно использует сайт для чтения материалов,
                            закладок, аналитики просмотров, локальных
                            настроек и email-рассылки.
                        </p>

                        <div
                            class="mt-6 rounded-[1.8rem] border border-slate-200/80 bg-slate-50/90 p-5 dark:border-white/10 dark:bg-white/5"
                        >
                            <div
                                class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400"
                            >
                                Коротко о границах
                            </div>
                            <p
                                class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Публичный интерфейс не строит отдельный профиль
                                читателя для доступа к новостям. Он использует
                                только те данные, которые нужны для показа
                                контента, подсчёта событий чтения, сохранения
                                закладок в текущем браузере и подтверждения
                                подписки.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
                        {#each privacyHighlights as highlight (highlight.label)}
                            <article
                                class="rounded-[1.9rem] border border-slate-200 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.95))] p-5 shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.78))]"
                            >
                                <div
                                    class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-600 dark:text-sky-300"
                                >
                                    {highlight.value}
                                </div>
                                <div
                                    class="mt-2 text-xl font-semibold text-slate-950 dark:text-white"
                                >
                                    {highlight.label}
                                </div>
                                <p
                                    class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    {highlight.body}
                                </p>
                            </article>
                        {/each}
                    </div>
                </div>
            </section>

            <section class="mt-8 grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                <div class="grid gap-6">
                    {#each privacySections as section, index (section.title)}
                        <article
                            class="rounded-[2rem] border border-slate-200 bg-white/92 p-6 shadow-sm dark:border-white/10 dark:bg-slate-950/75"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div>
                                    <div
                                        class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400"
                                    >
                                        {section.badge}
                                    </div>
                                    <h2
                                        class="mt-3 text-2xl font-semibold text-slate-950 dark:text-white sm:text-[1.9rem]"
                                    >
                                        {section.title}
                                    </h2>
                                    <p
                                        class="mt-3 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300"
                                    >
                                        {section.body}
                                    </p>
                                </div>

                                <div
                                    class="inline-flex h-11 min-w-11 items-center justify-center rounded-full bg-slate-900 px-3 text-sm font-semibold text-white dark:bg-white dark:text-slate-950"
                                >
                                    0{index + 1}
                                </div>
                            </div>

                            <ul class="mt-6 grid gap-3">
                                {#each section.points as point (point)}
                                    <li
                                        class="flex gap-3 rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 px-4 py-4 dark:border-white/10 dark:bg-white/5"
                                    >
                                        <span
                                            class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-sky-500 dark:bg-sky-300"
                                        ></span>
                                        <span
                                            class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                        >
                                            {point}
                                        </span>
                                    </li>
                                {/each}
                            </ul>
                        </article>
                    {/each}
                </div>

                <aside class="space-y-6">
                    <section
                        class="rounded-[2rem] border border-slate-200 bg-white/92 p-6 shadow-sm dark:border-white/10 dark:bg-slate-950/75"
                    >
                        <div
                            class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400"
                        >
                            Что важно знать
                        </div>

                        <div class="mt-5 grid gap-4">
                            {#each privacyFacts as fact (fact.title)}
                                <article
                                    class="rounded-[1.6rem] border border-slate-200/80 bg-slate-50/85 p-4 dark:border-white/10 dark:bg-white/5"
                                >
                                    <h3
                                        class="text-base font-semibold text-slate-950 dark:text-white"
                                    >
                                        {fact.title}
                                    </h3>
                                    <p
                                        class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                                    >
                                        {fact.body}
                                    </p>
                                </article>
                            {/each}
                        </div>
                    </section>

                    <section
                        class="rounded-[2rem] border border-slate-200 bg-white/92 p-6 shadow-sm dark:border-white/10 dark:bg-slate-950/75"
                    >
                        <div
                            class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400"
                        >
                            Вопросы и ответы
                        </div>

                        <div class="mt-5 space-y-4">
                            {#each privacyQuestions as item (item.question)}
                                <article
                                    class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/85 p-4 dark:border-white/10 dark:bg-white/5"
                                >
                                    <h3
                                        class="text-base font-semibold text-slate-950 dark:text-white"
                                    >
                                        {item.question}
                                    </h3>
                                    <p
                                        class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                                    >
                                        {item.answer}
                                    </p>
                                </article>
                            {/each}
                        </div>
                    </section>

                    <section
                        class="rounded-[2rem] border border-slate-200 bg-white/92 p-6 shadow-sm dark:border-white/10 dark:bg-slate-950/75"
                    >
                        <div
                            class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400"
                        >
                            Быстрый переход
                        </div>

                        <div class="mt-5 grid gap-3">
                            <a
                                href={homeUrl()}
                                class="flex items-center justify-between rounded-[1.4rem] border border-slate-200 px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                            >
                                <span>Главная лента</span>
                                <ArrowUpRight class="size-4 text-slate-400" />
                            </a>
                            <a
                                href={searchUrl()}
                                class="flex items-center justify-between rounded-[1.4rem] border border-slate-200 px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                            >
                                <span>Поиск</span>
                                <ArrowUpRight class="size-4 text-slate-400" />
                            </a>
                            <a
                                href={statsUrl()}
                                class="flex items-center justify-between rounded-[1.4rem] border border-slate-200 px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                            >
                                <span>Статистика</span>
                                <ArrowUpRight class="size-4 text-slate-400" />
                            </a>
                            <a
                                href={contactUrl()}
                                class="flex items-center justify-between rounded-[1.4rem] border border-slate-200 px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                            >
                                <span>Контакты</span>
                                <ArrowUpRight class="size-4 text-slate-400" />
                            </a>
                        </div>
                    </section>
                </aside>
            </section>
        {:else if standardContent}
            <section
                class="relative overflow-hidden rounded-[2.4rem] border border-slate-200/80 bg-white/90 p-6 shadow-[0_30px_100px_-60px_rgba(15,23,42,0.45)] backdrop-blur dark:border-white/10 dark:bg-slate-950/80 sm:p-8"
            >
                <div
                    class="absolute right-0 top-0 h-40 w-40 rounded-full bg-sky-200/60 blur-3xl dark:bg-sky-500/20"
                ></div>
                <div
                    class="absolute bottom-0 left-0 h-32 w-32 rounded-full bg-amber-200/70 blur-3xl dark:bg-amber-500/10"
                ></div>
                <div class="relative">
                    <div
                        class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/50 dark:text-sky-300"
                    >
                        {standardContent.badge}
                    </div>
                    <h1
                        class="mt-5 max-w-3xl text-4xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-5xl"
                    >
                        {standardContent.title}
                    </h1>
                    <p
                        class="mt-4 max-w-2xl text-base leading-7 text-slate-600 dark:text-slate-300"
                    >
                        {standardContent.description}
                    </p>
                </div>
            </section>

            <section class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                {#each standardContent.cards as card, index (card.title)}
                    <article
                        class="rounded-[2rem] border border-slate-200 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(248,250,252,0.92))] p-6 shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.82))]"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div
                                class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400"
                            >
                                Раздел
                            </div>
                            <div
                                class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white dark:bg-white dark:text-slate-950"
                            >
                                0{index + 1}
                            </div>
                        </div>
                        <h2
                            class="mt-3 text-2xl font-semibold text-slate-950 dark:text-white"
                        >
                            {card.title}
                        </h2>
                        <p
                            class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            {card.body}
                        </p>
                    </article>
                {/each}
            </section>

            <section
                class="mt-8 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900"
            >
                <div
                    class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400"
                >
                    Быстрый переход
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <a
                        href={homeUrl()}
                        class="rounded-2xl border border-slate-200 px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                    >
                        Главная лента
                    </a>
                    <a
                        href={searchUrl()}
                        class="rounded-2xl border border-slate-200 px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                    >
                        Поиск
                    </a>
                    <a
                        href={statsUrl()}
                        class="rounded-2xl border border-slate-200 px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                    >
                        Статистика
                    </a>
                </div>
            </section>
        {/if}
    </div>
</div>
