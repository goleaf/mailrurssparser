<x-filament-panels::page>
    @php
        $summary = $this->summary;
        $attentionFeeds = $this->attentionFeeds;
        $recentLogs = $this->recentLogs;
        $tableSignals = $this->tableSignals;
    @endphp

    <div class="space-y-6" data-rss-feeds-page>
        <section
            class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm"
            data-rss-feeds-overview
        >
            <div class="grid gap-0 xl:grid-cols-[1.15fr_0.85fr]">
                <div class="border-b border-slate-200 bg-gradient-to-br from-sky-100 via-white to-emerald-50 p-6 sm:p-8 xl:border-b-0 xl:border-r">
                    <div class="space-y-5">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            <span class="inline-flex items-center gap-2 rounded-full bg-sky-100 px-3 py-1 text-sky-700">
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedRss" class="h-4 w-4" />
                                RSS Desk
                            </span>
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-emerald-700">
                                {{ number_format($summary['total_feeds']) }} лент
                            </span>
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-amber-800">
                                {{ number_format($summary['runs_today']) }} запусков сегодня
                            </span>
                        </div>

                        <div class="space-y-3">
                            <h2 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">
                                Контур RSS-лент
                            </h2>
                            <p class="max-w-3xl text-sm leading-7 text-slate-600">
                                Единая рабочая зона для редакционного импорта: видно, какие ленты стабильны, какие выпали из цикла, сколько материалов пришло сегодня и где нужен ручной запуск прямо сейчас.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            @if (\App\Filament\Resources\RssFeeds\RssFeedResource::canCreate())
                                <a
                                    href="{{ \App\Filament\Resources\RssFeeds\RssFeedResource::getUrl('create') }}"
                                    class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500"
                                >
                                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedPlus" class="h-4 w-4" />
                                    Добавить ленту
                                </a>
                            @endif

                            <a
                                href="{{ \App\Filament\Pages\ManageRssFeeds::getUrl() }}"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                            >
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedBolt" class="h-4 w-4" />
                                RSS менеджер
                            </a>

                            <a
                                href="{{ \App\Filament\Pages\ParseHistory::getUrl() }}"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-transparent px-4 py-2.5 text-sm font-semibold text-primary-600 transition hover:bg-primary-50 hover:text-primary-700"
                            >
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedClock" class="h-4 w-4" />
                                История запусков
                            </a>
                        </div>
                    </div>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="space-y-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">
                                    Краткая сводка
                                </h3>
                                <p class="mt-1 text-sm text-slate-600">
                                    Быстрые сигналы по здоровью RSS-контура без раскрытия таблицы.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Активные ленты</div>
                                <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['active_feeds']) }}</div>
                                <div class="mt-2 text-sm text-slate-500">Сейчас участвуют в автоматическом цикле.</div>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Ждут запуска</div>
                                <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['due_feeds']) }}</div>
                                <div class="mt-2 text-sm text-slate-500">Пора обработать по расписанию или вручную.</div>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">С ошибками</div>
                                <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['failing_feeds']) }}</div>
                                <div class="mt-2 text-sm text-slate-500">Нужны проверка источника и ручной контроль.</div>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Новых сегодня</div>
                                <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['new_today']) }}</div>
                                <div class="mt-2 text-sm text-slate-500">Материалы, которые пришли через RSS за день.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" data-rss-feeds-summary>
            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-slate-500">Покрытие рубрик</div>
                        <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['categories']) }}</div>
                    </div>
                    <span class="rounded-2xl bg-sky-100 p-2 text-sky-700">
                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedFolder" class="h-5 w-5" />
                    </span>
                </div>
                <p class="mt-3 text-sm text-slate-500">Сколько рубрик сейчас реально покрыты RSS-источниками.</p>
            </article>

            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-slate-500">Импортировано статей</div>
                        <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['articles_parsed_total']) }}</div>
                    </div>
                    <span class="rounded-2xl bg-emerald-100 p-2 text-emerald-700">
                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedClipboardDocumentList" class="h-5 w-5" />
                    </span>
                </div>
                <p class="mt-3 text-sm text-slate-500">Исторический объём материалов, пришедших через контур.</p>
            </article>

            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-slate-500">Автоматический ритм</div>
                        <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['runs_today']) }}</div>
                    </div>
                    <span class="rounded-2xl bg-amber-100 p-2 text-amber-800">
                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowPath" class="h-5 w-5" />
                    </span>
                </div>
                <p class="mt-3 text-sm text-slate-500">Количество прогонов за текущий день по всем лентам.</p>
            </article>

            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-slate-500">Нужны действия</div>
                        <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['due_feeds'] + $summary['failing_feeds']) }}</div>
                    </div>
                    <span class="rounded-2xl bg-rose-100 p-2 text-rose-700">
                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle" class="h-5 w-5" />
                    </span>
                </div>
                <p class="mt-3 text-sm text-slate-500">Суммарный объём лент, где нужен ручной контроль или запуск.</p>
            </article>
        </section>

        <section class="grid gap-6 2xl:grid-cols-[1.05fr_0.95fr]">
            <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm" data-rss-feeds-attention>
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950">Сигналы и приоритеты</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Ленты, которые уже выпали из ритма, накопили ошибки или требуют ручного запуска.
                        </p>
                    </div>

                    @if ($tableSignals !== [])
                        <div class="flex flex-wrap gap-2">
                            @foreach ($tableSignals as $signal)
                                <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">
                                    <span class="text-slate-500">{{ $signal['label'] }}</span>
                                    <span>{{ $signal['value'] }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($attentionFeeds as $feed)
                        @php
                            $categoryColor = $feed->category?->color ?: '#94A3B8';
                            $isDue = $feed->is_active && ($feed->next_parse_at === null || $feed->next_parse_at->lte(now()));
                            $status = ! $feed->is_active
                                ? ['label' => 'Отключена', 'classes' => 'bg-slate-100 text-slate-700']
                                : (filled($feed->last_error)
                                    ? ['label' => 'Ошибка', 'classes' => 'bg-rose-100 text-rose-700']
                                    : ['label' => 'Ждёт запуска', 'classes' => 'bg-amber-100 text-amber-800']);
                        @endphp

                        <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4" data-rss-feed-attention-card>
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $categoryColor }}"></span>
                                            <span class="whitespace-nowrap">{{ $feed->category?->name ?? 'Без рубрики' }}</span>
                                        </span>
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $status['classes'] }}">
                                            {{ $status['label'] }}
                                        </span>
                                    </div>

                                    <div>
                                        <h4 class="text-base font-semibold text-slate-950">{{ $feed->title }}</h4>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ $feed->source_name ?: (parse_url((string) $feed->url, PHP_URL_HOST) ?: 'Источник не определён') }}
                                        </p>
                                    </div>

                                    <dl class="grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Последний запуск</dt>
                                            <dd class="mt-1 font-medium text-slate-900">{{ $feed->last_parsed_at?->diffForHumans() ?? 'Никогда' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Следующий запуск</dt>
                                            <dd class="mt-1 font-medium text-slate-900">{{ $feed->next_parse_at?->diffForHumans() ?? 'Не назначен' }}</dd>
                                        </div>
                                    </dl>

                                    @if (filled($feed->last_error))
                                        <div class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200">
                                            {{ \Illuminate\Support\Str::limit((string) $feed->last_error, 180) }}
                                        </div>
                                    @elseif ($isDue)
                                        <div class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                                            Лента активна, но уже ждёт запуска. Можно обработать прямо из таблицы или через RSS менеджер.
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-2 sm:flex-col sm:items-end">
                                    @if (\App\Filament\Resources\RssFeeds\RssFeedResource::canEdit($feed))
                                        <a
                                            href="{{ \App\Filament\Resources\RssFeeds\RssFeedResource::getUrl('edit', ['record' => $feed]) }}"
                                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary-600 px-3.5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500"
                                        >
                                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedPencilSquare" class="h-4 w-4" />
                                            Открыть
                                        </a>
                                    @endif

                                    @if (\App\Filament\Resources\RssFeeds\RssFeedResource::canView($feed))
                                        <a
                                            href="{{ \App\Filament\Resources\RssFeeds\RssFeedResource::getUrl('view', ['record' => $feed]) }}"
                                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                                        >
                                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedEye" class="h-4 w-4" />
                                            Профиль
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedCheckCircle" class="h-6 w-6" />
                            </div>
                            <h4 class="mt-4 text-base font-semibold text-slate-950">Критических сигналов сейчас нет</h4>
                            <p class="mt-2 text-sm text-slate-500">
                                Все активные ленты выглядят стабильно. Таблица ниже остаётся основным рабочим местом для точечных изменений.
                            </p>
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm" data-rss-feeds-recent-logs>
                <div class="border-b border-slate-200 pb-4">
                    <h3 class="text-lg font-semibold text-slate-950">Журнал последних запусков</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Свежие проходы парсинга по лентам с быстрым переходом к логам и профилю ленты.
                    </p>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($recentLogs as $log)
                        @php
                            $status = $log->success
                                ? ['label' => 'Успешно', 'classes' => 'bg-emerald-100 text-emerald-700']
                                : ['label' => 'Сбой', 'classes' => 'bg-rose-100 text-rose-700'];
                        @endphp

                        <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4" data-rss-feed-log-card>
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $status['classes'] }}">
                                            {{ $status['label'] }}
                                        </span>
                                        <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                            {{ $log->triggered_by === 'filament' ? 'Filament' : \Illuminate\Support\Str::headline((string) $log->triggered_by) }}
                                        </span>
                                    </div>

                                    <div>
                                        <h4 class="text-base font-semibold text-slate-950">{{ $log->rssFeed?->title ?? 'Неизвестная лента' }}</h4>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ $log->started_at?->format('d.m.Y H:i:s') ?? '—' }} · {{ $log->started_at?->diffForHumans() ?? '—' }}
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                                        <span class="inline-flex rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">
                                            +{{ number_format((int) $log->new_count) }} новых
                                        </span>
                                        <span class="inline-flex rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">
                                            {{ number_format((int) $log->skip_count) }} пропущено
                                        </span>
                                        <span class="inline-flex rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">
                                            {{ number_format((int) $log->duration_ms) }} ms
                                        </span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2 sm:flex-col sm:items-end">
                                    @if (\App\Filament\Resources\RssParseLogs\RssParseLogResource::canView($log))
                                        <a
                                            href="{{ \App\Filament\Resources\RssParseLogs\RssParseLogResource::getUrl('view', ['record' => $log]) }}"
                                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50 hover:text-slate-950"
                                        >
                                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedClock" class="h-4 w-4" />
                                            Лог
                                        </a>
                                    @endif

                                    @if ($log->rssFeed && \App\Filament\Resources\RssFeeds\RssFeedResource::canView($log->rssFeed))
                                        <a
                                            href="{{ \App\Filament\Resources\RssFeeds\RssFeedResource::getUrl('view', ['record' => $log->rssFeed]) }}"
                                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                                        >
                                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedRss" class="h-4 w-4" />
                                            Лента
                                        </a>
                                    @endif
                                </div>
                            </div>

                            @if (filled($log->error_message))
                                <div class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200">
                                    {{ \Illuminate\Support\Str::limit((string) $log->error_message, 160) }}
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-200 text-slate-600">
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedClock" class="h-6 w-6" />
                            </div>
                            <h4 class="mt-4 text-base font-semibold text-slate-950">Логи пока пусты</h4>
                            <p class="mt-2 text-sm text-slate-500">
                                После первого запуска парсинга здесь появится срез по последним проходам.
                            </p>
                        </div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm" data-rss-feeds-table-shell>
            <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-950">Каталог и настройки лент</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Основная рабочая таблица со встроенными сортировками, поиском, фильтрами и быстрыми действиями Filament.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1.5">
                        Поиск, сортировка и фильтры работают прямо в таблице ниже
                    </span>
                </div>
            </div>

            <div class="mt-6">
                {{ $this->content }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
