<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;

class RssParserService
{
    /**
     * @throws \RuntimeException
     */
    private function fetchFeedXml(string $url): SimpleXMLElement
    {
        $response = Http::timeout(config('rss.parser.timeout', 30))
            ->connectTimeout(config('rss.parser.connect_timeout', 10))
            ->withHeaders(['User-Agent' => config('rss.parser.user_agent')])
            ->withOptions(['verify' => false])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Feed fetch failed: HTTP '.$response->status().' for '.$url);
        }

        $body = $response->body();

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            $errors = libxml_get_errors();
            $message = $errors[0]->message ?? 'Invalid XML.';
            libxml_clear_errors();

            throw new RuntimeException(trim($message));
        }

        if (! isset($xml->channel)) {
            throw new RuntimeException('Invalid RSS: no channel element in '.$url);
        }

        return $xml;
    }

    private function extractTitle(SimpleXMLElement $item): string
    {
        $title = (string) $item->title;

        if ($title === '') {
            return '';
        }

        return html_entity_decode(trim($title));
    }

    private function extractLink(SimpleXMLElement $item): string
    {
        $link = trim((string) $item->link);

        if ($link === '') {
            $link = trim((string) $item->guid);
        }

        return $link;
    }

    private function extractGuid(SimpleXMLElement $item): string
    {
        $guid = trim((string) $item->guid);

        if ($guid === '') {
            $guid = $this->extractLink($item);
        }

        return $guid;
    }

    private function extractDescription(SimpleXMLElement $item): string
    {
        $description = '';
        $namespaces = $item->getNamespaces(true);

        if (isset($namespaces['content'])) {
            $content = $item->children($namespaces['content']);
            $description = (string) $content->encoded;
        }

        if ($description === '') {
            $description = (string) $item->description;
        }

        return trim(strip_tags($description));
    }

    private function extractImage(SimpleXMLElement $item): ?string
    {
        if (isset($item->enclosure)) {
            $attributes = $item->enclosure->attributes();
            $url = trim((string) ($attributes['url'] ?? ''));
            $type = (string) ($attributes['type'] ?? '');

            if ($url !== '' && str_starts_with($type, 'image/')) {
                return $url;
            }
        }

        $namespaces = $item->getNamespaces(true);

        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);
            $thumbnailUrl = trim((string) ($media->thumbnail->attributes()['url'] ?? ''));

            if ($thumbnailUrl !== '') {
                return $thumbnailUrl;
            }

            foreach ($media->content as $mediaContent) {
                $attributes = $mediaContent->attributes();
                $url = trim((string) ($attributes['url'] ?? ''));
                $type = (string) ($attributes['type'] ?? '');

                if ($url !== '' && str_starts_with($type, 'image')) {
                    return $url;
                }
            }
        }

        $description = (string) $item->description;

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $description, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function extractPubDate(SimpleXMLElement $item): ?Carbon
    {
        $pubDate = trim((string) $item->pubDate);

        if ($pubDate === '') {
            return null;
        }

        try {
            return Carbon::parse($pubDate);
        } catch (\Throwable) {
            return null;
        }
    }

    private function makeShortDescription(string $text, int $length = 300): string
    {
        $text = trim(strip_tags($text));

        if ($text === '') {
            return '';
        }

        return Str::limit($text, $length, '...');
    }

    private function calculateReadingTime(string $text): int
    {
        $wordCount = str_word_count(strip_tags($text));
        $wordsPerMinute = (int) config('rss.article.words_per_minute', 200);

        if ($wordsPerMinute <= 0) {
            $wordsPerMinute = 200;
        }

        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }

    /**
     * @return array<int, \SimpleXMLElement>
     */
    public function getFeedItems(SimpleXMLElement $xml): array
    {
        if (! isset($xml->channel->item)) {
            return [];
        }

        $items = [];
        $limit = (int) config('rss.parser.max_items_per_feed', 50);
        $limit = $limit > 0 ? $limit : 50;

        foreach ($xml->channel->item as $item) {
            $items[] = $item;

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }
}
