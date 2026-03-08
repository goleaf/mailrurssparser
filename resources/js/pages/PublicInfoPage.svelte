<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';

    type InfoVariant = 'about' | 'contact' | 'privacy';

    let { variant }: { variant: InfoVariant } = $props();

    const content = $derived(
        {
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
            privacy: {
                badge: 'Политика данных',
                title: 'Какие данные использует публичная часть сайта.',
                description:
                    'Интерфейс использует только те данные, которые нужны для чтения, фильтрации, закладок, аналитики просмотров и подписки.',
                cards: [
                    {
                        title: 'Закладки и тема',
                        body: 'Тема оформления и часть пользовательских предпочтений сохраняются локально в браузере.',
                    },
                    {
                        title: 'Аналитика просмотров',
                        body: 'Просмотры статей агрегируются для разделов популярности и общей статистики без вывода чувствительных деталей в публичный UI.',
                    },
                    {
                        title: 'Рассылка',
                        body: 'Email используется только для подписки и подтверждения получения новостной подборки.',
                    },
                ],
            },
        }[variant],
    );
</script>

<AppHead title={content.title} />

<div class="bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.16),_transparent_28%),linear-gradient(to_bottom,_#f8fbff,_#eef2ff)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.14),_transparent_28%),linear-gradient(to_bottom,_#020617,_#111827)] sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <section class="relative overflow-hidden rounded-[2.4rem] border border-slate-200/80 bg-white/90 p-6 shadow-[0_30px_100px_-60px_rgba(15,23,42,0.45)] backdrop-blur dark:border-white/10 dark:bg-slate-950/80 sm:p-8">
            <div class="absolute right-0 top-0 h-40 w-40 rounded-full bg-sky-200/60 blur-3xl dark:bg-sky-500/20"></div>
            <div class="absolute bottom-0 left-0 h-32 w-32 rounded-full bg-amber-200/70 blur-3xl dark:bg-amber-500/10"></div>
            <div class="relative">
                <div class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/50 dark:text-sky-300">
                    {content.badge}
                </div>
                <h1 class="mt-5 max-w-3xl text-4xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-5xl">
                    {content.title}
                </h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600 dark:text-slate-300">
                    {content.description}
                </p>
            </div>
        </section>

        <section class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            {#each content.cards as card, index (card.title)}
                <article class="rounded-[2rem] border border-slate-200 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(248,250,252,0.92))] p-6 shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.82))]">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                            Раздел
                        </div>
                        <div class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white dark:bg-white dark:text-slate-950">
                            0{index + 1}
                        </div>
                    </div>
                    <h2 class="mt-3 text-2xl font-semibold text-slate-950 dark:text-white">
                        {card.title}
                    </h2>
                    <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">
                        {card.body}
                    </p>
                </article>
            {/each}
        </section>

        <section class="mt-8 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                Быстрый переход
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <a
                    href="/#/"
                    class="rounded-2xl border border-slate-200 px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                >
                    Главная лента
                </a>
                <a
                    href="/#/search"
                    class="rounded-2xl border border-slate-200 px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                >
                    Поиск
                </a>
                <a
                    href="/#/stats"
                    class="rounded-2xl border border-slate-200 px-4 py-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                >
                    Статистика
                </a>
            </div>
        </section>
    </div>
</div>
