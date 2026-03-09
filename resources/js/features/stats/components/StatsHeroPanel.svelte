<script lang="ts">
    import Radio from 'lucide-svelte/icons/radio';
    import type { StatsOverview } from '@/features/portal';
    import {
        formatNumber,
        formatRelativeDate,
    } from '@/features/stats/lib/stats';

    interface Props {
        overview: StatsOverview | null;
    }

    let { overview }: Props = $props();
</script>

<section
    class="relative overflow-hidden rounded-[2.3rem] border border-slate-200/80 bg-white/90 p-6 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.35)] backdrop-blur dark:border-white/10 dark:bg-slate-950/80 sm:p-8"
>
    <div
        class="absolute right-0 top-0 h-40 w-40 rounded-full bg-sky-200/50 blur-3xl dark:bg-sky-500/20"
    ></div>
    <div
        class="absolute bottom-0 left-0 h-32 w-32 rounded-full bg-amber-200/50 blur-3xl dark:bg-amber-500/10"
    ></div>
    <div class="flex flex-wrap items-end justify-between gap-6">
        <div class="max-w-3xl">
            <div
                class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/50 dark:text-sky-300"
            >
                <Radio class="size-4" />
                Живая аналитика
            </div>
            <h1
                class="mt-5 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-4xl"
            >
                Пульс редакции и поведение аудитории
            </h1>
            <p
                class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base"
            >
                Единая панель с публикациями, просмотрами, долей рубрик и
                работой RSS-лент. Подходит и для быстрого обзора, и для анализа
                ритма новостей.
            </p>

            <div class="mt-5 flex flex-wrap gap-3">
                <div
                    class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                >
                    {formatNumber(overview?.articles.total ?? 0)} материалов
                </div>
                <div
                    class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                >
                    {formatNumber(overview?.views.total ?? 0)} просмотров
                </div>
                <div
                    class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                >
                    {formatNumber(overview?.feeds.active ?? 0)} активных лент
                </div>
            </div>
        </div>

        {#if overview?.last_parse}
            <div
                class="rounded-[1.75rem] border border-slate-200 bg-slate-50 px-5 py-4 dark:border-white/10 dark:bg-white/5"
            >
                <div
                    class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400"
                >
                    Последний парсинг
                </div>
                <div
                    class="mt-2 text-sm font-medium text-slate-900 dark:text-white"
                >
                    {formatRelativeDate(overview.last_parse)}
                </div>
            </div>
        {/if}
    </div>
</section>
