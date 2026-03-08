<?php

namespace App\Services;

use Illuminate\Support\Str;

enum TrackedMetric: string
{
    case ArticleView = 'article_view';
    case ArticleUniqueView = 'article_unique_view';
    case BookmarkAdded = 'bookmark_added';
    case BookmarkRemoved = 'bookmark_removed';
    case NewsletterSubscription = 'newsletter_subscription';
    case NewsletterConfirmation = 'newsletter_confirmation';
    case NewsletterUnsubscription = 'newsletter_unsubscription';
    case RssParseRun = 'rss_parse_run';
    case RssParseFailure = 'rss_parse_failure';
    case RssArticleImported = 'rss_article_imported';

    public function category(): string
    {
        return match ($this) {
            self::ArticleView, self::ArticleUniqueView, self::BookmarkAdded, self::BookmarkRemoved => 'engagement',
            self::NewsletterSubscription, self::NewsletterConfirmation, self::NewsletterUnsubscription => 'newsletter',
            self::RssParseRun, self::RssParseFailure, self::RssArticleImported => 'rss',
        };
    }

    public function label(): string
    {
        return Str::headline(str_replace('_', ' ', $this->value));
    }
}
