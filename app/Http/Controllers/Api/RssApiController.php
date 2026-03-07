<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\RssFeed;
use App\Services\RssParserService;
use Illuminate\Http\JsonResponse;

class RssApiController extends Controller
{
    public function status(): JsonResponse
    {
        $feeds = RssFeed::query()->with('category')->get()->map(function (RssFeed $feed): array {
            $statusLabel = match (true) {
                ! $feed->is_active => 'Disabled',
                $feed->last_error !== null && $feed->last_error !== '' => 'Error',
                $feed->next_parse_at === null || $feed->next_parse_at->lte(now()) => 'Due',
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

    public function parseFeed(RssParserService $parser, int $feedId): JsonResponse
    {
        $feed = RssFeed::query()->findOrFail($feedId);
        $result = $parser->parseFeed($feed, 'api');

        return response()->json([
            'success' => empty($result['error']),
            ...$result,
        ]);
    }

    public function parseAll(RssParserService $parser): JsonResponse
    {
        $results = $parser->parseAllFeeds('api');

        return response()->json([
            'success' => collect($results)->every(fn (array $result): bool => empty($result['error'])),
            'results' => $results,
            'totals' => [
                'new' => collect($results)->sum('new'),
                'skip' => collect($results)->sum('skip'),
                'errors' => collect($results)->sum('errors'),
            ],
        ]);
    }

    public function parseCategory(RssParserService $parser, string $slug): JsonResponse
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();
        $results = RssFeed::query()
            ->active()
            ->where('category_id', $category->id)
            ->get()
            ->map(fn (RssFeed $feed): array => $parser->parseFeed($feed, 'api'))
            ->values();

        return response()->json([
            'success' => $results->every(fn (array $result): bool => empty($result['error'])),
            'results' => $results,
            'totals' => [
                'new' => $results->sum('new'),
                'skip' => $results->sum('skip'),
                'errors' => $results->sum('errors'),
            ],
        ]);
    }
}
