<?php

namespace App\Services;

use App\Models\Article;
use App\Models\RssFeed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class RssParserService
{
    private const TRANSLIT = [
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'yo',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'y',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'kh',
        'ц' => 'ts',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'shch',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya',
        'А' => 'A',
        'Б' => 'B',
        'В' => 'V',
        'Г' => 'G',
        'Д' => 'D',
        'Е' => 'E',
        'Ё' => 'Yo',
        'Ж' => 'Zh',
        'З' => 'Z',
        'И' => 'I',
        'Й' => 'Y',
        'К' => 'K',
        'Л' => 'L',
        'М' => 'M',
        'Н' => 'N',
        'О' => 'O',
        'П' => 'P',
        'Р' => 'R',
        'С' => 'S',
        'Т' => 'T',
        'У' => 'U',
        'Ф' => 'F',
        'Х' => 'Kh',
        'Ц' => 'Ts',
        'Ч' => 'Ch',
        'Ш' => 'Sh',
        'Щ' => 'Shch',
        'Ъ' => '',
        'Ы' => 'Y',
        'Ь' => '',
        'Э' => 'E',
        'Ю' => 'Yu',
        'Я' => 'Ya',
    ];

    /**
     * @throws \RuntimeException
     */
    private function fetchFeedXml(string $url): SimpleXMLElement
    {
        $logger = Log::channel('rss');
        $response = Http::timeout(config('rss.parser.timeout', 30))
            ->connectTimeout(config('rss.parser.connect_timeout', 10))
            ->withHeaders(['User-Agent' => config('rss.parser.user_agent')])
            ->withOptions(['verify' => false])
            ->get($url);

        if (! $response->successful()) {
            $logger->error('HTTP error for '.$url.': '.$response->status());

            throw new RuntimeException('Feed fetch failed: HTTP '.$response->status().' for '.$url);
        }

        $body = $response->body();

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            $errors = libxml_get_errors();
            $message = $errors[0]->message ?? 'Invalid XML.';
            libxml_clear_errors();
            $logger->error('XML parse error for '.$url.': '.trim($message));

            throw new RuntimeException(trim($message));
        }

        if (! isset($xml->channel)) {
            $logger->error('XML parse error for '.$url.': Invalid RSS: no channel element');

            throw new RuntimeException('Invalid RSS: no channel element in '.$url);
        }

        return $xml;
    }

    private function extractTitle(SimpleXMLElement $item): string
    {
        $title = trim((string) $item->title);

        if ($title === '') {
            return '';
        }

        return html_entity_decode($title);
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
        $ns = $item->getNamespaces(true);

        if (isset($ns['content'])) {
            $content = $item->children($ns['content']);
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

        $ns = $item->getNamespaces(true);

        if (isset($ns['media'])) {
            $media = $item->children($ns['media']);
            $thumbnailUrl = trim((string) ($media->thumbnail['url'] ?? ''));

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

    private function isDuplicate(string $guid, string $url): bool
    {
        return Article::where(function ($q) use ($guid, $url): void {
            $q->where('source_guid', $guid)->orWhere('source_url', $url);
        })
            ->exists();
    }

    private function generateUniqueSlug(string $title): string
    {
        $transliterated = strtr($title, self::TRANSLIT);

        if (function_exists('iconv')) {
            $iconv = iconv('UTF-8', 'ASCII//TRANSLIT', $title);

            if ($iconv !== false) {
                $transliterated = $iconv;
            }
        }

        $slug = Str::slug($transliterated);

        if ($slug === '') {
            $slug = 'article-'.time();
        }

        $baseSlug = $slug;
        $suffix = 2;

        while (Article::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createArticle(array $data): Article
    {
        return Article::create($data);
    }

    private function processItem(SimpleXMLElement $item, int $categoryId, int $rssFeedId): ?Article
    {
        $logger = Log::channel('rss');

        try {
            $title = $this->extractTitle($item);

            if (config('app.debug')) {
                $logger->debug('Processing item: '.$title);
            }

            $link = $this->extractLink($item);
            $guid = $this->extractGuid($item);
            $description = $this->extractDescription($item);
            $imageUrl = $this->extractImage($item);
            $publishedAt = $this->extractPubDate($item) ?? now();

            if ($title === '' || $link === '') {
                return null;
            }

            if ($this->isDuplicate($guid, $link)) {
                $logger->warning('Duplicate found: '.$guid);

                return null;
            }

            $rawDescription = (string) $item->description;
            $namespaces = $item->getNamespaces(true);

            if ($rawDescription === '' && isset($namespaces['content'])) {
                $content = $item->children($namespaces['content']);
                $rawDescription = (string) $content->encoded;
            }

            $data = [
                'category_id' => $categoryId,
                'rss_feed_id' => $rssFeedId,
                'title' => $title,
                'slug' => $this->generateUniqueSlug($title),
                'source_url' => $link,
                'source_guid' => $guid,
                'image_url' => $imageUrl,
                'short_description' => $this->makeShortDescription($description),
                'rss_content' => $rawDescription,
                'author' => config('rss.article.default_author'),
                'source_name' => 'Новости Mail',
                'status' => config('rss.article.default_status'),
                'reading_time' => $this->calculateReadingTime($description),
                'published_at' => $publishedAt,
                'rss_parsed_at' => now(),
            ];

            return $this->createArticle($data);
        } catch (Throwable $e) {
            $itemTitle = isset($title) && $title !== '' ? $title : trim((string) $item->title);
            $logger->warning('Item failed: '.$e->getMessage().' for item '.$itemTitle);

            return null;
        }
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

    /**
     * @return array<string, int|string|null>
     */
    public function parseFeed(RssFeed $feed): array
    {
        $logger = Log::channel('rss');
        $result = [
            'feed_title' => $feed->title,
            'new' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_message' => null,
        ];

        try {
            $logger->info('Starting parse of feed: '.$feed->title.' ('.$feed->url.')');
            $xml = $this->fetchFeedXml($feed->url);
            $items = $this->getFeedItems($xml);

            foreach ($items as $item) {
                try {
                    $article = $this->processItem($item, $feed->category_id, $feed->id);

                    if ($article !== null) {
                        $result['new']++;
                    } else {
                        $result['skipped']++;
                    }
                } catch (Throwable $e) {
                    $result['errors']++;
                    $logger->warning('Item parse error: '.$e->getMessage());
                }
            }

            RssFeed::query()
                ->whereKey($feed->id)
                ->update([
                    'last_parsed_at' => now(),
                    'last_run_new_count' => $result['new'],
                    'last_run_skip_count' => $result['skipped'],
                    'articles_parsed_total' => DB::raw('articles_parsed_total + '.$result['new']),
                    'last_error' => null,
                ]);

            $feed->refresh();

            $logger->info('Feed parsed: '.$result['new'].' new, '.$result['skipped'].' skipped, '.$result['errors'].' errors');
        } catch (Throwable $e) {
            $result['error_message'] = $e->getMessage();
            $logger->error('Feed parse failed ['.$feed->title.']: '.$e->getMessage());

            $feed->last_error = $e->getMessage();
            $feed->save();
        }

        return $result;
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    public function parseAllFeeds(): array
    {
        $results = [];

        $feeds = RssFeed::active()->with('category')->get();

        foreach ($feeds as $feed) {
            $results[$feed->id] = $this->parseFeed($feed);
        }

        return $results;
    }
}
