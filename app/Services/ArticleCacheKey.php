<?php

namespace App\Services;

enum ArticleCacheKey: string
{
    case Categories = 'categories';
    case BreakingNews = 'breaking_news';
    case FeaturedArticles = 'featured_articles';
    case StatsOverview = 'stats_overview';

    public static function trendingTags(int $limit): string
    {
        return 'trending_tags_'.$limit;
    }
}
