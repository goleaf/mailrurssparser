<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class RssParserService
{
    private const RU_TRANSLIT = [
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

    private function logger(): \Illuminate\Log\Logger
    {
        return Log::channel('rss');
    }

    /**
     * @throws \RuntimeException
     */
    private function fetchWithRetry(string $url, int $maxRetries = 3): string
    {
        $currentUrl = $url;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout((int) config('rss.parser.timeout', 30))
                    ->connectTimeout((int) config('rss.parser.connect_timeout', 10))
                    ->withoutVerifying()
                    ->withHeaders([
                        'User-Agent' => (string) config('rss.parser.user_agent'),
                        'Accept' => 'application/rss+xml, application/xml, text/xml, */*',
                        'Accept-Encoding' => 'gzip, deflate',
                        'Cache-Control' => 'no-cache',
                    ])
                    ->get($currentUrl);

                $status = $response->status();

                if ($status === 200) {
                    return $response->body();
                }

                if (in_array($status, [301, 302], true)) {
                    $location = trim((string) $response->header('Location'));

                    if ($location !== '') {
                        $currentUrl = $location;

                        continue;
                    }
                }

                if ($status === 429) {
                    $this->logger()->error("HTTP error for {$currentUrl}: {$status}");
                    sleep(2 ** $attempt);

                    continue;
                }

                if (in_array($status, [404, 410], true)) {
                    $this->logger()->error("HTTP error for {$currentUrl}: {$status}");

                    throw new RuntimeException("Feed gone: HTTP {$status}");
                }

                $this->logger()->error("HTTP error for {$currentUrl}: {$status}");

                if ($attempt === $maxRetries) {
                    break;
                }

                sleep(1);
            } catch (ConnectionException $exception) {
                $this->logger()->error("HTTP error for {$currentUrl}: {$exception->getMessage()}");

                if ($attempt === $maxRetries) {
                    throw new RuntimeException("Feed unreachable after {$maxRetries} attempts: {$url}", previous: $exception);
                }

                sleep(1);
            }
        }

        throw new RuntimeException("Feed unreachable after {$maxRetries} attempts: {$url}");
    }

    /**
     * @throws \RuntimeException
     */
    private function parseXml(string $body): SimpleXMLElement
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);

        if ($xml === false) {
            $errors = libxml_get_errors();
            $message = isset($errors[0]) ? trim($errors[0]->message) : 'Invalid XML.';
            libxml_clear_errors();

            throw new RuntimeException($message);
        }

        libxml_clear_errors();

        if (! isset($xml->channel)) {
            throw new RuntimeException('Invalid RSS: no channel element');
        }

        return $xml;
    }

    private function detectEncoding(string $body): string
    {
        $encoding = 'UTF-8';

        if (preg_match('/<\?xml[^>]+encoding=["\']([^"\']+)["\']/i', $body, $matches) === 1) {
            $encoding = strtoupper(trim($matches[1]));
        } elseif (preg_match('/charset=([a-zA-Z0-9\-_]+)/i', $body, $matches) === 1) {
            $encoding = strtoupper(trim($matches[1]));
        }

        if (in_array($encoding, ['WINDOWS-1251', 'CP1251'], true) && function_exists('iconv')) {
            $converted = iconv('Windows-1251', 'UTF-8//IGNORE', $body);

            if ($converted !== false) {
                $converted = preg_replace('/(<\?xml[^>]+encoding=["\'])[^"\']+(["\'])/i', '$1UTF-8$2', $converted) ?? $converted;
                $converted = preg_replace('/(<meta[^>]+charset=)[a-zA-Z0-9\-_]+/i', '$1UTF-8', $converted) ?? $converted;

                return $converted;
            }
        }

        return $body;
    }

    private function sanitizeText(string $text): string
    {
        $text = preg_replace('/<(script|iframe|style|object|embed|form)\b[^>]*>.*?<\/\1>/is', '', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @throws \RuntimeException
     */
    public function fetchFeedXml(string $url): SimpleXMLElement
    {
        $body = $this->fetchWithRetry($url);
        $body = $this->detectEncoding($body);

        try {
            return $this->parseXml($body);
        } catch (Throwable $exception) {
            $this->logger()->error("XML parse error for {$url}: {$exception->getMessage()}");

            throw new RuntimeException($exception->getMessage(), previous: $exception);
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

        foreach ($xml->channel->item as $item) {
            $items[] = $item;
        }

        return array_slice($items, 0, (int) config('rss.parser.max_items_per_feed', 100));
    }

    private function extractTitle(SimpleXMLElement $item): string
    {
        $title = $this->sanitizeText((string) $item->title);
        $title = preg_replace('/\s+(?:[-—|]\s*(?:Mail\.ru|Новости Mail))$/u', '', $title) ?? $title;

        return trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function extractLink(SimpleXMLElement $item): string
    {
        $link = trim((string) $item->link);

        if ($link === '' && isset($item->guid)) {
            $guidAttributes = $item->guid->attributes();
            $isPermalink = strtolower((string) ($guidAttributes['isPermaLink'] ?? 'true')) !== 'false';

            if ($isPermalink) {
                $link = trim((string) $item->guid);
            }
        }

        return filter_var($link, FILTER_VALIDATE_URL) ? $link : '';
    }

    private function extractGuid(SimpleXMLElement $item): string
    {
        $guid = trim((string) $item->guid);

        if ($guid === '') {
            $guid = $this->extractLink($item);
        }

        return trim($guid);
    }

    private function extractDescription(SimpleXMLElement $item): string
    {
        $content = '';
        $namespaces = $item->getNamespaces(true);

        if (isset($namespaces['content'])) {
            $contentNodes = $item->children($namespaces['content']);
            $content = (string) $contentNodes->encoded;
        }

        if ($content === '' && isset($namespaces['media'])) {
            $mediaNodes = $item->children($namespaces['media']);
            $content = (string) $mediaNodes->description;
        }

        if ($content === '') {
            $content = (string) $item->description;
        }

        return $this->sanitizeText($content);
    }

    private function extractFullHtml(SimpleXMLElement $item): string
    {
        $content = '';
        $namespaces = $item->getNamespaces(true);

        if (isset($namespaces['content'])) {
            $contentNodes = $item->children($namespaces['content']);
            $content = (string) $contentNodes->encoded;
        }

        if ($content === '' && isset($namespaces['media'])) {
            $mediaNodes = $item->children($namespaces['media']);
            $content = (string) $mediaNodes->description;
        }

        if ($content === '') {
            $content = (string) $item->description;
        }

        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/<(script|iframe|style|object|embed|form)\b[^>]*>.*?<\/\1>/is', '', $content));
    }

    private function extractImage(SimpleXMLElement $item): ?string
    {
        $namespaces = $item->getNamespaces(true);

        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);

            foreach ($media->content as $mediaContent) {
                $attributes = $mediaContent->attributes();
                $url = trim((string) ($attributes['url'] ?? ''));
                $medium = strtolower((string) ($attributes['medium'] ?? ''));

                if ($medium === 'image' && filter_var($url, FILTER_VALIDATE_URL)) {
                    return $url;
                }
            }

            $thumbnailUrl = trim((string) ($media->thumbnail->attributes()['url'] ?? ''));

            if (filter_var($thumbnailUrl, FILTER_VALIDATE_URL)) {
                return $thumbnailUrl;
            }
        }

        if (isset($item->enclosure)) {
            $attributes = $item->enclosure->attributes();
            $url = trim((string) ($attributes['url'] ?? ''));
            $type = strtolower((string) ($attributes['type'] ?? ''));

            if (str_starts_with($type, 'image/') && filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        $imageUrl = trim((string) $item->image->url);

        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return $imageUrl;
        }

        $descriptionHtml = (string) $item->description;

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $descriptionHtml, $matches) === 1
            && filter_var($matches[1], FILTER_VALIDATE_URL)
        ) {
            return $matches[1];
        }

        return null;
    }

    private function extractAuthor(SimpleXMLElement $item): ?string
    {
        $author = trim((string) $item->author);
        $namespaces = $item->getNamespaces(true);

        if ($author === '' && isset($namespaces['dc'])) {
            $dc = $item->children($namespaces['dc']);
            $author = trim((string) $dc->creator);
        }

        $author = preg_replace('/<?[^<>\s]+@[^<>\s]+>?/u', '', $author) ?? $author;
        $author = trim($author, " \t\n\r\0\x0B-");

        return $author !== '' ? $author : null;
    }

    private function extractPubDate(SimpleXMLElement $item): Carbon
    {
        $values = [trim((string) $item->pubDate)];
        $namespaces = $item->getNamespaces(true);
        $formats = [
            DATE_RSS,
            DATE_ATOM,
            DATE_ISO8601,
            'Y-m-d\TH:i:sP',
            'Y-m-d H:i:s',
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'd.m.Y',
            'd M Y H:i:s',
            'd M Y H:i',
            'd F Y H:i:s',
            'd F Y H:i',
        ];

        if (isset($namespaces['dc'])) {
            $dc = $item->children($namespaces['dc']);
            $values[] = trim((string) $dc->date);
        }

        foreach ($values as $value) {
            if ($value === '') {
                continue;
            }

            foreach ($formats as $format) {
                try {
                    return Carbon::createFromFormat($format, $value);
                } catch (Throwable) {
                }
            }

            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                continue;
            }
        }

        return Carbon::now();
    }

    /**
     * @return array<int, string>
     */
    private function extractCategories(SimpleXMLElement $item): array
    {
        $categories = [];

        foreach ($item->category as $category) {
            $name = $this->sanitizeText((string) $category);

            if ($name !== '') {
                $categories[] = $name;
            }
        }

        return array_values(array_unique($categories));
    }

    private function generateUniqueSlug(string $title): string
    {
        $transliterated = strtr($title, self::RU_TRANSLIT);

        if (function_exists('iconv')) {
            $iconvTitle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);

            if ($iconvTitle !== false && trim($iconvTitle) !== '') {
                $transliterated = $iconvTitle;
            }
        }

        $slug = Str::slug($transliterated);

        if ($slug === '') {
            $slug = 'article-'.time().'-'.Str::lower(Str::random(4));
        }

        $baseSlug = $slug;
        $counter = 2;

        while (Article::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function calculateReadingTime(string $text): int
    {
        $wordCount = str_word_count(strip_tags($text));
        $wordsPerMinute = (int) config('rss.article.words_per_minute', 200);
        $wordsPerMinute = $wordsPerMinute > 0 ? $wordsPerMinute : 200;

        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }

    private function makeShortDescription(string $text, int $length = 300): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));

        if ($text === '') {
            return '';
        }

        return Str::limit($text, $length, '...');
    }

    private function isDuplicate(string $guid, string $url): bool
    {
        if ($guid === '' && $url === '') {
            return false;
        }

        return Article::withTrashed()
            ->where(function ($query) use ($guid, $url): void {
                if ($guid !== '') {
                    $query->where('source_guid', $guid);
                }

                if ($url !== '') {
                    $method = $guid !== '' ? 'orWhere' : 'where';
                    $query->{$method}('source_url', $url);
                }
            })
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildArticleData(SimpleXMLElement $item, int $categoryId, int $rssFeedId, RssFeed $feed): array
    {
        /** @var array<string, mixed> $extraSettings */
        $extraSettings = is_array($feed->extra_settings) ? $feed->extra_settings : [];
        $title = $this->extractTitle($item);
        $description = $this->extractDescription($item);
        $fullHtml = $this->extractFullHtml($item);
        $content = $fullHtml !== '' ? $fullHtml : $description;
        $isFeatured = $feed->auto_featured && ! Article::query()->where('rss_feed_id', $rssFeedId)->exists();
        $shortDescriptionLength = (int) ($extraSettings['short_description_length'] ?? config('rss.article.short_description_length', 300));
        $status = is_string($extraSettings['status'] ?? null)
            ? (string) $extraSettings['status']
            : ($feed->auto_publish ? 'published' : 'draft');
        $contentType = is_string($extraSettings['content_type'] ?? null)
            ? (string) $extraSettings['content_type']
            : 'news';
        $sourceName = is_string($extraSettings['source_name'] ?? null) && $extraSettings['source_name'] !== ''
            ? (string) $extraSettings['source_name']
            : $feed->source_name;
        $author = $this->extractAuthor($item)
            ?? (is_string($extraSettings['default_author'] ?? null) && $extraSettings['default_author'] !== ''
                ? (string) $extraSettings['default_author']
                : $sourceName);

        return [
            'category_id' => $categoryId,
            'rss_feed_id' => $rssFeedId,
            'title' => $title,
            'slug' => $this->generateUniqueSlug($title),
            'source_url' => $this->extractLink($item),
            'source_guid' => $this->extractGuid($item),
            'image_url' => $this->extractImage($item),
            'image_caption' => null,
            'short_description' => $this->makeShortDescription(
                $description,
                $shortDescriptionLength,
            ),
            'rss_content' => $content,
            'author' => $author,
            'source_name' => $sourceName,
            'status' => $status,
            'content_type' => $contentType,
            'is_featured' => $isFeatured,
            'importance' => $this->guessImportance($title, $description),
            'reading_time' => $this->calculateReadingTime($content),
            'published_at' => $this->extractPubDate($item),
            'rss_parsed_at' => now(),
        ];
    }

    private function guessImportance(string $title, string $desc): int
    {
        $score = 5;
        $normalizedTitle = Str::lower($title);

        if (Str::contains($normalizedTitle, ['срочно', 'экстренно', 'чрезвычайно'])) {
            $score += 3;
        }

        if (Str::contains($normalizedTitle, ['объявил', 'заявил', 'подписал'])) {
            $score += 1;
        }

        if (mb_strlen($title) > 80) {
            $score += 1;
        }

        if (mb_strlen($desc) < 100) {
            $score -= 1;
        }

        return max(1, min(10, $score));
    }

    private function makeTagSlug(string $name): string
    {
        $slug = Str::slug(strtr($name, self::RU_TRANSLIT));

        if ($slug !== '') {
            return $slug;
        }

        return 'tag-'.Str::lower(Str::random(8));
    }

    private function createArticle(array $data): Article
    {
        return Article::query()->create($data);
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function syncArticleTags(Article $article, array $categories): void
    {
        $tagIds = collect($categories)
            ->map(function (string $name): int {
                $tag = Tag::query()->firstOrCreate(
                    ['name' => $name],
                    [
                        'slug' => $this->makeTagSlug($name),
                        'color' => '#6B7280',
                    ],
                );

                return $tag->id;
            })
            ->values()
            ->all();

        if ($tagIds !== []) {
            $article->syncTags($tagIds);
        }
    }

    private function processItem(SimpleXMLElement $item, int $categoryId, int $rssFeedId, RssFeed $feed): ?Article
    {
        $title = $this->extractTitle($item);
        $this->logger()->debug("Processing item: {$title}");

        try {
            $guid = $this->extractGuid($item);
            $link = $this->extractLink($item);

            if ($guid === '' && $link === '') {
                return null;
            }

            if ($this->isDuplicate($guid, $link)) {
                $this->logger()->warning("Duplicate found: {$guid}");

                return null;
            }

            $data = $this->buildArticleData($item, $categoryId, $rssFeedId, $feed);

            if ($data['title'] === '' || (($data['source_url'] ?? '') === '' && ($data['source_guid'] ?? '') === '')) {
                return null;
            }

            return DB::transaction(function () use ($item, $data): Article {
                $article = $this->createArticle($data);
                $this->syncArticleTags($article, $this->extractCategories($item));

                return $article;
            });
        } catch (Throwable $exception) {
            $safeTitle = $title !== '' ? $title : '(untitled)';

            $this->logger()->warning("Item failed: {$exception->getMessage()} for item {$safeTitle}");

            return null;
        }
    }

    /**
     * @return array<string, int|string|null>
     */
    public function parseFeed(RssFeed $feed, string $triggeredBy = 'scheduler'): array
    {
        $this->logger()->info("Starting parse of feed: {$feed->title} ({$feed->url})");

        $log = RssParseLog::query()->create([
            'rss_feed_id' => $feed->id,
            'started_at' => now(),
            'triggered_by' => $triggeredBy,
        ]);

        $startTime = microtime(true);
        $new = 0;
        $skip = 0;
        $errors = 0;
        $total = 0;
        $error = null;
        $itemErrors = [];

        try {
            $xml = $this->fetchFeedXml($feed->url);
            $items = $this->getFeedItems($xml);
            $total = count($items);

            foreach ($items as $item) {
                try {
                    $article = $this->processItem($item, $feed->category_id, $feed->id, $feed);

                    if ($article instanceof Article) {
                        $new++;
                    } else {
                        $skip++;
                    }
                } catch (Throwable $exception) {
                    $errors++;
                    $itemErrors[] = [
                        'title' => $this->extractTitle($item),
                        'error' => $exception->getMessage(),
                    ];
                    $this->logger()->error("Item failed: {$exception->getMessage()} for item {$this->extractTitle($item)}");
                }
            }

            $feed->markParsed($new, $skip, $errors);
            $feed->refresh();
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            $feed->markFailed($error);
            $feed->refresh();
            $this->logger()->error("Feed parse failed [{$feed->title}]: {$error}");
        } finally {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $log->update([
                'finished_at' => now(),
                'new_count' => $new,
                'skip_count' => $skip,
                'error_count' => $errors,
                'total_items' => $total,
                'duration_ms' => $durationMs,
                'success' => $errors === 0 && $error === null,
                'error_message' => $error,
                'item_errors' => $itemErrors,
            ]);
        }

        $this->logger()->info("Feed parsed: {$new} new, {$skip} skipped, {$errors} errors");

        return [
            'feed' => $feed->title,
            'new' => $new,
            'skip' => $skip,
            'errors' => $errors,
            'total' => $total,
            'duration_ms' => $durationMs ?? 0,
            'error' => $error,
        ];
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    public function parseAllFeeds(string $triggeredBy = 'scheduler'): array
    {
        return RssFeed::query()
            ->active()
            ->with('category')
            ->get()
            ->mapWithKeys(function (RssFeed $feed) use ($triggeredBy): array {
                return [$feed->id => $this->parseFeed($feed, $triggeredBy)];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    public function parseDueFeeds(string $triggeredBy = 'scheduler'): array
    {
        return RssFeed::query()
            ->dueForParsing()
            ->with('category')
            ->get()
            ->mapWithKeys(function (RssFeed $feed) use ($triggeredBy): array {
                return [$feed->id => $this->parseFeed($feed, $triggeredBy)];
            })
            ->all();
    }

    /**
     * @return array{feed: string, items: int, new: int, skip: int}
     */
    public function inspectFeed(RssFeed $feed, ?int $limit = null): array
    {
        $xml = $this->fetchFeedXml($feed->url);
        $items = $this->getFeedItems($xml);

        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        $new = 0;
        $skip = 0;

        foreach ($items as $item) {
            $guid = $this->extractGuid($item);
            $link = $this->extractLink($item);
            $title = $this->extractTitle($item);

            if ($title === '' || ($guid === '' && $link === '')) {
                $skip++;

                continue;
            }

            if ($this->isDuplicate($guid, $link)) {
                $skip++;
            } else {
                $new++;
            }
        }

        return [
            'feed' => $feed->title,
            'items' => count($items),
            'new' => $new,
            'skip' => $skip,
        ];
    }

    /**
     * @return array<int, array{title: string, link: string, pub_date: string, image: string|null}>
     */
    public function previewFeed(string $url): array
    {
        $xml = $this->fetchFeedXml($url);

        return collect($this->getFeedItems($xml))
            ->map(function (SimpleXMLElement $item): array {
                return [
                    'title' => $this->extractTitle($item),
                    'link' => $this->extractLink($item),
                    'pub_date' => $this->extractPubDate($item)->toIso8601String(),
                    'image' => $this->extractImage($item),
                ];
            })
            ->all();
    }

    /**
     * @throws \RuntimeException
     */
    public function importArticleFromUrl(string $url): Article
    {
        $feed = RssFeed::query()
            ->with('category')
            ->firstWhere('url', $url);

        if (! $feed instanceof RssFeed) {
            $category = Category::query()
                ->where(function ($query): void {
                    $query->where('slug', 'main')
                        ->orWhere('is_active', true);
                })
                ->orderByRaw("case when slug = 'main' then 0 else 1 end")
                ->orderBy('order')
                ->first();

            if (! $category instanceof Category) {
                throw new RuntimeException('Нужна хотя бы одна категория для импорта статьи.');
            }

            $feed = new RssFeed([
                'category_id' => $category->id,
                'title' => 'Imported Feed',
                'url' => $url,
                'source_name' => 'Новости Mail',
                'auto_publish' => false,
                'auto_featured' => false,
                'fetch_interval' => 15,
            ]);
            $feed->setRelation('category', $category);
        }

        $xml = $this->fetchFeedXml($url);
        $item = $this->getFeedItems($xml)[0] ?? null;

        if (! $item instanceof SimpleXMLElement) {
            throw new RuntimeException('В RSS-ленте не найдено ни одного материала.');
        }

        $guid = $this->extractGuid($item);
        $link = $this->extractLink($item);

        if ($guid === '' && $link === '') {
            throw new RuntimeException('Не удалось определить ссылку или GUID материала.');
        }

        if ($this->isDuplicate($guid, $link)) {
            throw new RuntimeException('Материал уже существует в базе.');
        }

        $data = $this->buildArticleData($item, $feed->category_id, $feed->id ?? 0, $feed);
        $data['rss_feed_id'] = $feed->exists ? $feed->id : null;
        $data['status'] = 'draft';
        $data['is_featured'] = false;

        if (($data['title'] ?? '') === '') {
            throw new RuntimeException('Не удалось определить заголовок материала.');
        }

        return DB::transaction(function () use ($item, $data): Article {
            $article = $this->createArticle($data);
            $this->syncArticleTags($article, $this->extractCategories($item));

            return $article;
        });
    }
}
