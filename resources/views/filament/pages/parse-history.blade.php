<x-filament-panels::page>
    @php
        $summary = $this->summary;
        $logs = $this->logs;
    @endphp

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Runs Today</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['runs_today']) }}</div>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Running Now</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['runs_in_progress']) }}</div>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Avg Duration</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['average_duration_ms']) }} ms</div>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">New Today</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['total_new_today']) }}</div>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">Error Rate</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $summary['error_rate'] }}%</div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Feed</span>
                    <select
                        wire:model.live="feed"
                        class="block w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                    >
                        <option value="">All feeds</option>
                        @foreach ($this->feedOptions as $id => $title)
                            <option value="{{ $id }}">{{ $title }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</span>
                    <select
                        wire:model.live="status"
                        class="block w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                    >
                        <option value="">All statuses</option>
                        <option value="success">Success</option>
                        <option value="failure">Failure</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Date from</span>
                    <input
                        type="date"
                        wire:model.live="dateFrom"
                        class="block w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                    />
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Date to</span>
                    <input
                        type="date"
                        wire:model.live="dateTo"
                        class="block w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-100"
                    />
                </label>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Feed</th>
                        <th class="px-4 py-3 font-medium">Started</th>
                        <th class="px-4 py-3 font-medium">Duration</th>
                        <th class="px-4 py-3 font-medium">New</th>
                        <th class="px-4 py-3 font-medium">Skip</th>
                        <th class="px-4 py-3 font-medium">Errors</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($logs as $log)
                        <tr
                            wire:key="log-{{ $log->id }}"
                            wire:click="toggleExpanded({{ $log->id }})"
                            class="cursor-pointer transition hover:bg-gray-50 dark:hover:bg-white/5"
                        >
                            <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $log->rssFeed?->title ?? 'Unknown feed' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $log->started_at?->format('d.m.Y H:i:s') }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ number_format((int) $log->duration_ms) }} ms</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $log->new_count }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $log->skip_count }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $log->error_count }}</td>
                            <td class="px-4 py-3">
                                @if ($log->success)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                        Success
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
                                        Failure
                                    </span>
                                @endif
                            </td>
                        </tr>

                        @if (isset($this->expandedLogs[$log->id]))
                            <tr wire:key="log-expanded-{{ $log->id }}">
                                <td colspan="7" class="bg-gray-50 px-4 py-4 dark:bg-white/5">
                                    <div class="space-y-3">
                                        @if (filled($log->error_message))
                                            <div>
                                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Error message</div>
                                                <div class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $log->error_message }}</div>
                                            </div>
                                        @endif

                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Item errors</div>
                                            <pre class="mt-2 overflow-x-auto rounded-xl bg-gray-950 px-4 py-3 text-xs text-gray-100">{{ json_encode($log->item_errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                No parse logs found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="border-t border-gray-200 px-4 py-4 dark:border-white/10">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
