<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Manage RSS Feeds</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Run all feeds or trigger a single feed directly from Filament.</p>
                </div>

                <button
                    type="button"
                    wire:click="parseAll"
                    wire:loading.attr="disabled"
                    wire:target="parseAll"
                    class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="parseAll">Parse All</span>
                    <span wire:loading wire:target="parseAll">Parsing...</span>
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Title</th>
                        <th class="px-4 py-3 font-medium">Category</th>
                        <th class="px-4 py-3 font-medium">Last Parsed</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">New Count</th>
                        <th class="px-4 py-3 font-medium">Error</th>
                        <th class="px-4 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($this->feeds as $feed)
                        <tr wire:key="feed-{{ $feed['id'] }}">
                            <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $feed['title'] }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $feed['category_name'] }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $feed['last_parsed_at'] }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $feed['status'] === 'Active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' }}">
                                    {{ $feed['status'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $feed['new_count'] }}</td>
                            <td class="px-4 py-3 text-sm text-rose-600 dark:text-rose-300">{{ $feed['error'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    wire:click="parseSingleFeed({{ $feed['id'] }})"
                                    class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:text-gray-950 dark:border-white/10 dark:text-gray-200 dark:hover:border-white/20 dark:hover:text-white"
                                    @disabled($isParsing)
                                >
                                    @if ($isParsing && $selectedFeedId === $feed['id'])
                                        Parsing...
                                    @else
                                        Parse
                                    @endif
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($this->parseResults !== [])
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Latest Results</h3>
                <div class="mt-4 space-y-3">
                    @foreach ($this->parseResults as $result)
                        <div class="rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-white/10">
                            <div class="font-medium text-gray-950 dark:text-white">{{ $result['feed'] }}</div>
                            <div class="mt-1 text-gray-600 dark:text-gray-300">
                                New: {{ $result['new'] }}, Skipped: {{ $result['skip'] }}, Errors: {{ $result['errors'] }}
                            </div>
                            @if (! empty($result['error']))
                                <div class="mt-2 text-rose-600 dark:text-rose-300">{{ $result['error'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
