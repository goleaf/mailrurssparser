<script lang="ts">
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import MessageSquare from 'lucide-svelte/icons/message-square';
    import Send from 'lucide-svelte/icons/send';
    import * as api from '@/lib/api';
    import { appState, initApp } from '@/stores/app.svelte.js';

    type Category = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
    };

    let email = $state('');
    let loading = $state(false);
    let successMessage = $state('');
    let errorMessage = $state('');

    const categories = $derived((appState.categories ?? []) as Category[]);
    const currentYear = new Date().getFullYear();

    async function submitNewsletter(): Promise<void> {
        const trimmedEmail = email.trim();

        if (!trimmedEmail) {
            errorMessage = 'Укажите email для подписки.';
            successMessage = '';

            return;
        }

        loading = true;
        errorMessage = '';
        successMessage = '';

        try {
            const response = await api.subscribe({
                email: trimmedEmail,
            });

            if (response.data?.already_subscribed) {
                successMessage = 'Этот адрес уже подтверждён в рассылке.';
            } else if (response.data?.resent) {
                successMessage = 'Мы повторно отправили письмо для подтверждения.';
            } else {
                successMessage =
                    response.data?.message ?? 'Проверьте почту для подтверждения.';
            }

            email = '';
        } catch (error) {
            errorMessage =
                error instanceof Error
                    ? error.message
                    : 'Не удалось оформить подписку.';
        } finally {
            loading = false;
        }
    }

    $effect(() => {
        if (!appState.initialized) {
            void initApp();
        }
    });
</script>

<footer class="border-t border-slate-200 bg-slate-950 text-slate-100 dark:border-white/10 dark:bg-black">
    <div class="mx-auto max-w-7xl px-4 py-14 lg:px-6">
        <div class="grid gap-10 lg:grid-cols-[1.1fr_1fr_0.8fr_1.1fr]">
            <section class="space-y-5">
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-12 items-center justify-center rounded-2xl bg-white text-xl text-slate-900"
                    >
                        🗞️
                    </span>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.25em] text-sky-300">
                            News Portal
                        </div>
                        <div class="text-2xl font-semibold">Новости</div>
                    </div>
                </div>

                <p class="max-w-sm text-sm leading-6 text-slate-300">
                    Актуальная лента политики, экономики, общества и спорта с быстрым поиском,
                    метками и удобным чтением на любом устройстве.
                </p>

                <div class="flex flex-wrap gap-3">
                    <a
                        href="https://vk.com"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-2 rounded-full border border-white/10 px-4 py-2 text-sm transition hover:border-sky-300 hover:text-sky-200"
                    >
                        <MessageSquare class="size-4" />
                        VK
                    </a>
                    <a
                        href="https://t.me"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-2 rounded-full border border-white/10 px-4 py-2 text-sm transition hover:border-sky-300 hover:text-sky-200"
                    >
                        <Send class="size-4" />
                        Telegram
                    </a>
                </div>
            </section>

            <section>
                <div class="text-xs font-semibold uppercase tracking-[0.25em] text-sky-300">
                    Категории
                </div>
                <div class="mt-5 grid gap-2 sm:grid-cols-2">
                    {#each categories as category (category.id)}
                        <a
                            href={`/#/category/${category.slug}`}
                            class="flex items-center gap-3 rounded-2xl border border-white/6 bg-white/4 px-3 py-3 text-sm text-slate-200 transition hover:border-white/12 hover:bg-white/8"
                        >
                            <span
                                class="size-2 rounded-full"
                                style={`background-color: ${category.color ?? '#2563EB'};`}
                            ></span>
                            <span class="truncate"
                                >{category.icon ?? '•'} {category.name}</span
                            >
                        </a>
                    {/each}
                </div>
            </section>

            <section class="space-y-3">
                <div class="text-xs font-semibold uppercase tracking-[0.25em] text-sky-300">
                    Быстрые ссылки
                </div>

                <a
                    href="/#/about"
                    class="flex items-center justify-between rounded-2xl border border-white/6 bg-white/4 px-4 py-3 text-sm transition hover:border-white/12 hover:bg-white/8"
                >
                    <span>О проекте</span>
                    <ArrowUpRight class="size-4 text-slate-400" />
                </a>
                <a
                    href="/#/contact"
                    class="flex items-center justify-between rounded-2xl border border-white/6 bg-white/4 px-4 py-3 text-sm transition hover:border-white/12 hover:bg-white/8"
                >
                    <span>Контакты</span>
                    <ArrowUpRight class="size-4 text-slate-400" />
                </a>
                <a
                    href="/#/privacy"
                    class="flex items-center justify-between rounded-2xl border border-white/6 bg-white/4 px-4 py-3 text-sm transition hover:border-white/12 hover:bg-white/8"
                >
                    <span>Политика данных</span>
                    <ArrowUpRight class="size-4 text-slate-400" />
                </a>
                <a
                    href="/rss.xml"
                    class="flex items-center justify-between rounded-2xl border border-white/6 bg-white/4 px-4 py-3 text-sm transition hover:border-white/12 hover:bg-white/8"
                >
                    <span>RSS-лента</span>
                    <ArrowUpRight class="size-4 text-slate-400" />
                </a>
                <a
                    href="/sitemap.xml"
                    class="flex items-center justify-between rounded-2xl border border-white/6 bg-white/4 px-4 py-3 text-sm transition hover:border-white/12 hover:bg-white/8"
                >
                    <span>Карта сайта</span>
                    <ArrowUpRight class="size-4 text-slate-400" />
                </a>
            </section>

            <section class="rounded-[2rem] border border-white/10 bg-white/5 p-6">
                <div class="text-xs font-semibold uppercase tracking-[0.25em] text-sky-300">
                    Рассылка
                </div>
                <h3 class="mt-3 text-2xl font-semibold">Лучшее за день на почту</h3>
                <p class="mt-2 text-sm leading-6 text-slate-300">
                    Подписка без лишнего шума. Только важные материалы и срочные новости.
                </p>

                <form
                    class="mt-6 space-y-3"
                    onsubmit={(event) => {
                        event.preventDefault();
                        void submitNewsletter();
                    }}
                >
                    <input
                        bind:value={email}
                        type="email"
                        class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-sky-400"
                        placeholder="you@example.com"
                    />

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-2xl bg-sky-500 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-sky-400 disabled:cursor-not-allowed disabled:opacity-60"
                        disabled={loading}
                    >
                        {loading ? 'Отправляем...' : 'Подписаться'}
                    </button>
                </form>

                {#if successMessage}
                    <p class="mt-3 text-sm text-emerald-300">{successMessage}</p>
                {/if}

                {#if errorMessage}
                    <p class="mt-3 text-sm text-rose-300">{errorMessage}</p>
                {/if}
            </section>
        </div>

        <div class="mt-10 border-t border-white/10 pt-6 text-sm text-slate-400">
            © {currentYear} Новости. Все права защищены.
        </div>
    </div>
</footer>
