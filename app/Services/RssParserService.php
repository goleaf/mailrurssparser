<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
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
