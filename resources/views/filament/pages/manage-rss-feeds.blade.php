<x-filament-panels::page>
    @php
        $groupedFeeds = $this->groupedFeeds;
    @endphp

    <div class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">RSS Менеджер</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Фильтруйте по категориям, запускайте все ленты сразу и управляйте отдельными RSS-источниками.</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label class="min-w-52">
                        <span class="sr-only">Категория</span>
                        <select
                            wire:model.live="filterCategory"
                            class="block w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                        >
                            <option value="">Все категории</option>
                            @foreach ($this->categoryOptions as $slug => $name)
                                <option value="{{ $slug }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button
                        type="button"
                        wire:click="parseAll"
                        wire:loading.attr="disabled"
                        wire:target="parseAll"
                        class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="parseAll">Запустить всё</span>
                        <span wire:loading wire:target="parseAll">Идёт парсинг...</span>
                    </button>
                </div>
            </div>
        </div>

        @forelse ($groupedFeeds as $categoryName => $feeds)
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $categoryName }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ count($feeds) }} лент</p>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($feeds as $feed)
                        @php
                            $categoryLabel = data_get($feed, 'category.name', 'Без категории');
                            $categoryColor = data_get($feed, 'category.color', '#6B7280');
                            $lastParsedAt = filled($feed['last_parsed_at'] ?? null) ? \Illuminate\Support\Carbon::parse($feed['last_parsed_at']) : null;
                            $nextParseAt = filled($feed['next_parse_at'] ?? null) ? \Illuminate\Support\Carbon::parse($feed['next_parse_at']) : null;
                            $status = ! ($feed['is_active'] ?? false)
                                ? ['label' => 'Отключена', 'classes' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300']
                                : (filled($feed['last_error'] ?? null)
                                    ? ['label' => 'Ошибка', 'classes' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300']
                                    : ['label' => 'Работает', 'classes' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300']);
                        @endphp

                        <div wire:key="feed-{{ $feed['id'] }}" class="rounded-2xl border border-gray-200 p-5 dark:border-white/10">
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold text-white" style="background-color: {{ $categoryColor }}">
                                            {{ $categoryLabel }}
                                        </span>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $status['classes'] }}">
                                            {{ $status['label'] }}
                                        </span>
                                    </div>

                                    <h4 class="text-base font-semibold text-gray-950 dark:text-white">{{ $feed['title'] }}</h4>
                                    <p class="break-all text-sm text-gray-500 dark:text-gray-400">{{ $feed['url'] }}</p>
                                </div>

                                <span class="inline-flex rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                                    +{{ $feed['last_run_new_count'] ?? 0 }}
                                </span>
                            </div>

                            <dl class="mt-4 grid gap-3 text-sm text-gray-600 dark:text-gray-300 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Последний запуск</dt>
                                    <dd>{{ $lastParsedAt?->diffForHumans() ?? 'Никогда' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Следующий запуск</dt>
                                    <dd>{{ $nextParseAt?->diffForHumans() ?? 'Не запланирован' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Всего статей</dt>
                                    <dd>{{ number_format((int) ($feed['articles_parsed_total'] ?? 0)) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Сбоев подряд</dt>
                                    <dd>{{ $feed['consecutive_failures'] ?? 0 }}</dd>
                                </div>
                            </dl>

                            @if (filled($feed['last_error'] ?? null))
                                <p class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                                    {{ $feed['last_error'] }}
                                </p>
                            @endif

                            <div class="mt-5 flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    wire:click="parseFeed({{ $feed['id'] }})"
                                    class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
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
                                    class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:text-gray-950 dark:border-white/10 dark:text-gray-200 dark:hover:border-white/20 dark:hover:text-white"
                                    @disabled($isParsing)
                                >
                                    {{ ($feed['is_active'] ?? false) ? 'Отключить' : 'Включить' }}
                                </button>

                                <a
                                    href="{{ \App\Filament\Resources\RssFeeds\RssFeedResource::getUrl('edit', ['record' => $feed['id']]) }}"
                                    class="inline-flex items-center justify-center rounded-xl border border-transparent px-3 py-2 text-sm font-medium text-primary-600 transition hover:bg-primary-50 hover:text-primary-700 dark:text-primary-300 dark:hover:bg-primary-500/10"
                                >
                                    Редактировать
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-2xl bg-white p-6 text-sm text-gray-500 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                Нет RSS-лент для выбранного фильтра.
            </div>
        @endforelse

        @if ($this->results !== [])
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Последние результаты</h3>

                <div class="mt-4 overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10">
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
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $result['new'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $result['skip'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $result['errors'] }}</td>
                                    <td class="px-4 py-3">
                                        @if (filled($result['error'] ?? null))
                                            <span class="text-rose-600 dark:text-rose-300">{{ $result['error'] }}</span>
                                        @else
                                            <span class="text-emerald-600 dark:text-emerald-300">Успешно</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
