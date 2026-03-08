<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Services\RssParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RssParseController extends Controller
{
    public function index(): View
    {
        $todayStart = today()->startOfDay();
        $todayEnd = $todayStart->endOfDay();

        $feeds = RssFeed::query()
            ->with(['category'])
            ->withCount('articles')
            ->get()
            ->sortBy(fn (RssFeed $feed): string => ($feed->category?->name ?? '').' '.$feed->title)
            ->values();

        $stats = [
            'total_articles' => Article::query()->count(),
            'today_articles' => Article::query()->publishedBetween($todayStart, $todayEnd)->count(),
            'last_system_parse' => RssFeed::query()->parsed()->max('last_parsed_at'),
        ];

        return view('rss.index', compact('feeds', 'stats'));
    }

    public function parseAll(Request $request, RssParserService $parser): RedirectResponse
    {
        $request->validate([
            '_token' => ['nullable'],
        ]);

        $results = $parser->parseAllFeeds();

        $summary = collect($results)->reduce(function (array $carry, array $result): array {
            $carry['new'] += (int) ($result['new'] ?? 0);
            $carry['skipped'] += (int) ($result['skip'] ?? 0);
            $carry['errors'] += (int) ($result['errors'] ?? 0);

            return $carry;
        }, ['new' => 0, 'skipped' => 0, 'errors' => 0]);

        return redirect()
            ->route('rss.index')
            ->with('status', "Парсинг завершён. Новые: {$summary['new']}, Пропущено: {$summary['skipped']}, Ошибки: {$summary['errors']}");
    }

    public function parseFeed(Request $request, int $feedId, RssParserService $parser): JsonResponse
    {
        $request->validate([
            '_token' => ['nullable'],
        ]);

        $feed = RssFeed::query()->findOrFail($feedId);
        $result = $parser->parseFeed($feed);
        $feed->refresh();

        $success = empty($result['error']);

        return response()->json([
            'success' => $success,
            'new' => (int) ($result['new'] ?? 0),
            'skipped' => (int) ($result['skip'] ?? 0),
            'errors' => (int) ($result['errors'] ?? 0),
            'message' => $success
                ? "Новые: {$result['new']}, Пропущено: {$result['skip']}"
                : (string) $result['error'],
            'last_parsed_at' => $feed->last_parsed_at?->toIso8601String(),
            'last_parsed_at_human' => $feed->last_parsed_at?->format('d.m.Y H:i') ?? 'Никогда',
            'articles_parsed_total' => $feed->articles_parsed_total,
            'last_run_new_count' => $feed->last_run_new_count,
            'last_error' => $feed->last_error,
        ]);
    }

    public function parseCategory(Request $request, string $slug, RssParserService $parser): JsonResponse
    {
        $request->validate([
            '_token' => ['nullable'],
        ]);

        $category = Category::query()->where('slug', $slug)->firstOrFail();
        $feeds = RssFeed::query()->active()->inCategory($category)->get();

        if ($feeds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Для этой категории не найдено активных лент.',
                'new' => 0,
                'skipped' => 0,
                'errors' => 0,
            ], 424);
        }

        $results = collect($parser->parseFeeds($feeds, 'scheduler'));

        $summary = $results->reduce(function (array $carry, array $result): array {
            $carry['new'] += (int) ($result['new'] ?? 0);
            $carry['skipped'] += (int) ($result['skip'] ?? 0);
            $carry['errors'] += (int) ($result['errors'] ?? 0);

            return $carry;
        }, ['new' => 0, 'skipped' => 0, 'errors' => 0]);

        $success = $results->every(fn (array $result): bool => empty($result['error']));

        return response()->json([
            'success' => $success,
            'message' => $success
                ? "Категория обработана. Новые: {$summary['new']}, Пропущено: {$summary['skipped']}"
                : 'Категория обработана с ошибками.',
            'new' => $summary['new'],
            'skipped' => $summary['skipped'],
            'errors' => $summary['errors'],
            'results' => $results->values()->all(),
        ]);
    }
}
