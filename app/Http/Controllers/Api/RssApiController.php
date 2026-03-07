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
        $feeds = RssFeed::query()->with('category')->get()->map(function (RssFeed $feed): array {
            $statusLabel = match (true) {
                ! $feed->is_active => 'Disabled',
                $feed->last_error !== null && $feed->last_error !== '' => 'Error',
                $feed->next_parse_at !== null && $feed->next_parse_at->lte(now()) => 'Due',
                default => 'OK',
            };

            return [
                'id' => $feed->id,
                'title' => $feed->title,
                'category_name' => $feed->category?->name,
                'is_active' => $feed->is_active,
                'last_parsed_at' => $feed->last_parsed_at?->toIso8601String(),
                'next_parse_at' => $feed->next_parse_at?->toIso8601String(),
                'last_run_new_count' => $feed->last_run_new_count,
                'consecutive_failures' => $feed->consecutive_failures,
                'last_error' => $feed->last_error,
                'status_label' => $statusLabel,
            ];
        });

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
        $results = RssFeed::query()
            ->active()
            ->where('category_id', $category->id)
            ->get()
            ->map(fn (RssFeed $feed): array => $parser->parseFeed($feed, 'api'))
            ->values();

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
