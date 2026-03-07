<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>RSS Feed Manager</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900">
        @php
            $feedGroups = $feeds->groupBy(fn ($feed) => $feed->category?->slug ?? 'uncategorized');
            $lastParsedAt = $stats['last_system_parse'] ? \Illuminate\Support\Carbon::parse($stats['last_system_parse'])->format('d.m.Y H:i') : 'Never';
        @endphp

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-8 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-2">
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">Operations</p>
                        <h1 class="text-3xl font-semibold tracking-tight text-slate-950">RSS Feed Manager</h1>
                        <p class="text-sm text-slate-600">
                            Last system parse: <span class="font-medium text-slate-900">{{ $lastParsedAt }}</span>
                            <span class="mx-2 text-slate-300">|</span>
                            Total articles: <span class="font-medium text-slate-900">{{ $stats['total_articles'] }}</span>
                            <span class="mx-2 text-slate-300">|</span>
                            Today: <span class="font-medium text-slate-900">{{ $stats['today_articles'] }}</span>
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <form action="{{ route('rss.parse-all') }}" method="POST" id="parse-all-form">
                            @csrf
                            <button
                                type="submit"
                                id="parse-all-button"
                                class="inline-flex items-center gap-2 rounded-full bg-sky-600 px-5 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 disabled:cursor-not-allowed disabled:bg-sky-300"
                            >
                                <span class="inline-flex h-2.5 w-2.5 rounded-full bg-white/80"></span>
                                Parse All Feeds
                            </button>
                        </form>

                        <button
                            type="button"
                            id="refresh-status-button"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                        >
                            Refresh Status
                        </button>
                    </div>
                </div>
            </div>

            <div id="flash-container" class="mb-6 space-y-3">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif
            </div>

            <div class="space-y-8">
                @foreach ($feedGroups as $group)
                    @php
                        $category = $group->first()?->category;
                        $categoryArticles = $group->sum('articles_count');
                    @endphp

                    <section class="rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
                        <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex flex-wrap items-center gap-3">
                                <span
                                    class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium"
                                    style="background-color: {{ $category?->color ?? '#94A3B8' }}1f; color: {{ $category?->color ?? '#475569' }};"
                                >
                                    <span>{{ $category?->icon ?? '🛰️' }}</span>
                                    <span>{{ $category?->name ?? 'Uncategorized' }}</span>
                                </span>
                                <span class="text-sm text-slate-500">{{ $group->count() }} feeds</span>
                                <span class="text-sm text-slate-500">{{ $categoryArticles }} total articles</span>
                            </div>

                            @if ($category?->slug)
                                <button
                                    type="button"
                                    onclick="parseCategory('{{ $category->slug }}', this)"
                                    class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                                >
                                    Parse Category
                                </button>
                            @endif
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="px-6 py-3 font-medium">Feed Name</th>
                                        <th class="px-6 py-3 font-medium">URL</th>
                                        <th class="px-6 py-3 font-medium">Active</th>
                                        <th class="px-6 py-3 font-medium">Last Parsed</th>
                                        <th class="px-6 py-3 font-medium">New (last)</th>
                                        <th class="px-6 py-3 font-medium">Total</th>
                                        <th class="px-6 py-3 font-medium">Error</th>
                                        <th class="px-6 py-3 font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($group as $feed)
                                        <tr id="feed-row-{{ $feed->id }}" class="odd:bg-white even:bg-slate-50/70">
                                            <td class="px-6 py-4 font-medium text-slate-900">{{ $feed->title }}</td>
                                            <td class="px-6 py-4">
                                                <a href="{{ $feed->url }}" target="_blank" rel="noreferrer" class="break-all text-sky-600 hover:text-sky-500">
                                                    {{ $feed->url }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $feed->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                                    {{ $feed->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-slate-600" data-column="last-parsed">
                                                {{ $feed->last_parsed_at?->format('d.m.Y H:i') ?? 'Never' }}
                                            </td>
                                            <td class="px-6 py-4 text-slate-600" data-column="new-last">{{ $feed->last_run_new_count }}</td>
                                            <td class="px-6 py-4 text-slate-600" data-column="total">{{ $feed->articles_parsed_total }}</td>
                                            <td class="px-6 py-4 text-sm text-rose-600" data-column="error">{{ $feed->last_error ?: '—' }}</td>
                                            <td class="px-6 py-4">
                                                <button
                                                    type="button"
                                                    onclick="parseFeed({{ $feed->id }}, this)"
                                                    class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-400"
                                                >
                                                    Parse Now
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
            </div>
        </div>

        <script>
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const showFlash = (message, type = 'success') => {
                const flashContainer = document.getElementById('flash-container');
                const banner = document.createElement('div');
                banner.className = `rounded-2xl border px-4 py-3 text-sm ${
                    type === 'success'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                        : 'border-rose-200 bg-rose-50 text-rose-800'
                }`;
                banner.textContent = message;
                flashContainer.prepend(banner);

                setTimeout(() => {
                    banner.remove();
                }, 5000);
            };

            const setButtonLoading = (buttonEl, isLoading, loadingText) => {
                if (! buttonEl) {
                    return;
                }

                if (isLoading) {
                    buttonEl.dataset.originalText = buttonEl.textContent.trim();
                    buttonEl.textContent = loadingText;
                    buttonEl.disabled = true;
                } else {
                    buttonEl.textContent = buttonEl.dataset.originalText || 'Parse Now';
                    buttonEl.disabled = false;
                }
            };

            async function parseFeed(feedId, buttonEl) {
                setButtonLoading(buttonEl, true, 'Parsing...');

                try {
                    const response = await fetch(`/admin/rss/parse/${feedId}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({}),
                    });

                    const data = await response.json();

                    if (! response.ok || ! data.success) {
                        throw new Error(data.message || 'Feed parsing failed.');
                    }

                    const row = document.getElementById(`feed-row-${feedId}`);

                    row.querySelector('[data-column="last-parsed"]').textContent = data.last_parsed_at_human || 'Just now';
                    row.querySelector('[data-column="new-last"]').textContent = data.last_run_new_count;
                    row.querySelector('[data-column="total"]').textContent = data.articles_parsed_total;
                    row.querySelector('[data-column="error"]').textContent = data.last_error || '—';

                    showFlash(`Feed parsed. New: ${data.new}, Skipped: ${data.skipped}`);
                } catch (error) {
                    showFlash(error.message || 'Feed parsing failed.', 'error');
                } finally {
                    setButtonLoading(buttonEl, false, 'Parse Now');
                }
            }

            async function parseCategory(slug, buttonEl) {
                setButtonLoading(buttonEl, true, 'Parsing...');

                try {
                    const response = await fetch(`/admin/rss/parse-category/${slug}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({}),
                    });

                    const data = await response.json();

                    if (! response.ok || ! data.success) {
                        throw new Error(data.message || 'Category parsing failed.');
                    }

                    showFlash(`Category parsed. New: ${data.new}, Skipped: ${data.skipped}`);
                    window.setTimeout(() => window.location.reload(), 600);
                } catch (error) {
                    showFlash(error.message || 'Category parsing failed.', 'error');
                } finally {
                    setButtonLoading(buttonEl, false, 'Parse Category');
                }
            }

            document.getElementById('refresh-status-button').addEventListener('click', () => {
                window.location.reload();
            });

            document.getElementById('parse-all-form').addEventListener('submit', (event) => {
                const button = document.getElementById('parse-all-button');
                button.disabled = true;
                button.textContent = 'Parsing all feeds...';
            });
        </script>
    </body>
</html>
