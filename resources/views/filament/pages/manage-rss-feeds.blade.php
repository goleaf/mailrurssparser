<x-filament-panels::page>
    @php
        $groupedFeeds = $this->groupedFeeds;
        $summary = $this->summary;
        $resultSummary = $this->resultSummary;
    @endphp

    <div class="space-y-6" data-rss-manager-page>
        <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="grid gap-0 xl:grid-cols-2">
                <div class="border-b border-gray-200 bg-gradient-to-br from-sky-100 via-white to-emerald-50 p-6 dark:border-white/10 dark:from-sky-500/10 dark:via-gray-900 dark:to-emerald-500/10 sm:p-8 xl:border-b-0 xl:border-r">
                    <div class="space-y-5">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
                                RSS Control
                            </span>
                            <span class="inline-flex items-center rounded-full bg-sky-100 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                                {{ number_format($summary['total_feeds']) }} лент
                            </span>
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                {{ number_format($summary['categories']) }} рубрик
                            </span>
                        </div>

                        <div class="space-y-3">
                            <h2 class="text-2xl font-bold text-gray-950 dark:text-white sm:text-3xl">
                                Операционный центр RSS
                            </h2>
                            <p class="max-w-2xl text-sm leading-7 text-gray-600 dark:text-gray-300">
                                Управляйте лентами как единым контуром: быстро отслеживайте проблемные источники, запускайте парсинг по месту и переключайтесь между каталогом лент и историей запусков без лишних переходов.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                wire:click="parseAll"
                                wire:loading.attr="disabled"
                                wire:target="parseAll"
                                class="inline-flex items-center justify-center rounded-2xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span wire:loading.remove wire:target="parseAll">Обновить весь контур</span>
                                <span wire:loading wire:target="parseAll">Парсинг всех лент...</span>
                            </button>

                            <a
                                href="{{ \App\Filament\Resources\RssFeeds\RssFeedResource::getUrl('index') }}"
                                class="inline-flex items-center justify-center rounded-2xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-gray-400 hover:text-gray-950 dark:border-white/10 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-white/20 dark:hover:text-white"
                            >
                                Каталог лент
                            </a>

                            <a
                                href="{{ \App\Filament\Pages\ParseHistory::getUrl() }}"
                                class="inline-flex items-center justify-center rounded-2xl border border-transparent px-4 py-2.5 text-sm font-semibold text-primary-600 transition hover:bg-primary-50 hover:text-primary-700 dark:text-primary-300 dark:hover:bg-primary-500/10"
                            >
                                История запусков
                            </a>
                        </div>
                    </div>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="space-y-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Фильтр и состояние
                                </h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    Показываем {{ number_format($summary['filtered_feeds']) }} из {{ number_format($summary['total_feeds']) }} лент.
                                </p>
                            </div>

                            @if ($isParsing)
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                    Парсинг выполняется
                                </span>
                            @endif
                        </div>

                        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                            <label class="block" data-rss-filter-select>
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Рубрика</span>
                                <select
                                    wire:model.live="filterCategory"
                                    class="block w-full rounded-2xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                                >
                                    <option value="">Все категории</option>
                                    @foreach ($this->categoryOptions as $slug => $name)
                                        <option value="{{ $slug }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <button
                                type="button"
                                wire:click="resetFilterCategory"
                                class="inline-flex items-center justify-center rounded-2xl border border-gray-300 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-gray-400 hover:text-gray-950 dark:border-white/10 dark:text-gray-200 dark:hover:border-white/20 dark:hover:text-white"
                            >
                                Сбросить фильтр
                            </button>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Активные источники</div>
                                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['active_feeds']) }}</div>
                            </div>

                            <div class="rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Требуют запуска</div>
                                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['due_feeds']) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" data-rss-manager-summary>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Лент в текущем срезе</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['filtered_feeds']) }}</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">После фильтра по рубрике и состоянию страницы.</div>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Нуждаются во внимании</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['failing_feeds']) }}</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Ленты с последней ошибкой или нестабильным циклом.</div>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Новых в последнем цикле</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['new_last_run_total']) }}</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Сумма новых материалов по видимым лентам.</div>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Импортировано материалов</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['articles_parsed_total']) }}</div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Исторический объём публикаций через RSS-контур.</div>
            </div>
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

            <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-white/10">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                        <div class="space-y-2">
                            <div class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $categoryColor }}"></span>
                                <span class="whitespace-nowrap">{{ $categoryName }}</span>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $categoryName }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Ленты по рубрикам с отдельным управлением и быстрым обзором состояния.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 text-xs font-semibold">
                            <span class="inline-flex items-center rounded-full bg-sky-100 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                                {{ count($feeds) }} всего
                            </span>
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                {{ $activeCount }} активных
                            </span>
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                {{ $dueCount }} ждут запуска
                            </span>
                            <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
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
                                    ? ['label' => 'Отключена', 'classes' => 'bg-gray-200 text-gray-700 dark:bg-white/10 dark:text-gray-300', 'border' => 'border-gray-200 dark:border-white/10']
                                    : (filled($feed['last_error'] ?? null)
                                        ? ['label' => 'Требует внимания', 'classes' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300', 'border' => 'border-rose-200 dark:border-rose-500/20']
                                        : ($isDue
                                            ? ['label' => 'Готова к запуску', 'classes' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300', 'border' => 'border-amber-200 dark:border-amber-500/20']
                                            : ['label' => 'Стабильна', 'classes' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'border' => 'border-emerald-200 dark:border-emerald-500/20']));
                            @endphp

                            <article
                                wire:key="feed-{{ $feed['id'] }}"
                                @class([
                                    'relative overflow-hidden rounded-3xl border bg-gray-50/80 p-5 shadow-sm transition dark:bg-white/5',
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
                                                <span class="inline-flex rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-gray-600 ring-1 ring-gray-200 dark:bg-gray-950 dark:text-gray-300 dark:ring-white/10">
                                                    {{ $sourceName }}
                                                </span>
                                            </div>

                                            <div>
                                                <h4 class="text-base font-semibold text-gray-950 dark:text-white">{{ $feed['title'] }}</h4>
                                                <p class="mt-1 break-all text-sm text-gray-500 dark:text-gray-400">{{ $feed['url'] }}</p>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-white px-3 py-2 text-right shadow-sm ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Новых</div>
                                            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                                                +{{ number_format((int) ($feed['last_run_new_count'] ?? 0)) }}
                                            </div>
                                        </div>
                                    </div>

                                    <dl class="grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Последний запуск</dt>
                                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $lastParsedAt?->diffForHumans() ?? 'Никогда' }}</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Следующий запуск</dt>
                                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $nextParseAt?->diffForHumans() ?? 'Нужно назначить' }}</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Импортировано</dt>
                                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) ($feed['articles_parsed_total'] ?? 0)) }}</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Сбоев подряд</dt>
                                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) ($feed['consecutive_failures'] ?? 0)) }}</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Интервал</dt>
                                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) ($feed['fetch_interval'] ?? 0)) }} мин</dd>
                                        </div>

                                        <div class="rounded-2xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-white/10">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Пропущено</dt>
                                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ number_format((int) ($feed['last_run_skip_count'] ?? 0)) }}</dd>
                                        </div>
                                    </dl>

                                    @if (filled($feed['last_error'] ?? null))
                                        <div class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20">
                                            {{ \Illuminate\Support\Str::limit((string) $feed['last_error'], 220) }}
                                        </div>
                                    @endif

                                    <div class="mt-auto flex flex-wrap items-center gap-3">
                                        <button
                                            type="button"
                                            wire:click="parseFeed({{ $feed['id'] }})"
                                            class="inline-flex items-center justify-center rounded-2xl bg-primary-600 px-3.5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                            @disabled($isParsing)
                                        >
                                            @if ($isParsing && $parsingFeedId === $feed['id'])
                                                Идёт парсинг...
                                            @else
                                                Запустить
                                            @endif
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="toggleFeed({{ $feed['id'] }})"
                                            class="inline-flex items-center justify-center rounded-2xl border border-gray-300 px-3.5 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-gray-400 hover:text-gray-950 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:text-gray-200 dark:hover:border-white/20 dark:hover:text-white"
                                            @disabled($isParsing)
                                        >
                                            {{ ($feed['is_active'] ?? false) ? 'Отключить' : 'Включить' }}
                                        </button>

                                        <a
                                            href="{{ \App\Filament\Resources\RssFeeds\RssFeedResource::getUrl('edit', ['record' => $feed['id']]) }}"
                                            class="inline-flex items-center justify-center rounded-2xl border border-transparent px-3.5 py-2.5 text-sm font-semibold text-primary-600 transition hover:bg-primary-50 hover:text-primary-700 dark:text-primary-300 dark:hover:bg-primary-500/10"
                                        >
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
            <div class="rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="mx-auto max-w-xl space-y-3">
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Ленты не найдены</h3>
                    <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Для выбранного фильтра нет RSS-источников. Сбросьте фильтр или добавьте новую ленту в каталог.
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                        <button
                            type="button"
                            wire:click="resetFilterCategory"
                            class="inline-flex items-center justify-center rounded-2xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-gray-400 hover:text-gray-950 dark:border-white/10 dark:text-gray-200 dark:hover:border-white/20 dark:hover:text-white"
                        >
                            Сбросить фильтр
                        </button>

                        <a
                            href="{{ \App\Filament\Resources\RssFeeds\RssFeedResource::getUrl('create') }}"
                            class="inline-flex items-center justify-center rounded-2xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500"
                        >
                            Добавить ленту
                        </a>
                    </div>
                </div>
            </div>
        @endforelse

        @if ($this->results !== [])
            <section class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" data-rss-manager-results>
                <div class="border-b border-gray-200 px-6 py-5 dark:border-white/10">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Последние результаты запуска</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Сводка по последнему ручному запуску прямо на странице управления лентами.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2 text-xs font-semibold">
                            <span class="inline-flex items-center rounded-full bg-sky-100 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                                {{ $resultSummary['runs'] }} запусков
                            </span>
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                {{ $resultSummary['successful_runs'] }} успешно
                            </span>
                            <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
                                {{ $resultSummary['failed_runs'] }} с ошибкой
                            </span>
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-gray-700 dark:bg-white/5 dark:text-gray-300">
                                +{{ $resultSummary['new'] }} новых
                            </span>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-medium">Лента</th>
                                <th class="px-4 py-3 font-medium">Новые</th>
                                <th class="px-4 py-3 font-medium">Пропущено</th>
                                <th class="px-4 py-3 font-medium">Ошибки</th>
                                <th class="px-4 py-3 font-medium">Статус</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($this->results as $result)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $result['feed'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ number_format((int) ($result['new'] ?? 0)) }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ number_format((int) ($result['skip'] ?? 0)) }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ number_format((int) ($result['errors'] ?? 0)) }}</td>
                                    <td class="px-4 py-3">
                                        @if (filled($result['error'] ?? null))
                                            <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
                                                {{ \Illuminate\Support\Str::limit((string) $result['error'], 80) }}
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                                Успешно
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
