<x-filament-panels::page>
    @php
        $summary = $this->summary;
        $filteredSummary = $this->filteredSummary;
        $activeFilters = $this->activeFilters;
        $logs = $this->logs;
    @endphp

    <div class="space-y-6" data-parse-history-page>
        <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="grid gap-0 xl:grid-cols-[1.05fr_0.95fr]">
                <div class="border-b border-gray-200 bg-gradient-to-br from-amber-100 via-white to-sky-50 p-6 dark:border-white/10 dark:from-amber-500/10 dark:via-gray-900 dark:to-sky-500/10 sm:p-8 xl:border-b-0 xl:border-r">
                    <div class="space-y-5">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                Parse Ops
                            </span>
                            <span class="inline-flex items-center rounded-full bg-sky-100 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                                {{ number_format($summary['runs_today']) }} запусков сегодня
                            </span>
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                {{ number_format($filteredSummary['unique_feeds']) }} лент в выборке
                            </span>
                        </div>

                        <div class="space-y-3">
                            <h2 class="text-2xl font-bold text-gray-950 dark:text-white sm:text-3xl">
                                История запусков и диагностика RSS
                            </h2>
                            <p class="max-w-2xl text-sm leading-7 text-gray-600 dark:text-gray-300">
                                Следите за живым журналом импорта: фильтруйте проблемные периоды, сравнивайте успехи и сбои, раскрывайте детали по ошибкам элементов и быстро возвращайтесь к управлению лентами.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <a
                                href="{{ \App\Filament\Pages\ManageRssFeeds::getUrl() }}"
                                class="inline-flex items-center justify-center rounded-2xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500"
                            >
                                Открыть RSS менеджер
                            </a>

                            <button
                                type="button"
                                wire:click="resetFilters"
                                class="inline-flex items-center justify-center rounded-2xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-gray-400 hover:text-gray-950 dark:border-white/10 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-white/20 dark:hover:text-white"
                            >
                                Сбросить фильтры
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Фильтры журнала
                            </h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                Настройте выборку по ленте, статусу и временному окну. При смене фильтра пагинация и раскрытые записи сбрасываются автоматически.
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block" data-parse-history-feed-filter>
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Лента</span>
                                <select
                                    wire:model.live="feed"
                                    class="block w-full rounded-2xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                                >
                                    <option value="">Все ленты</option>
                                    @foreach ($this->feedOptions as $id => $title)
                                        <option value="{{ $id }}">{{ $title }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Статус</span>
                                <select
                                    wire:model.live="status"
                                    class="block w-full rounded-2xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                                >
                                    <option value="">Все статусы</option>
                                    <option value="success">Успешно</option>
                                    <option value="failure">Сбой</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Дата от</span>
                                <input
                                    type="date"
                                    wire:model.live="dateFrom"
                                    class="block w-full rounded-2xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                                />
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Дата до</span>
                                <input
                                    type="date"
                                    wire:model.live="dateTo"
                                    class="block w-full rounded-2xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                                />
                            </label>
                        </div>

                        @if ($activeFilters !== [])
                            <div class="flex flex-wrap gap-2" data-parse-history-active-filters>
                                @foreach ($activeFilters as $filter)
                                    <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:bg-white/5 dark:text-gray-300">
                                        <span class="text-gray-500 dark:text-gray-400">{{ $filter['label'] }}</span>
                                        <span>{{ $filter['value'] }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5" data-parse-history-summary>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Запусков сегодня</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['runs_today']) }}</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Все сессии парсинга за текущий день.</div>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Сейчас выполняются</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['runs_in_progress']) }}</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Логи без завершения или с пересечением текущего момента.</div>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Средняя длительность</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['average_duration_ms']) }} ms</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Средняя длина цикла за сегодняшний день.</div>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Новых сегодня</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['total_new_today']) }}</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Импорт материалов за день по всем лентам.</div>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Доля ошибок</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $summary['error_rate'] }}%</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Отношение неуспешных запусков к дневному объёму.</div>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" data-parse-history-results>
            <div class="border-b border-gray-200 px-6 py-5 dark:border-white/10">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Журнал запусков</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Карточки логов с быстрым обзором и раскрывающейся диагностикой по конкретному запуску.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs font-semibold">
                        <span class="inline-flex items-center rounded-full bg-sky-100 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                            {{ number_format($filteredSummary['total_runs']) }} записей
                        </span>
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                            {{ number_format($filteredSummary['successful_runs']) }} успешно
                        </span>
                        <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
                            {{ number_format($filteredSummary['failed_runs']) }} сбоев
                        </span>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-gray-700 dark:bg-white/5 dark:text-gray-300">
                            +{{ number_format($filteredSummary['total_new']) }} новых
                        </span>
                    </div>
                </div>
            </div>

            <div class="p-6">
                @if ($logs->count() > 0)
                    <div class="grid gap-4 xl:grid-cols-2" data-parse-history-log-grid>
                        @foreach ($logs as $log)
                            @php
                                $status = $log->success
                                    ? ['label' => 'Успешно', 'classes' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'border' => 'border-emerald-200 dark:border-emerald-500/20']
                                    : ['label' => 'Сбой', 'classes' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300', 'border' => 'border-rose-200 dark:border-rose-500/20'];
                                $trigger = match ((string) $log->triggered_by) {
                                    'scheduler' => ['label' => 'Планировщик', 'classes' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'],
                                    'manual' => ['label' => 'Вручную', 'classes' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300'],
                                    'api' => ['label' => 'API', 'classes' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'],
                                    'filament' => ['label' => 'Filament', 'classes' => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300'],
                                    default => ['label' => \Illuminate\Support\Str::headline((string) $log->triggered_by), 'classes' => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300'],
                                };
                                $categoryColor = $log->rssFeed?->category?->color ?: '#94A3B8';
                                $isExpanded = isset($this->expandedLogs[$log->id]);
                            @endphp

                            <article
                                wire:key="log-{{ $log->id }}"
                                @class([
                                    'relative overflow-hidden rounded-3xl border bg-gray-50/80 p-5 shadow-sm transition dark:bg-white/5',
                                    $status['border'],
                                ])
                                data-parse-log-card
                            >
                                <div class="absolute inset-y-0 left-0 w-1.5" style="background-color: {{ $categoryColor }}"></div>

                                <div class="flex h-full flex-col gap-5 pl-3">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="space-y-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $status['classes'] }}">
                                                    {{ $status['label'] }}
                                                </span>
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $trigger['classes'] }}">
                                                    {{ $trigger['label'] }}
                                                </span>
                                                @if ($log->rssFeed?->category?->name)
                                                    <span class="inline-flex items-center gap-2 rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-gray-950 dark:text-gray-300 dark:ring-white/10">
                                                        <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $categoryColor }}"></span>
                                                        <span class="whitespace-nowrap">{{ $log->rssFeed->category->name }}</span>
                                                    </span>
                                                @endif
                                            </div>

                                            <div>
                                                <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                                                    {{ $log->rssFeed?->title ?? 'Неизвестная лента' }}
                                                </h4>
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $log->started_at?->format('d.m.Y H:i:s') ?? '—' }} · {{ $log->started_at?->diffForHumans() ?? '—' }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-white px-3 py-2 text-right shadow-sm ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Длительность</div>
                                            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                                                {{ number_format((int) $log->duration_ms) }} ms
                                            </div>
                                        </div>
                                    </div>

                                    <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Новые</dt>
                                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) $log->new_count) }}</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Пропущено</dt>
                                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) $log->skip_count) }}</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ошибки</dt>
                                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) $log->error_count) }}</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Элементов</dt>
                                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) $log->total_items) }}</dd>
                                        </div>
                                    </dl>

                                    @if (filled($log->error_message))
                                        <div class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20">
                                            {{ $log->error_message }}
                                        </div>
                                    @endif

                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $isExpanded ? 'Диагностика раскрыта' : 'Раскройте запись, чтобы посмотреть детали ошибок.' }}
                                        </div>

                                        <button
                                            type="button"
                                            wire:click="toggleExpanded({{ $log->id }})"
                                            class="inline-flex items-center justify-center rounded-2xl border border-gray-300 px-3.5 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-gray-400 hover:text-gray-950 dark:border-white/10 dark:text-gray-200 dark:hover:border-white/20 dark:hover:text-white"
                                        >
                                            {{ $isExpanded ? 'Скрыть детали' : 'Показать детали' }}
                                        </button>
                                    </div>

                                    @if ($isExpanded)
                                        <div class="space-y-4 rounded-3xl bg-white p-4 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <div class="grid gap-4 lg:grid-cols-[0.9fr_1.1fr]">
                                                <div class="space-y-2">
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Технические детали</div>
                                                    <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                                        <div>Лента: <span class="font-medium text-gray-900 dark:text-white">{{ $log->rssFeed?->title ?? 'Неизвестная лента' }}</span></div>
                                                        <div>Источник: <span class="font-medium text-gray-900 dark:text-white">{{ $trigger['label'] }}</span></div>
                                                        <div>Начало: <span class="font-medium text-gray-900 dark:text-white">{{ $log->started_at?->format('d.m.Y H:i:s') ?? '—' }}</span></div>
                                                        <div>Окончание: <span class="font-medium text-gray-900 dark:text-white">{{ $log->finished_at?->format('d.m.Y H:i:s') ?? 'Не завершено' }}</span></div>
                                                    </div>
                                                </div>

                                                <div class="space-y-2">
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ошибки элементов</div>

                                                    @if (filled($log->item_errors) && count((array) $log->item_errors) > 0)
                                                        <div class="space-y-2">
                                                            @foreach ((array) $log->item_errors as $itemError)
                                                                <div class="rounded-2xl bg-gray-50 px-3 py-3 text-sm text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                                                                    @if (is_array($itemError))
                                                                        <pre class="overflow-x-auto whitespace-pre-wrap text-xs leading-6 text-gray-700 dark:text-gray-300">{{ json_encode($itemError, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                                    @else
                                                                        {{ (string) $itemError }}
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <div class="rounded-2xl bg-gray-50 px-3 py-3 text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                                                            Для этого запуска ошибки элементов не зафиксированы.
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-3xl bg-gray-50 p-8 text-center ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                        <div class="mx-auto max-w-xl space-y-3">
                            <h4 class="text-lg font-semibold text-gray-950 dark:text-white">Логи не найдены</h4>
                            <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">
                                По текущим фильтрам журнал пуст. Сбросьте фильтры или дождитесь новых запусков парсинга.
                            </p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="border-t border-gray-200 px-4 py-4 dark:border-white/10">
                {{ $logs->links() }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
