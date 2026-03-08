<div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Последние логи парсинга</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Последние 5 запусков RSS-парсинга.</p>
        </div>
    </div>

    @if ($logs->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">Логов парсинга пока нет.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Лента</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Новые</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Длительность</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Статус</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Время</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($logs as $log)
                        <tr>
                            <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $log->rssFeed?->title ?? 'Неизвестная лента' }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $log->new_count }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $log->duration_ms ?? 0 }} ms</td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                    'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' => $log->success,
                                    'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300' => ! $log->success,
                                ])>
                                    {{ $log->success ? 'Успешно' : 'Ошибка' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $log->started_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
