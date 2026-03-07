<script lang="ts">
    import MailCheck from 'lucide-svelte/icons/mail-check';
    import * as api from '@/lib/api';

    let email = $state('');
    let loading = $state(false);
    let submitted = $state(false);
    let errorMessage = $state('');

    async function submit(): Promise<void> {
        const trimmedEmail = email.trim();

        if (!trimmedEmail) {
            errorMessage = 'Укажите email.';

            return;
        }

        loading = true;
        errorMessage = '';

        try {
            await api.subscribe({
                email: trimmedEmail,
            });

            submitted = true;
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
</script>

<aside class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900">
    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 dark:text-sky-300">
        📬 Рассылка
    </div>
    <h3 class="mt-3 text-xl font-semibold text-slate-900 dark:text-white">
        Получайте лучшее на почту
    </h3>
    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
        Короткая подборка важных материалов и заметных событий без лишнего шума.
    </p>

    {#if submitted}
        <div class="mt-5 rounded-3xl bg-emerald-50 p-5 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
            <div class="flex items-center gap-3">
                <MailCheck class="size-5" />
                <span class="font-medium">Спасибо, подписка оформлена.</span>
            </div>
        </div>
    {:else}
        <form
            class="mt-5 space-y-3"
            onsubmit={(event) => {
                event.preventDefault();
                void submit();
            }}
        >
            <input
                bind:value={email}
                type="email"
                class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:bg-white dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:bg-white/8"
                placeholder="you@example.com"
            />

            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                disabled={loading}
            >
                {loading ? 'Отправляем...' : 'Подписаться'}
            </button>

            {#if errorMessage}
                <p class="text-sm text-rose-500 dark:text-rose-300">{errorMessage}</p>
            {/if}
        </form>
    {/if}
</aside>
