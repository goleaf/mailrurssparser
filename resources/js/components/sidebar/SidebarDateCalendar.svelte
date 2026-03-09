<script lang="ts">
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import * as api from '@/lib/api';
    import { cn, formatNumber } from '@/lib/utils';
    import { clearDate, filters, setDate } from '@/stores/articles.svelte.js';

    const weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    const monthNames = [
        'Январь',
        'Февраль',
        'Март',
        'Апрель',
        'Май',
        'Июнь',
        'Июль',
        'Август',
        'Сентябрь',
        'Октябрь',
        'Ноябрь',
        'Декабрь',
    ];

    let currentYear = $state(new Date().getFullYear());
    let currentMonth = $state(new Date().getMonth() + 1);
    let calendarData = $state<Record<string, number>>({});
    let loading = $state(false);

    const daysInMonth = $derived(
        new Date(currentYear, currentMonth, 0).getDate(),
    );
    const firstWeekday = $derived(
        (new Date(currentYear, currentMonth - 1, 1).getDay() + 6) % 7,
    );
    const monthLabel = $derived(
        `${monthNames[currentMonth - 1]} ${currentYear}`,
    );
    const totalArticles = $derived(
        Object.values(calendarData).reduce((sum, count) => sum + count, 0),
    );

    function formatDay(day: number): string {
        return `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(
            day,
        ).padStart(2, '0')}`;
    }

    function isToday(day: number): boolean {
        const today = new Date();

        return (
            currentYear === today.getFullYear() &&
            currentMonth === today.getMonth() + 1 &&
            day === today.getDate()
        );
    }

    function prevMonth(): void {
        if (currentMonth === 1) {
            currentMonth = 12;
            currentYear -= 1;

            return;
        }

        currentMonth -= 1;
    }

    function nextMonth(): void {
        if (currentMonth === 12) {
            currentMonth = 1;
            currentYear += 1;

            return;
        }

        currentMonth += 1;
    }

    $effect(() => {
        const year = currentYear;
        const month = currentMonth;
        let cancelled = false;

        loading = true;

        void api
            .getCalendar(year, month)
            .then((response) => {
                if (!cancelled) {
                    calendarData = response.data;
                }
            })
            .catch(() => {
                if (!cancelled) {
                    calendarData = {};
                }
            })
            .finally(() => {
                if (!cancelled) {
                    loading = false;
                }
            });

        return () => {
            cancelled = true;
        };
    });
</script>

<aside
    class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900"
>
    <div class="mb-4 flex items-center justify-between">
        <div
            class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-600 dark:text-sky-300"
        >
            📅 Календарь
        </div>

        {#if filters.date}
            <button
                type="button"
                class="text-xs font-medium text-slate-500 transition hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"
                onclick={() => {
                    clearDate();
                }}
            >
                Сбросить дату
            </button>
        {/if}
    </div>

    <div class="mb-4 flex items-center justify-between">
        <button
            type="button"
            class="inline-flex size-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white"
            onclick={prevMonth}
            aria-label="Предыдущий месяц"
        >
            <ChevronLeft class="size-4" />
        </button>

        <div class="text-sm font-semibold text-slate-900 dark:text-white">
            {monthLabel}
        </div>

        <button
            type="button"
            class="inline-flex size-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white"
            onclick={nextMonth}
            aria-label="Следующий месяц"
        >
            <ChevronRight class="size-4" />
        </button>
    </div>

    <div
        class="grid grid-cols-7 gap-1 text-center text-[0.7rem] font-semibold uppercase tracking-wide text-slate-400"
    >
        {#each weekdays as weekday (weekday)}
            <div class="py-2">{weekday}</div>
        {/each}
    </div>

    <div class="relative mt-2 grid grid-cols-7 gap-1">
        {#if loading}
            <div
                class="absolute inset-0 rounded-3xl bg-white/70 backdrop-blur-xs dark:bg-neutral-900/70"
            ></div>
        {/if}

        {#each Array.from( { length: firstWeekday }, ) as _, index (`empty-${index}`)}
            <div class="aspect-square"></div>
        {/each}

        {#each Array.from({ length: daysInMonth }, (_, index) => index + 1) as day, index (`day-${currentYear}-${currentMonth}-${index + 1}`)}
            {@const count = calendarData[String(day)] ?? 0}
            {@const selected = filters.date === formatDay(day)}
            <button
                type="button"
                class={cn(
                    'relative aspect-square rounded-2xl text-sm transition',
                    count > 0
                        ? 'bg-sky-50 font-semibold text-sky-800 hover:bg-sky-100 dark:bg-sky-950/40 dark:text-sky-200 dark:hover:bg-sky-950/60'
                        : 'text-slate-500 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5',
                    selected &&
                        'bg-sky-500 text-white hover:bg-sky-500 dark:bg-sky-500 dark:text-white',
                    isToday(day) &&
                        !selected &&
                        'ring-2 ring-sky-300 dark:ring-sky-700',
                )}
                onclick={() => {
                    setDate(formatDay(day));
                }}
                title={count > 0 ? `${count} статей` : undefined}
            >
                <span>{day}</span>

                {#if count > 0}
                    <span
                        class={cn(
                            'absolute bottom-1 left-1/2 size-1.5 -translate-x-1/2 rounded-full',
                            selected
                                ? 'bg-white'
                                : 'bg-sky-500 dark:bg-sky-300',
                        )}
                    ></span>
                {/if}
            </button>
        {/each}
    </div>

    <div class="mt-4 text-sm text-slate-500 dark:text-slate-400">
        За месяц:
        <span class="font-semibold text-slate-900 dark:text-white"
            >{formatNumber(totalArticles)}</span
        >
    </div>
</aside>
