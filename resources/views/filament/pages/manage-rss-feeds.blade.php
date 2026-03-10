<x-filament-panels::page>
    @php
        $groupedFeeds = $this->groupedFeeds;
        $summary = $this->summary;
        $resultSummary = $this->resultSummary;
        $priorityFeeds = $this->priorityFeeds;
        $latestRuns = $this->latestRuns;
        $activeFilters = $this->activeFilters;
    @endphp

    <div class="space-y-6" data-rss-manager-page>
        <section
            class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm"
            data-rss-manager-hero
        >
            <div class="grid gap-0 xl:grid-cols-[1.15fr_0.85fr]">
                <div class="border-b border-slate-200 bg-gradient-to-br from-sky-100 via-white to-emerald-50 p-6 sm:p-8 xl:border-b-0 xl:border-r">
                    <div class="space-y-5">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            <span class="inline-flex items-center gap-2 rounded-full bg-rose-100 px-3 py-1 text-rose-700">
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedBolt" class="h-4 w-4" />
                                RSS Ops
                            </span>
                            <span class="inline-flex items-center rounded-full bg-sky-100 px-3 py-1 text-sky-700">
                                {{ number_format($summary['total_feeds']) }} лент
                            </span>
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-emerald-700">
                                {{ number_format($summary['healthy_feeds']) }} стабильных
                            </span>
                        </div>

                        <div class="space-y-3">
                            <h2 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">
                                Операционный пульт RSS
                            </h2>
                            <p class="max-w-3xl text-sm leading-7 text-slate-600">
                                Вся RSS-инфраструктура в одном экране: фильтруйте проблемные источники, находите ленты по названию и источнику, запускайте разбор вручную и отслеживайте свежую активность без перехода в отдельные разделы.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                wire:click="parseAll"
                                wire:loading.attr="disabled"
                                wire:target="parseAll"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowPath" class="h-4 w-4" />
                                <span wire:loading.remove wire:target="parseAll">Запустить весь контур</span>
                                <span wire:loading wire:target="parseAll">Обрабатываем все ленты...</span>
                            </button>

                            <a
                                href="{{ \App\Filament\Resources\RssFeeds\RssFeedResource::getUrl('index') }}"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                            >
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedRss" class="h-4 w-4" />
                                Каталог лент
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
                    <div class="space-y-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">
                                    Фокус и фильтры
                                </h3>
                                <p class="mt-1 text-sm text-slate-600">
                                    Показываем {{ number_format($summary['filtered_feeds']) }} из {{ number_format($summary['total_feeds']) }} лент в текущем срезе.
                                </p>
                            </div>

                            @if ($isParsing)
                                <span class="inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                    <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-amber-500"></span>
                                    Парсинг выполняется
                                </span>
                            @endif
                        </div>

                        <div class="grid gap-4">
                            <label class="block" data-rss-filter-search>
                                <span class="mb-2 block text-sm font-medium text-slate-700">Поиск по лентам</span>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">
                                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedMagnifyingGlass" class="h-4 w-4" />
                                    </span>
                                    <input
                                        type="search"
                                        wire:model.live.debounce.300ms="search"
                                        placeholder="Название, URL, источник, ошибка"
                                        class="block w-full rounded-2xl border border-slate-300 bg-white py-3 pr-4 pl-11 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                </div>
                            </label>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block" data-rss-filter-select>
                                    <span class="mb-2 block text-sm font-medium text-slate-700">Рубрика</span>
                                    <select
                                        wire:model.live="filterCategory"
                                        class="block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    >
                                        <option value="">Все рубрики</option>
                                        @foreach ($this->categoryOptions as $slug => $name)
                                            <option value="{{ $slug }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="block" data-rss-filter-status>
                                    <span class="mb-2 block text-sm font-medium text-slate-700">Состояние</span>
                                    <select
                                        wire:model.live="status"
                                        class="block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    >
                                        <option value="all">Все состояния</option>
                                        <option value="healthy">Стабильные</option>
                                        <option value="active">Активные</option>
                                        <option value="due">Ждут запуска</option>
                                        <option value="failing">С ошибкой</option>
                                        <option value="inactive">Отключённые</option>
                                    </select>
                                </label>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    wire:click="resetFilters"
                                    class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                                >
                                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedXMark" class="h-4 w-4" />
                                    Сбросить фильтры
                                </button>

                                <div class="text-sm text-slate-500">
                                    {{ number_format($summary['due_feeds']) }} ждут запуска, {{ number_format($summary['failing_feeds']) }} требуют внимания.
                                </div>
                            </div>
                        </div>

                        @if ($activeFilters !== [])
                            <div class="flex flex-wrap gap-2" data-rss-manager-active-filters>
                                @foreach ($activeFilters as $filter)
                                    <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">
                                        <span class="text-slate-500">{{ $filter['label'] }}</span>
                                        <span>{{ $filter['value'] }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6" data-rss-manager-summary>
            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-slate-500">Лент в фокусе</div>
                        <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['filtered_feeds']) }}</div>
                    </div>
                    <span class="rounded-2xl bg-sky-100 p-2 text-sky-700">
                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedSquares2x2" class="h-5 w-5" />
                    </span>
                </div>
                <p class="mt-3 text-sm text-slate-500">Текущая рабочая выборка после поиска и фильтров.</p>
            </article>

            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">Стабильные</div>
                <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['healthy_feeds']) }}</div>
                <div class="mt-2 text-sm text-slate-500">Активные и без свежих сигналов.</div>
            </article>

            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">Ждут запуска</div>
                <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['due_feeds']) }}</div>
                <div class="mt-2 text-sm text-slate-500">Можно обработать прямо с карточки.</div>
            </article>

            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">С ошибкой</div>
                <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['failing_feeds']) }}</div>
                <div class="mt-2 text-sm text-slate-500">Нужна проверка источника или парсера.</div>
            </article>

            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">Новых в цикле</div>
                <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($summary['new_last_run_total']) }}</div>
                <div class="mt-2 text-sm text-slate-500">Материалы из последнего прохода.</div>
            </article>
        </section>

        <section class="grid gap-6 2xl:grid-cols-[1.08fr_0.92fr]">
            <article
                class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
                data-rss-manager-priority
            >
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950">Приоритетная очередь</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Самые важные ленты в текущем срезе: ошибки, отставание от расписания и отключённые источники.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1.5">
                            {{ count($priorityFeeds) }} в фокусе
                        </span>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-2">
                    @forelse ($priorityFeeds as $feed)
                        @php
                            $categoryColor = data_get($feed, 'category.color') ?: '#94A3B8';
                            $isDue = ($feed['is_active'] ?? false) && (! filled($feed['next_parse_at'] ?? null) || \Illuminate\Support\Carbon::parse($feed['next_parse_at'])->lte(now()));
                            $status = ! ($feed['is_active'] ?? false)
                                ? ['label' => 'Отключена', 'classes' => 'bg-slate-100 text-slate-700']
                                : (filled($feed['last_error'] ?? null)
                                    ? ['label' => 'С ошибкой', 'classes' => 'bg-rose-100 text-rose-700']
                                    : ['label' => 'Ждёт запуска', 'classes' => 'bg-amber-100 text-amber-800']);
                        @endphp

                        <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4" data-rss-priority-card>
                            <div class="space-y-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $categoryColor }}"></span>
                                                <span class="whitespace-nowrap">{{ data_get($feed, 'category.name', 'Без рубрики') }}</span>
                                            </span>
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $status['classes'] }}">
                                                {{ $status['label'] }}
                                            </span>
                                        </div>

                                        <div>
                                            <h4 class="text-base font-semibold text-slate-950">{{ $feed['title'] }}</h4>
                                            <p class="mt-1 text-sm text-slate-500">
                                                {{ $feed['source_name'] ?: (parse_url((string) $feed['url'], PHP_URL_HOST) ?: 'Источник не определён') }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="rounded-2xl bg-white px-3 py-2 text-right ring-1 ring-slate-200">
                                        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Новых</div>
                                        <div class="mt-1 text-lg font-semibold text-slate-950">
                                            +{{ number_format((int) ($feed['last_run_new_count'] ?? 0)) }}
                                        </div>
                                    </div>
                                </div>

                                <dl class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Последний запуск</dt>
                                        <dd class="mt-1 text-sm font-medium text-slate-900">
                                            {{ filled($feed['last_parsed_at'] ?? null) ? \Illuminate\Support\Carbon::parse($feed['last_parsed_at'])->diffForHumans() : 'Никогда' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Следующий запуск</dt>
                                        <dd class="mt-1 text-sm font-medium text-slate-900">
                                            {{ filled($feed['next_parse_at'] ?? null) ? \Illuminate\Support\Carbon::parse($feed['next_parse_at'])->diffForHumans() : 'Не назначен' }}
                                        </dd>
                                    </div>
                                </dl>

                                @if (filled($feed['last_error'] ?? null))
                                    <div class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200">
                                        {{ \Illuminate\Support\Str::limit((string) $feed['last_error'], 170) }}
                                    </div>
                                @elseif ($isDue)
                                    <div class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                                        Активная лента уже вышла на следующий цикл. Её стоит прогнать вручную или проверить расписание.
                                    </div>
                                @endif

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        wire:click="parseFeed({{ $feed['id'] }})"
                                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary-600 px-3.5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                        @disabled($isParsing)
                                    >
                                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowPath" class="h-4 w-4" />
                                        @if ($isParsing && $parsingFeedId === $feed['id'])
                                            Идёт парсинг...
                                        @else
                                            Запустить
                                        @endif
                                    </button>

                                    <a
                                        href="{{ \App\Filament\Resources\RssFeeds\RssFeedResource::getUrl('edit', ['record' => $feed['id']]) }}"
                                        class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                                    >
                                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedPencilSquare" class="h-4 w-4" />
                                        Редактировать
                                    </a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center xl:col-span-2">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedCheckCircle" class="h-6 w-6" />
                            </div>
                            <h4 class="mt-4 text-base font-semibold text-slate-950">Критических лент в выборке нет</h4>
                            <p class="mt-2 text-sm text-slate-500">
                                Текущие фильтры показывают только стабильные источники. Измените условия справа, чтобы посмотреть проблемные зоны.
                            </p>
                        </div>
                    @endforelse
                </div>
            </article>

            <article
                class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
                data-rss-manager-activity
            >
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-5">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950">Живой контур запусков</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Последние сохранённые прогоны и быстрый доступ к тому, что происходило в RSS-системе совсем недавно.
                        </p>
                    </div>

                    @if ($this->results !== [])
                        <div class="grid gap-3 sm:grid-cols-3" data-rss-manager-results>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Запусков</div>
                                <div class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($resultSummary['runs']) }}</div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Успешно</div>
                                <div class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($resultSummary['successful_runs']) }}</div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Новых</div>
                                <div class="mt-2 text-2xl font-semibold text-slate-950">+{{ number_format($resultSummary['new']) }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($latestRuns as $run)
                        <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4" data-rss-activity-card>
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $run['success'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                            {{ $run['success'] ? 'Успешно' : 'Сбой' }}
                                        </span>
                                        <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                            {{ $run['triggered_by'] }}
                                        </span>
                                        @if ($run['category_name'])
                                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $run['category_color'] }}"></span>
                                                <span class="whitespace-nowrap">{{ $run['category_name'] }}</span>
                                            </span>
                                        @endif
                                    </div>

                                    <div>
                                        <h4 class="text-base font-semibold text-slate-950">{{ $run['feed_title'] }}</h4>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ $run['started_at_label'] }} · {{ $run['started_at_human'] }}
                                        </p>
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white px-3 py-2 text-right ring-1 ring-slate-200">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Длительность</div>
                                    <div class="mt-1 text-lg font-semibold text-slate-950">{{ number_format($run['duration_ms']) }} ms</div>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                                <span class="inline-flex rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">
                                    +{{ number_format($run['new_count']) }} новых
                                </span>
                                <span class="inline-flex rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">
                                    {{ number_format($run['skip_count']) }} пропущено
                                </span>
                                <span class="inline-flex rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">
                                    {{ number_format($run['error_count']) }} ошибок
                                </span>
                            </div>

                            @if (filled($run['error_message']))
                                <div class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200">
                                    {{ \Illuminate\Support\Str::limit((string) $run['error_message'], 160) }}
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-200 text-slate-600">
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedClock" class="h-6 w-6" />
                            </div>
                            <h4 class="mt-4 text-base font-semibold text-slate-950">История запусков пока пуста</h4>
                            <p class="mt-2 text-sm text-slate-500">
                                После первого успешного или неуспешного прохода здесь появится живая активность по RSS-контуру.
                            </p>
                        </div>
                    @endforelse
                </div>
            </article>
        </section>

        @forelse ($groupedFeeds as $categoryName => $feeds)
            @php
                $categoryColor = collect($feeds)->pluck('category.color')->filter()->first() ?: '#64748B';
                $activeCount = collect($feeds)->filter(fn (array $feed): bool => (bool) ($feed['is_active'] ?? false))->count();
                $failingCount = collect($feeds)->filter(fn (array $feed): bool => filled($feed['last_error'] ?? null))->count();
                $dueCount = collect($feeds)->filter(function (array $feed): bool {
                    if (! ($feed['is_active'] ?? false)) {
                        return false;
                    }

                    if (! filled($feed['next_parse_at'] ?? null)) {
                        return true;
                    }

                    return \Illuminate\Support\Carbon::parse($feed['next_parse_at'])->lte(now());
                })->count();
            @endphp

            <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                        <div class="space-y-2">
                            <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $categoryColor }}"></span>
                                <span class="whitespace-nowrap">{{ $categoryName }}</span>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">{{ $categoryName }}</h3>
                                <p class="text-sm text-slate-500">
                                    Рабочее поле по рубрике: локальные статусы, быстрый запуск и доступ к редактированию каждой ленты.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 text-xs font-semibold">
                            <span class="inline-flex items-center rounded-full bg-sky-100 px-3 py-1 text-sky-700">
                                {{ count($feeds) }} всего
                            </span>
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-emerald-700">
                                {{ $activeCount }} активных
                            </span>
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-amber-800">
                                {{ $dueCount }} ждут запуска
                            </span>
                            <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-rose-700">
                                {{ $failingCount }} с ошибкой
                            </span>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="grid gap-4 xl:grid-cols-2 2xl:grid-cols-3" data-rss-feed-grid>
                        @foreach ($feeds as $feed)
                            @php
                                $lastParsedAt = filled($feed['last_parsed_at'] ?? null) ? \Illuminate\Support\Carbon::parse($feed['last_parsed_at']) : null;
                                $nextParseAt = filled($feed['next_parse_at'] ?? null) ? \Illuminate\Support\Carbon::parse($feed['next_parse_at']) : null;
                                $sourceName = filled($feed['source_name'] ?? null) ? $feed['source_name'] : (parse_url((string) $feed['url'], PHP_URL_HOST) ?: 'Источник не определён');
                                $isDue = ($feed['is_active'] ?? false) && ($nextParseAt === null || $nextParseAt->lte(now()));
                                $status = ! ($feed['is_active'] ?? false)
                                    ? ['label' => 'Отключена', 'classes' => 'bg-slate-100 text-slate-700', 'border' => 'border-slate-200']
                                    : (filled($feed['last_error'] ?? null)
                                        ? ['label' => 'Требует внимания', 'classes' => 'bg-rose-100 text-rose-700', 'border' => 'border-rose-200']
                                        : ($isDue
                                            ? ['label' => 'Готова к запуску', 'classes' => 'bg-amber-100 text-amber-800', 'border' => 'border-amber-200']
                                            : ['label' => 'Стабильна', 'classes' => 'bg-emerald-100 text-emerald-700', 'border' => 'border-emerald-200']));
                            @endphp

                            <article
                                wire:key="feed-{{ $feed['id'] }}"
                                @class([
                                    'relative overflow-hidden rounded-[1.75rem] border bg-slate-50 p-5 shadow-sm transition',
                                    $status['border'],
                                ])
                                data-rss-feed-card
                            >
                                <div class="absolute inset-y-0 left-0 w-1.5" style="background-color: {{ $categoryColor }}"></div>

                                <div class="flex h-full flex-col gap-5 pl-3">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="space-y-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $status['classes'] }}">
                                                    {{ $status['label'] }}
                                                </span>
                                                <span class="inline-flex rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                                    {{ $sourceName }}
                                                </span>
                                            </div>

                                            <div>
                                                <h4 class="text-base font-semibold text-slate-950">{{ $feed['title'] }}</h4>
                                                <p class="mt-1 break-all text-sm text-slate-500">{{ $feed['url'] }}</p>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-white px-3 py-2 text-right ring-1 ring-slate-200">
                                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Новых</div>
                                            <div class="mt-1 text-lg font-semibold text-slate-950">
                                                +{{ number_format((int) ($feed['last_run_new_count'] ?? 0)) }}
                                            </div>
                                        </div>
                                    </div>

                                    <dl class="grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-slate-200">
                                            <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Последний запуск</dt>
                                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ $lastParsedAt?->diffForHumans() ?? 'Никогда' }}</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-slate-200">
                                            <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Следующий запуск</dt>
                                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ $nextParseAt?->diffForHumans() ?? 'Нужно назначить' }}</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-slate-200">
                                            <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Импортировано</dt>
                                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ number_format((int) ($feed['articles_parsed_total'] ?? 0)) }}</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-slate-200">
                                            <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Сбоев подряд</dt>
                                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ number_format((int) ($feed['consecutive_failures'] ?? 0)) }}</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-slate-200">
                                            <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Интервал</dt>
                                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ number_format((int) ($feed['fetch_interval'] ?? 0)) }} мин</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-slate-200">
                                            <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Пропущено</dt>
                                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ number_format((int) ($feed['last_run_skip_count'] ?? 0)) }}</dd>
                                        </div>
                                    </dl>

                                    @if (filled($feed['last_error'] ?? null))
                                        <div class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200">
                                            {{ \Illuminate\Support\Str::limit((string) $feed['last_error'], 220) }}
                                        </div>
                                    @endif

                                    <div class="mt-auto flex flex-wrap items-center gap-3">
                                        <button
                                            type="button"
                                            wire:click="parseFeed({{ $feed['id'] }})"
                                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary-600 px-3.5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                            @disabled($isParsing)
                                        >
                                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowPath" class="h-4 w-4" />
                                            @if ($isParsing && $parsingFeedId === $feed['id'])
                                                Идёт парсинг...
                                            @else
                                                Запустить
                                            @endif
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="toggleFeed({{ $feed['id'] }})"
                                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950 disabled:cursor-not-allowed disabled:opacity-60"
                                            @disabled($isParsing)
                                        >
                                            <x-filament::icon :icon="($feed['is_active'] ?? false) ? \Filament\Support\Icons\Heroicon::OutlinedPauseCircle : \Filament\Support\Icons\Heroicon::OutlinedPlayCircle" class="h-4 w-4" />
                                            {{ ($feed['is_active'] ?? false) ? 'Отключить' : 'Включить' }}
                                        </button>

                                        <a
                                            href="{{ \App\Filament\Resources\RssFeeds\RssFeedResource::getUrl('edit', ['record' => $feed['id']]) }}"
                                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-transparent px-3.5 py-2.5 text-sm font-semibold text-primary-600 transition hover:bg-primary-50 hover:text-primary-700"
                                        >
                                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedPencilSquare" class="h-4 w-4" />
                                            Редактировать
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @empty
            <div class="rounded-[2rem] border border-slate-200 bg-white p-8 text-center shadow-sm">
                <div class="mx-auto max-w-xl space-y-3">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedRss" class="h-7 w-7" />
                    </div>
                    <h3 class="text-lg font-semibold text-slate-950">Ленты не найдены</h3>
                    <p class="text-sm leading-6 text-slate-500">
                        Для текущих условий нет RSS-источников. Сбросьте фильтры или добавьте новую ленту в каталог.
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                        <button
                            type="button"
                            wire:click="resetFilters"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                        >
                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedXMark" class="h-4 w-4" />
                            Сбросить фильтры
                        </button>

                        <a
                            href="{{ \App\Filament\Resources\RssFeeds\RssFeedResource::getUrl('create') }}"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500"
                        >
                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedPlus" class="h-4 w-4" />
                            Добавить ленту
                        </a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
