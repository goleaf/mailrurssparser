<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { homeUrl, searchUrl } from '@/lib/publicRoutes';
</script>

<AppHead title="Страница не найдена" />

<div
    class="bg-[radial-gradient(circle_at_top,_rgba(244,114,182,0.18),_transparent_30%),linear-gradient(to_bottom,_#fff7fb,_#eef2ff)] px-4 py-8 dark:bg-[radial-gradient(circle_at_top,_rgba(244,114,182,0.14),_transparent_28%),linear-gradient(to_bottom,_#020617,_#111827)] sm:px-6 lg:px-8"
>
    <div class="mx-auto max-w-4xl">
        <section
            class="rounded-[2.25rem] border border-slate-200/80 bg-white/90 p-8 text-center shadow-[0_30px_100px_-60px_rgba(15,23,42,0.45)] backdrop-blur dark:border-white/10 dark:bg-slate-950/80 sm:p-10"
        >
            <div
                class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400"
            >
                404
            </div>
            <h1
                class="mt-4 text-4xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-5xl"
            >
                Такой публичной страницы здесь нет.
            </h1>
            <p
                class="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-600 dark:text-slate-300"
            >
                Откройте главную ленту, поиск или статистику. Если ссылка вела
                на статью, проверьте, что slug сформирован корректно.
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a
                    href={homeUrl()}
                    class="rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                >
                    На главную
                </a>
                <a
                    href={searchUrl()}
                    class="rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
                >
                    Открыть поиск
                </a>
            </div>
        </section>
    </div>
</div>
