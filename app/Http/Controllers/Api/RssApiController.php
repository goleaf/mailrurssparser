<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\RssFeed;
use App\Services\RssParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RssApiController extends Controller
{
    public function status(): JsonResponse
    {
        $feeds = RssFeed::query()
            ->with('category')
            ->get()
            ->withoutAppends()
            ->setAppends(['category_name', 'status_label'])
            ->setVisible([
                'id',
                'title',
                'is_active',
                'last_parsed_at',
                'next_parse_at',
                'last_run_new_count',
                'consecutive_failures',
                'last_error',
            ])
            ->mergeVisible(['category_name', 'status_label']);

        return response()->json(['data' => $feeds]);
    }

    public function parseFeed(Request $request, int $feedId): JsonResponse
    {
        unset($request);

        $feed = RssFeed::query()->findOrFail($feedId);
        $result = app(RssParserService::class)->parseFeed($feed, 'api');

        return response()->json([
            'success' => ! isset($result['error']),
            ...$result,
        ]);
    }

    public function parseAll(Request $request): JsonResponse
    {
        unset($request);

        $results = app(RssParserService::class)->parseAllFeeds('api');

        return response()->json([
            'success' => collect($results)->every(fn (array $result): bool => ! isset($result['error'])),
            'results' => $results,
            'totals' => [
                'new' => collect($results)->sum('new'),
                'skip' => collect($results)->sum('skip'),
                'errors' => collect($results)->sum('errors'),
            ],
        ]);
    }

    public function parseCategory(string $slug): JsonResponse
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();
        $parser = app(RssParserService::class);
        $feeds = RssFeed::query()
            ->active()
            ->inCategory($category)
            ->get();

        if ($feeds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active feeds found for this category.',
                'results' => [],
                'totals' => [
                    'new' => 0,
                    'skip' => 0,
                    'errors' => 0,
                ],
            ], 424);
        }

        $results = collect($parser->parseFeeds($feeds, 'api'))->values();

        return response()->json([
            'success' => $results->every(fn (array $result): bool => ! isset($result['error'])),
            'results' => $results,
            'totals' => [
                'new' => $results->sum('new'),
                'skip' => $results->sum('skip'),
                'errors' => $results->sum('errors'),
            ],
        ]);
    }
}
