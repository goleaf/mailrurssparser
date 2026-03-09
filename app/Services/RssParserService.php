<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\RssParseLog;
use App\Models\SubCategory;
use App\Models\Tag;
use App\Support\Utf8Normalizer;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\Batch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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

    /**
     * @var Collection<int, Category>|null
     */
    private ?Collection $categoryCatalog = null;

    public function __construct(private ?SourceArticleParserService $sourceArticleParser = null)
    {
        $this->sourceArticleParser ??= app(SourceArticleParserService::class);
    }

    private function logger(): \Illuminate\Log\Logger
    {
        return Log::channel('rss');
    }

    private function configureFeedRequest(
        PendingRequest $request,
        string $requestUrl,
        string $originalUrl,
        int $attempt,
        int $maxRetries,
    ): PendingRequest {
        return $request
            ->timeout((int) config('rss.parser.timeout', 30))
            ->connectTimeout((int) config('rss.parser.connect_timeout', 10))
            ->withoutVerifying()
            ->withoutRedirecting()
            ->withHeaders([
                'User-Agent' => (string) config('rss.parser.user_agent'),
                'Accept' => 'application/rss+xml, application/xml, text/xml, */*',
                'Accept-Encoding' => 'gzip, deflate',
                'Cache-Control' => 'no-cache',
            ])
            ->withUrlParameters(['query' => []])
            ->withUrlParameters($this->feedRequestBaseUrlParameters($requestUrl))
            ->withUrlParameters($this->feedRequestPathUrlParameters($requestUrl))
            ->withAttributes($this->feedRequestAttributes($requestUrl, $originalUrl, $attempt, $maxRetries))
            ->beforeSending(function (HttpClientRequest $request): void {
                $context = $request->attributes();
                $context['url'] = $request->url();

                $this->logger()->debug('RSS request sending.', $context);
            });
    }

    /**
     * @return array<string, int|string>
     */
    private function feedRequestAttributes(string $requestUrl, string $originalUrl, int $attempt, int $maxRetries): array
    {
        return [
            'request_type' => 'rss_feed',
            'request_url' => $requestUrl,
            'original_url' => $originalUrl,
            'attempt' => $attempt,
            'max_retries' => $maxRetries,
        ];
    }

    private function newFeedRequest(string $requestUrl, string $originalUrl, int $attempt, int $maxRetries): PendingRequest
    {
        return $this->configureFeedRequest(Http::withHeaders([]), $requestUrl, $originalUrl, $attempt, $maxRetries);
    }

    private function logFeedResponse(
        Response $response,
        string $requestUrl,
        string $originalUrl,
        int $attempt,
        int $maxRetries,
        array $extraContext = [],
    ): void {
        $context = array_merge(
            $this->feedRequestAttributes($requestUrl, $originalUrl, $attempt, $maxRetries),
            $extraContext,
            ['status' => $response->status()],
        );

        $location = $response->redirectLocation();

        if ($location !== null) {
            $context['location'] = $location;
        }

        $this->logger()->debug('RSS response received.', $context);
    }

    private function resolveFeedResponse(
        string $requestUrl,
        string $originalUrl,
        int $attempt,
        int $maxRetries,
        Response|ConnectionException|null $firstAttemptResult = null,
    ): Response {
        if ($attempt === 1 && $firstAttemptResult !== null) {
            if ($firstAttemptResult instanceof ConnectionException) {
                throw $firstAttemptResult;
            }

            $this->logFeedResponse($firstAttemptResult, $requestUrl, $originalUrl, $attempt, $maxRetries, [
                'batched' => true,
            ]);

            return $firstAttemptResult;
        }

        return $this->newFeedRequest($requestUrl, $originalUrl, $attempt, $maxRetries)
            ->get('{+endpoint}{?query*}')
            ->tap(function (Response $response) use ($attempt, $requestUrl, $originalUrl, $maxRetries): void {
                $this->logFeedResponse($response, $requestUrl, $originalUrl, $attempt, $maxRetries);
            });
    }

    /**
     * @return array{endpoint: string}
     */
    private function feedRequestBaseUrlParameters(string $requestUrl): array
    {
        $parts = parse_url($requestUrl);
        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'https';
        $host = is_string($parts['host'] ?? null) ? $parts['host'] : '';
        $user = is_string($parts['user'] ?? null) ? $parts['user'] : '';
        $pass = is_string($parts['pass'] ?? null) ? $parts['pass'] : '';
        $port = is_int($parts['port'] ?? null) ? $parts['port'] : null;
        $authority = $host;

        if ($user !== '') {
            $authority = $user.($pass !== '' ? ':'.$pass : '').'@'.$authority;
        }

        if ($authority !== '' && $port !== null) {
            $authority .= ':'.$port;
        }

        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '';

        return ['endpoint' => "{$scheme}://{$authority}{$path}"];
    }

    /**
     * @return array{query: array<string, string>}
     */
    private function feedRequestPathUrlParameters(string $requestUrl): array
    {
        $parts = parse_url($requestUrl);
        $query = [];

        if (is_string($parts['query'] ?? null) && $parts['query'] !== '') {
            parse_str($parts['query'], $query);
        }

        return ['query' => $query];
    }

    /**
     * @throws \RuntimeException
     */
    private function fetchWithRetry(
        string $url,
        int $maxRetries = 3,
        Response|ConnectionException|null $firstAttemptResult = null,
    ): string {
        $currentUrl = $url;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $requestUrl = $currentUrl;

            try {
                $response = $this->resolveFeedResponse($requestUrl, $url, $attempt, $maxRetries, $firstAttemptResult);
                $firstAttemptResult = null;

                $status = $response->status();

                if ($status === 200) {
                    return $response->body();
                }

                if ($response->isRedirectStatus()) {
                    $location = $response->redirectLocation();

                    if ($location !== null) {
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
                $firstAttemptResult = null;
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

                return Utf8Normalizer::normalizeString($converted) ?? '';
            }
        }

        return Utf8Normalizer::normalizeString($body) ?? '';
    }

    private function sanitizeText(string $text): string
    {
        $text = Utf8Normalizer::normalizeString($text) ?? '';
        $text = preg_replace('/<(script|iframe|style|object|embed|form)\b[^>]*>.*?<\/\1>/is', '', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim(Utf8Normalizer::normalizeString($text) ?? '');
    }

    private function sanitizeHtml(string $html): string
    {
        $html = Utf8Normalizer::normalizeString($html) ?? '';
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(Utf8Normalizer::normalizeString((string) preg_replace('/<(script|iframe|style|object|embed|form|noscript)\b[^>]*>.*?<\/\1>/is', '', $html)) ?? '');
    }

    private function fetchSourcePage(string $url, ?int $maxRetries = null): string
    {
        $maxRetries ??= (int) config('rss.page_parser.max_retries', 2);
        $currentUrl = $url;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout((int) config('rss.page_parser.timeout', config('rss.parser.timeout', 30)))
                    ->connectTimeout((int) config('rss.page_parser.connect_timeout', config('rss.parser.connect_timeout', 10)))
                    ->withoutVerifying()
                    ->withoutRedirecting()
                    ->withHeaders([
                        'User-Agent' => (string) config('rss.parser.user_agent'),
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Encoding' => 'gzip, deflate',
                        'Cache-Control' => 'no-cache',
                    ])
                    ->get($currentUrl);

                $status = $response->status();

                if ($status === 200) {
                    return $this->detectEncoding($response->body());
                }

                if ($response->isRedirectStatus() && $response->redirectLocation() !== null) {
                    $currentUrl = $this->absolutizeUrl($currentUrl, $response->redirectLocation());

                    continue;
                }

                if (in_array($status, [404, 410], true)) {
                    throw new RuntimeException("Source page gone: HTTP {$status}");
                }

                if ($attempt === $maxRetries) {
                    break;
                }

                sleep($status === 429 ? 2 ** $attempt : 1);
            } catch (ConnectionException $exception) {
                if ($attempt === $maxRetries) {
                    throw new RuntimeException("Source page unreachable after {$maxRetries} attempts: {$url}", previous: $exception);
                }

                sleep(1);
            }
        }

        throw new RuntimeException("Source page unreachable after {$maxRetries} attempts: {$url}");
    }

    private function parseHtml(string $html): DOMXPath
    {
        libxml_use_internal_errors(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);

        if ($loaded === false) {
            $errors = libxml_get_errors();
            $message = isset($errors[0]) ? trim($errors[0]->message) : 'Invalid HTML document.';
            libxml_clear_errors();

            throw new RuntimeException($message);
        }

        libxml_clear_errors();

        return new DOMXPath($document);
    }

    /**
     * @return array<string, string>
     */
    private function extractMetaTags(DOMXPath $xpath): array
    {
        $meta = [];
        $nodes = $xpath->query('//meta[@content]');

        if ($nodes === false) {
            return $meta;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $key = trim((string) ($node->getAttribute('property') ?: $node->getAttribute('name')));
            $value = trim((string) $node->getAttribute('content'));

            if ($key === '' || $value === '') {
                continue;
            }

            $meta[Str::lower($key)] = $value;
        }

        return $meta;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractStructuredDataItems(DOMXPath $xpath): array
    {
        $items = [];
        $nodes = $xpath->query('//script[@type="application/ld+json"]');

        if ($nodes === false) {
            return $items;
        }

        foreach ($nodes as $node) {
            $payload = trim($node->textContent);

            if ($payload === '') {
                continue;
            }

            try {
                $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                continue;
            }

            $items = [...$items, ...$this->flattenStructuredDataItems($decoded)];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function flattenStructuredDataItems(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return collect($payload)
                ->flatMap(fn (mixed $item): array => $this->flattenStructuredDataItems($item))
                ->values()
                ->all();
        }

        $items = [$payload];

        if (isset($payload['@graph'])) {
            $items = [
                ...$items,
                ...$this->flattenStructuredDataItems($payload['@graph']),
            ];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function extractPrimaryArticleStructuredData(array $items): ?array
    {
        foreach ($items as $item) {
            $types = Arr::wrap($item['@type'] ?? []);
            $normalizedTypes = collect($types)
                ->map(fn (mixed $type): string => Str::lower((string) $type))
                ->values()
                ->all();

            if (array_intersect($normalizedTypes, ['newsarticle', 'article', 'reportage'])) {
                return $item;
            }
        }

        return null;
    }

    private function firstNonEmptyString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim(Utf8Normalizer::normalizeString($value) ?? '');

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private function absolutizeUrl(string $baseUrl, string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return $baseUrl;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        $parts = parse_url($baseUrl);
        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'https';
        $host = is_string($parts['host'] ?? null) ? $parts['host'] : '';
        $port = is_int($parts['port'] ?? null) ? ':'.$parts['port'] : '';

        if (str_starts_with($url, '//')) {
            return "{$scheme}:{$url}";
        }

        if (str_starts_with($url, '/')) {
            return "{$scheme}://{$host}{$port}{$url}";
        }

        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '/';
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');
        $directory = $directory === '.' ? '' : $directory;

        return "{$scheme}://{$host}{$port}{$directory}/{$url}";
    }

    private function extractCanonicalUrl(DOMXPath $xpath, string $url): string
    {
        $nodes = $xpath->query('//link[@rel="canonical"]');

        if ($nodes !== false && $nodes->length > 0 && $nodes->item(0) instanceof DOMElement) {
            $href = trim($nodes->item(0)->getAttribute('href'));

            if ($href !== '') {
                return $this->absolutizeUrl($url, $href);
            }
        }

        return $url;
    }

    private function extractDocumentTitle(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//title');

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $title = $this->sanitizeText($nodes->item(0)?->textContent ?? '');
        $title = preg_replace('/\s+\|\s+.+$/u', '', $title) ?? $title;

        return $title !== '' ? trim($title) : null;
    }

    private function extractImageCaption(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//figcaption');

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $caption = $this->sanitizeText($nodes->item(0)?->textContent ?? '');

        return $caption !== '' ? $caption : null;
    }

    private function nodeHtml(DOMNode $node): string
    {
        $document = $node->ownerDocument;

        if (! $document instanceof DOMDocument) {
            return '';
        }

        return trim((string) $document->saveHTML($node));
    }

    private function extractArticleBodyHtml(DOMXPath $xpath): string
    {
        $queries = [
            '//*[@article-item-type="html"]/*',
            '//*[contains(@class, "article__body")]/*',
            '//article//*[self::p or self::h2 or self::h3 or self::blockquote or self::ul or self::ol]',
            '//main//*[self::p or self::h2 or self::h3 or self::blockquote or self::ul or self::ol]',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);

            if ($nodes === false || $nodes->length === 0) {
                continue;
            }

            $html = collect(iterator_to_array($nodes))
                ->map(fn (DOMNode $node): string => $this->nodeHtml($node))
                ->filter()
                ->implode("\n");

            $html = $this->sanitizeHtml($html);

            if ($html !== '') {
                return $html;
            }
        }

        return '';
    }

    private function textToHtmlParagraphs(string $text): string
    {
        $text = trim((string) preg_replace("/\r\n?/", "\n", $text));

        if ($text === '') {
            return '';
        }

        $paragraphs = preg_split("/\n{2,}/u", $text) ?: [$text];

        return collect($paragraphs)
            ->map(function (string $paragraph): string {
                $paragraph = $this->sanitizeText($paragraph);

                if ($paragraph === '') {
                    return '';
                }

                return '<p>'.htmlspecialchars($paragraph, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
            })
            ->filter()
            ->implode("\n");
    }

    private function extractStructuredDataPersonName(mixed $value): ?string
    {
        if (is_string($value)) {
            return $this->firstNonEmptyString($value);
        }

        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $item) {
                $name = $this->extractStructuredDataPersonName($item);

                if ($name !== null) {
                    return $name;
                }
            }

            return null;
        }

        if (is_array($value)) {
            return $this->firstNonEmptyString((string) ($value['name'] ?? ''));
        }

        return null;
    }

    private function extractStructuredDataPersonUrl(mixed $value): ?string
    {
        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $item) {
                $url = $this->extractStructuredDataPersonUrl($item);

                if ($url !== null) {
                    return $url;
                }
            }

            return null;
        }

        if (is_array($value)) {
            $candidate = trim((string) ($value['url'] ?? ''));

            return filter_var($candidate, FILTER_VALIDATE_URL) ? $candidate : null;
        }

        return null;
    }

    private function extractStructuredDataImageUrl(mixed $value): ?string
    {
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
        }

        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $item) {
                $url = $this->extractStructuredDataImageUrl($item);

                if ($url !== null) {
                    return $url;
                }
            }

            return null;
        }

        if (is_array($value)) {
            return $this->extractStructuredDataImageUrl($value['url'] ?? null);
        }

        return null;
    }

    private function extractScriptContentById(DOMXPath $xpath, string $scriptId): ?string
    {
        $nodes = $xpath->query(sprintf('//script[@id="%s"]', $scriptId));

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $script = trim((string) $nodes->item(0)?->textContent);

        return $script !== '' ? $script : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractMailPreloadedArticleState(DOMXPath $xpath): ?array
    {
        $script = $this->extractScriptContentById($xpath, 'preload_article');

        if ($script !== null && preg_match('/window\.__PRELOADED_STATE__ARTICLE\s*=\s*(\{.*\})\s*;?\s*$/su', $script, $matches) === 1) {
            try {
                $decoded = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $decoded = null;
            }

            $article = $decoded['article'] ?? null;

            if (is_array($article)) {
                return $article;
            }
        }

        $nodes = $xpath->query('//script[contains(text(), "__PRELOADED_STATE__PAGE")]');

        if ($nodes === false) {
            return null;
        }

        foreach ($nodes as $node) {
            $script = trim((string) $node->textContent);

            if ($script === '') {
                continue;
            }

            if (preg_match('/window\.__PRELOADED_STATE__PAGE\s*=\s*(\{.*\})\s*;?\s*$/su', $script, $matches) !== 1) {
                continue;
            }

            try {
                $decoded = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                continue;
            }

            $article = $decoded['article'] ?? null;

            if (is_array($article)) {
                return $article;
            }
        }

        return null;
    }

    private function normalizeSourceName(?string $sourceName): ?string
    {
        return Article::sanitizeSourceName($sourceName);
    }

    private function extractMailPreloadedImageUrl(array $article): ?string
    {
        foreach (Arr::wrap($article['content'] ?? []) as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'picture') {
                continue;
            }

            $url = $this->firstNonEmptyString(
                is_string($block['attrs']['images']['large']['url'] ?? null) ? $block['attrs']['images']['large']['url'] : null,
                is_string($block['attrs']['images']['base']['url'] ?? null) ? $block['attrs']['images']['base']['url'] : null,
                is_string($block['data']['large']['url'] ?? null) ? $block['data']['large']['url'] : null,
                is_string($block['data']['base']['url'] ?? null) ? $block['data']['base']['url'] : null,
            );

            if ($url !== null && filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        return null;
    }

    private function extractMailPreloadedImageCaption(array $article, ?string $title): ?string
    {
        foreach (Arr::wrap($article['content'] ?? []) as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'picture') {
                continue;
            }

            $caption = $this->sanitizeText((string) ($block['attrs']['description'] ?? ''));

            if ($caption !== '' && $caption !== $title) {
                return $caption;
            }
        }

        return $this->normalizeSourceName((string) ($article['source']['title'] ?? ''));
    }

    private function extractMailPreloadedArticleBodyHtml(array $article): string
    {
        $descriptionHtml = $this->sanitizeHtml((string) ($article['description'] ?? ''));
        $bodyHtml = collect(Arr::wrap($article['content'] ?? []))
            ->filter(fn (mixed $block): bool => is_array($block) && ($block['type'] ?? null) === 'html')
            ->map(fn (array $block): string => $this->sanitizeHtml((string) ($block['html'] ?? '')))
            ->filter()
            ->implode("\n");

        return collect([$descriptionHtml, $bodyHtml])
            ->filter()
            ->implode("\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function extractMailPreloadedArticleData(array $article): array
    {
        $title = $this->firstNonEmptyString((string) ($article['title'] ?? ''));
        $descriptionHtml = $this->sanitizeHtml((string) ($article['description'] ?? ''));
        $description = $this->sanitizeText($descriptionHtml);
        $bodyHtml = $this->extractMailPreloadedArticleBodyHtml($article);
        $author = collect(Arr::wrap($article['authors'] ?? []))
            ->map(function (mixed $author): ?string {
                if (! is_array($author)) {
                    return null;
                }

                return $this->firstNonEmptyString(
                    is_string($author['name'] ?? null) ? $author['name'] : null,
                    is_string($author['title'] ?? null) ? $author['title'] : null,
                );
            })
            ->filter()
            ->first();
        $authorUrl = collect(Arr::wrap($article['authors'] ?? []))
            ->map(function (mixed $author): ?string {
                if (! is_array($author)) {
                    return null;
                }

                $url = trim((string) ($author['href'] ?? $author['url'] ?? ''));

                return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
            })
            ->filter()
            ->first();

        return [
            'title' => $title,
            'short_description' => $description !== ''
                ? $this->makeShortDescription($description, (int) config('rss.article.short_description_length', 300))
                : null,
            'full_description' => $bodyHtml !== '' ? $bodyHtml : null,
            'image_url' => $this->extractMailPreloadedImageUrl($article),
            'image_caption' => $this->extractMailPreloadedImageCaption($article, $title),
            'author' => $author,
            'author_url' => $authorUrl,
            'source_name' => $this->normalizeSourceName((string) ($article['source']['title'] ?? '')),
            'meta_title' => $title,
            'meta_description' => $description !== '' ? $description : null,
            'canonical_url' => $this->firstNonEmptyString((string) ($article['href'] ?? '')),
            'published_at' => $this->firstNonEmptyString((string) ($article['published']['rfc3339'] ?? '')),
            'last_edited_at' => $this->firstNonEmptyString((string) ($article['modified']['rfc3339'] ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractSourceArticleData(string $url, array $settings = []): array
    {
        $html = $this->fetchSourcePage($url);
        $xpath = $this->parseHtml($html);
        $meta = $this->extractMetaTags($xpath);
        $preloadedArticle = $this->extractMailPreloadedArticleState($xpath);
        $structuredDataItems = $this->extractStructuredDataItems($xpath);
        $articleStructuredData = $this->extractPrimaryArticleStructuredData($structuredDataItems);
        $structuredData = $articleStructuredData ?? [];
        $sourceParserData = [];

        if ($this->sourceArticleParser !== null) {
            try {
                $sourceParserData = $this->sourceArticleParser->parseHtml($html, $url, $settings);
            } catch (Throwable $exception) {
                $this->logger()->warning("Fallback source parser failed for {$url}: {$exception->getMessage()}");
            }
        }

        $preloadedArticleData = is_array($preloadedArticle) ? $this->extractMailPreloadedArticleData($preloadedArticle) : [];
        $title = $this->firstNonEmptyString(
            is_string($preloadedArticleData['title'] ?? null) ? $preloadedArticleData['title'] : null,
            $structuredData['headline'] ?? null,
            $meta['og:title'] ?? null,
            $meta['twitter:title'] ?? null,
            $meta['yandex_recommendations_title'] ?? null,
            $this->extractDocumentTitle($xpath),
        );
        $description = $this->firstNonEmptyString(
            is_string($preloadedArticleData['meta_description'] ?? null) ? $preloadedArticleData['meta_description'] : null,
            $structuredData['description'] ?? null,
            $meta['widget:description'] ?? null,
            $meta['og:description'] ?? null,
            $meta['description'] ?? null,
            $meta['twitter:description'] ?? null,
        );
        $bodyHtml = $this->firstNonEmptyString(
            is_string($preloadedArticleData['full_description'] ?? null) ? $preloadedArticleData['full_description'] : null,
        ) ?? '';
        $bodyHtml = $bodyHtml !== '' ? $bodyHtml : $this->extractArticleBodyHtml($xpath);
        $bodyHtml = $bodyHtml !== ''
            ? $bodyHtml
            : $this->textToHtmlParagraphs((string) ($structuredData['articleBody'] ?? ''));
        $imageUrl = $this->firstNonEmptyString(
            is_string($preloadedArticleData['image_url'] ?? null) ? $preloadedArticleData['image_url'] : null,
            $this->extractStructuredDataImageUrl($structuredData['image'] ?? null),
            $meta['og:image'] ?? null,
            $meta['twitter:image'] ?? null,
            $meta['yandex_recommendations_image'] ?? null,
        );
        $imageUrl = is_string($imageUrl) ? $this->absolutizeUrl($url, $imageUrl) : null;
        $author = $this->firstNonEmptyString(
            is_string($preloadedArticleData['author'] ?? null) ? $preloadedArticleData['author'] : null,
            $this->extractStructuredDataPersonName($structuredData['author'] ?? null),
            $meta['author'] ?? null,
        );
        $authorUrl = $this->firstNonEmptyString(
            is_string($preloadedArticleData['author_url'] ?? null) ? $preloadedArticleData['author_url'] : null,
            $this->extractStructuredDataPersonUrl($structuredData['author'] ?? null),
        );
        $publishedAt = $this->firstNonEmptyString(
            is_string($preloadedArticleData['published_at'] ?? null) ? $preloadedArticleData['published_at'] : null,
            $structuredData['datePublished'] ?? null,
            $meta['article:published_time'] ?? null,
        );
        $sourceName = $this->normalizeSourceName($this->firstNonEmptyString(
            $meta['marker:source'] ?? null,
            is_string($preloadedArticleData['source_name'] ?? null) ? $preloadedArticleData['source_name'] : null,
            $this->extractStructuredDataPersonName($structuredData['publisher'] ?? null),
            $meta['og:site_name'] ?? null,
        ));
        $publishedAtCarbon = null;
        $lastEditedAtCarbon = null;

        if ($publishedAt !== null) {
            try {
                $publishedAtCarbon = Carbon::parse($publishedAt);
            } catch (Throwable) {
                $publishedAtCarbon = null;
            }
        }

        if (is_string($preloadedArticleData['last_edited_at'] ?? null)) {
            try {
                $lastEditedAtCarbon = Carbon::parse($preloadedArticleData['last_edited_at']);
            } catch (Throwable) {
                $lastEditedAtCarbon = null;
            }
        }

        $sourceData = [
            'title' => $title,
            'short_description' => $description !== null
                ? $this->makeShortDescription($description, (int) config('rss.article.short_description_length', 300))
                : null,
            'full_description' => $bodyHtml !== '' ? $bodyHtml : null,
            'image_url' => $imageUrl,
            'image_caption' => $this->firstNonEmptyString(
                is_string($preloadedArticleData['image_caption'] ?? null) ? $preloadedArticleData['image_caption'] : null,
                $this->extractImageCaption($xpath),
            ),
            'author' => $author,
            'author_url' => $authorUrl,
            'source_name' => $sourceName,
            'meta_title' => $title,
            'meta_description' => $description,
            'canonical_url' => $this->firstNonEmptyString(
                is_string($preloadedArticleData['canonical_url'] ?? null) ? $preloadedArticleData['canonical_url'] : null,
                $this->extractCanonicalUrl($xpath, $url),
            ),
            'structured_data' => $articleStructuredData,
            'published_at' => $publishedAtCarbon,
            'last_edited_at' => $lastEditedAtCarbon,
        ];

        return $this->mergeSourceArticleData($sourceData, $sourceParserData, $settings);
    }

    /**
     * @param  array<string, mixed>  $articleData
     * @return array<string, mixed>
     */
    private function enrichArticleDataFromSource(array $articleData, array $settings = []): array
    {
        $sourceUrl = is_string($articleData['source_url'] ?? null) ? trim($articleData['source_url']) : '';

        if ($sourceUrl !== '' && $this->isSourcePageEnrichmentEnabled($settings)) {
            try {
                $sourceData = $this->extractSourceArticleData($sourceUrl, $settings);

                foreach ($sourceData as $key => $value) {
                    if ($value === null || $this->isBlankArticleAttribute($value)) {
                        continue;
                    }

                    $articleData[$key] = $value;
                }
            } catch (Throwable $exception) {
                $this->logger()->warning("Source page enrichment failed for {$sourceUrl}: {$exception->getMessage()}");
            }
        }

        if (is_string($articleData['title'] ?? null) && $articleData['title'] !== '') {
            $articleData['slug'] = $this->generateUniqueSlug($articleData['title']);
        }

        if (($articleData['short_description'] ?? '') === '' && ($articleData['full_description'] ?? '') !== '') {
            $articleData['short_description'] = $this->makeShortDescription(
                (string) $articleData['full_description'],
                (int) config('rss.article.short_description_length', 300),
            );
        }

        if (is_string($articleData['canonical_url'] ?? null) && $articleData['canonical_url'] !== '') {
            $articleData['source_url'] = $articleData['canonical_url'];
        }

        if (($articleData['full_description'] ?? '') !== '') {
            $articleData['rss_content'] = $this->sanitizeText((string) $articleData['full_description']);
        }

        $articleData['importance'] = $this->guessImportance(
            (string) ($articleData['title'] ?? ''),
            (string) ($articleData['short_description'] ?? ''),
        );

        $articleData['reading_time'] = $this->calculateReadingTime(
            (string) ($articleData['full_description'] ?? $articleData['rss_content'] ?? ''),
        );

        return $articleData;
    }

    /**
     * @param  array<string, mixed>  $primary
     * @param  array<string, mixed>  $secondary
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function mergeSourceArticleData(array $primary, array $secondary, array $settings = []): array
    {
        if ($secondary === []) {
            return $primary;
        }

        $secondaryDescription = $this->firstNonEmptyString(
            is_string($secondary['subtitle'] ?? null) ? $secondary['subtitle'] : null,
            is_string($secondary['short_description'] ?? null) ? $secondary['short_description'] : null,
            is_string($secondary['meta_description'] ?? null) ? $secondary['meta_description'] : null,
        );
        $preferSecondaryTitle = $this->hasSourcePageSelectorOverride($settings, 'source_page_title_selector');
        $preferSecondarySubtitle = $this->hasSourcePageSelectorOverride($settings, 'source_page_subtitle_selector');
        $preferSecondaryBody = $this->hasSourcePageSelectorOverride($settings, 'source_page_article_selector');
        $preferSecondaryAuthor = $this->hasSourcePageSelectorOverride($settings, 'source_page_author_selector');
        $preferSecondaryImage = $this->hasSourcePageSelectorOverride($settings, 'source_page_image_selector');

        return [
            'title' => $this->preferredSourceValue(
                $primary['title'] ?? null,
                $secondary['title'] ?? null,
                $preferSecondaryTitle,
            ),
            'short_description' => $this->preferredSourceValue(
                $primary['short_description'] ?? null,
                $secondaryDescription,
                $preferSecondarySubtitle,
            ),
            'full_description' => $this->preferredSourceValue(
                $primary['full_description'] ?? null,
                $secondary['full_description'] ?? null,
                $preferSecondaryBody,
            ),
            'image_url' => $this->preferredSourceValue(
                $primary['image_url'] ?? null,
                $secondary['image_url'] ?? null,
                $preferSecondaryImage,
            ),
            'image_caption' => $this->preferredSourceValue(
                $primary['image_caption'] ?? null,
                $secondary['image_caption'] ?? null,
                $preferSecondaryImage,
            ),
            'author' => $this->preferredSourceValue(
                $primary['author'] ?? null,
                $secondary['author'] ?? null,
                $preferSecondaryAuthor,
            ),
            'author_url' => $this->preferredSourceValue(
                $primary['author_url'] ?? null,
                $secondary['author_url'] ?? null,
                $preferSecondaryAuthor,
            ),
            'source_name' => $this->preferredSourceValue(
                $primary['source_name'] ?? null,
                $secondary['source_name'] ?? null,
                false,
            ),
            'meta_title' => $this->preferredSourceValue(
                $primary['meta_title'] ?? null,
                $secondary['meta_title'] ?? null,
                $preferSecondaryTitle,
            ),
            'meta_description' => $this->preferredSourceValue(
                $primary['meta_description'] ?? null,
                $secondaryDescription,
                $preferSecondarySubtitle,
            ),
            'canonical_url' => $this->preferredSourceValue(
                $primary['canonical_url'] ?? null,
                $secondary['canonical_url'] ?? null,
                false,
            ),
            'structured_data' => $this->preferredSourceValue(
                $primary['structured_data'] ?? null,
                $secondary['structured_data'] ?? null,
                false,
            ),
            'published_at' => $this->preferredSourceValue(
                $primary['published_at'] ?? null,
                $secondary['published_at'] ?? null,
                false,
            ),
            'last_edited_at' => $primary['last_edited_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function hasSourcePageSelectorOverride(array $settings, string $key): bool
    {
        $value = $settings[$key] ?? null;

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return collect($value)
                ->contains(fn (mixed $item): bool => is_string($item) && trim($item) !== '');
        }

        return false;
    }

    private function preferredSourceValue(mixed $primary, mixed $secondary, bool $preferSecondary): mixed
    {
        $primaryIsBlank = $this->isBlankArticleAttribute($primary);
        $secondaryIsBlank = $this->isBlankArticleAttribute($secondary);

        if ($preferSecondary && ! $secondaryIsBlank) {
            return $secondary;
        }

        if (! $primaryIsBlank) {
            return $primary;
        }

        return $secondaryIsBlank ? null : $secondary;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function isSourcePageEnrichmentEnabled(array $settings): bool
    {
        $value = $settings['source_page_enabled']
            ?? config('rss.source_pages.enabled', config('rss.page_parser.enabled', true));

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(Str::lower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    public function needsSourceEnrichment(Article $article): bool
    {
        if (! is_string($article->source_url) || trim($article->source_url) === '') {
            return false;
        }

        return $this->isBlankArticleAttribute($article->full_description)
            || $this->isBlankArticleAttribute($article->image_url)
            || $this->isBlankArticleAttribute($article->canonical_url)
            || $this->isBlankArticleAttribute($article->meta_description)
            || $this->isBlankArticleAttribute($article->structured_data)
            || $this->isBlankArticleAttribute($article->author)
            || $this->isBlankArticleAttribute($article->source_name);
    }

    public function enrichExistingArticle(Article $article, bool $force = false): bool
    {
        $sourceUrl = is_string($article->source_url) ? trim($article->source_url) : '';

        if ($sourceUrl === '' || (! $force && ! $this->needsSourceEnrichment($article))) {
            return false;
        }

        /** @var array<string, mixed> $settings */
        $settings = is_array($article->rssFeed?->extra_settings) ? $article->rssFeed->extra_settings : [];

        if (! $this->isSourcePageEnrichmentEnabled($settings)) {
            return false;
        }

        $sourceData = [];

        try {
            $sourceData = $this->extractSourceArticleData($sourceUrl, $settings);
        } catch (Throwable $exception) {
            $this->logger()->warning("Stored article enrichment failed for {$sourceUrl}: {$exception->getMessage()}");
        }

        $updates = [];

        foreach ($sourceData as $key => $value) {
            if ($value === null || $this->isBlankArticleAttribute($value)) {
                continue;
            }

            if (! $force && ! $this->shouldReplaceExistingArticleAttribute($article, $key)) {
                continue;
            }

            $updates[$key] = $value;
        }

        if (isset($updates['canonical_url']) && is_string($updates['canonical_url']) && $updates['canonical_url'] !== '') {
            if ($force || $this->isBlankArticleAttribute($article->source_url) || $this->isBlankArticleAttribute($article->canonical_url)) {
                $updates['source_url'] = $updates['canonical_url'];
            }
        }

        $updates = $this->addStoredArticleFallbackUpdates($article, $updates);

        if (isset($updates['full_description']) && is_string($updates['full_description']) && $updates['full_description'] !== '') {
            $updates['rss_content'] = $this->sanitizeText($updates['full_description']);
        }

        if ($updates === []) {
            return false;
        }

        $sourceEditedAt = $updates['last_edited_at'] ?? null;
        unset($updates['last_edited_at']);

        $title = (string) ($updates['title'] ?? $article->title ?? '');
        $description = (string) ($updates['short_description'] ?? $article->short_description ?? '');
        $content = (string) ($updates['full_description'] ?? $updates['rss_content'] ?? $article->full_description ?? $article->rss_content ?? '');

        if ($title !== '' || $description !== '') {
            $updates['importance'] = $this->guessImportance($title, $description);
        }

        if ($content !== '') {
            $updates['reading_time'] = $this->calculateReadingTime($content);
        }

        if ($updates !== []) {
            $article->forceFill($updates)->save();
        }

        if ($sourceEditedAt instanceof Carbon) {
            $article->forceFill([
                'last_edited_at' => $sourceEditedAt->copy()->setTimezone((string) config('app.timezone', 'UTC')),
            ])->saveQuietly();
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function addStoredArticleFallbackUpdates(Article $article, array $updates): array
    {
        $fallbackData = $this->buildStoredArticleFallbackData($article, $updates);

        foreach (['short_description', 'meta_title', 'meta_description', 'canonical_url', 'source_url', 'source_name'] as $key) {
            if (array_key_exists($key, $updates)) {
                continue;
            }

            $value = $fallbackData[$key] ?? null;

            if ($value === null || $this->isBlankArticleAttribute($value)) {
                continue;
            }

            if (! $this->shouldReplaceExistingArticleAttribute($article, $key)) {
                continue;
            }

            if ($article->getRawOriginal($key) === $value) {
                continue;
            }

            $updates[$key] = $value;
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function buildStoredArticleFallbackData(Article $article, array $updates): array
    {
        $articleData = [
            'title' => $updates['title'] ?? $article->title,
            'short_description' => $updates['short_description'] ?? $article->short_description,
            'full_description' => $updates['full_description'] ?? $article->full_description,
            'rss_content' => $updates['rss_content'] ?? $article->rss_content,
            'source_url' => $updates['source_url'] ?? $article->source_url,
            'source_name' => $updates['source_name'] ?? $article->source_name,
            'meta_title' => $updates['meta_title'] ?? $article->getRawOriginal('meta_title'),
            'meta_description' => $updates['meta_description'] ?? $article->getRawOriginal('meta_description'),
            'canonical_url' => $updates['canonical_url'] ?? $article->canonical_url,
        ];

        if ($this->isBlankArticleAttribute($articleData['short_description']) && ! $this->isBlankArticleAttribute($articleData['full_description'])) {
            $articleData['short_description'] = $this->makeShortDescription(
                (string) $articleData['full_description'],
                (int) config('rss.article.short_description_length', 300),
            );
        }

        if ($this->isBlankArticleAttribute($articleData['meta_title']) && ! $this->isBlankArticleAttribute($articleData['title'])) {
            $articleData['meta_title'] = trim((string) $articleData['title']);
        }

        if ($this->isBlankArticleAttribute($articleData['meta_description'])) {
            $articleData['meta_description'] = $this->firstNonEmptyString(
                is_string($articleData['short_description'] ?? null)
                    ? $this->makeShortDescription(
                        $articleData['short_description'],
                        (int) config('rss.article.short_description_length', 300),
                    )
                    : null,
                is_string($articleData['full_description'] ?? null)
                    ? $this->makeShortDescription(
                        $articleData['full_description'],
                        (int) config('rss.article.short_description_length', 300),
                    )
                    : null,
                is_string($articleData['rss_content'] ?? null)
                    ? $this->makeShortDescription(
                        $articleData['rss_content'],
                        (int) config('rss.article.short_description_length', 300),
                    )
                    : null,
            );
        }

        if ($this->isBlankArticleAttribute($articleData['canonical_url']) && ! $this->isBlankArticleAttribute($articleData['source_url'])) {
            $articleData['canonical_url'] = trim((string) $articleData['source_url']);
        }

        if ($this->isBlankArticleAttribute($articleData['source_url']) && ! $this->isBlankArticleAttribute($articleData['canonical_url'])) {
            $articleData['source_url'] = trim((string) $articleData['canonical_url']);
        }

        if ($this->isBlankArticleAttribute($articleData['source_name'])) {
            $articleData['source_name'] = $this->inferSourceNameFromUrl(
                is_string($articleData['canonical_url'] ?? null) && trim($articleData['canonical_url']) !== ''
                    ? $articleData['canonical_url']
                    : (is_string($articleData['source_url'] ?? null) ? $articleData['source_url'] : null),
            );
        }

        return $articleData;
    }

    private function shouldReplaceExistingArticleAttribute(Article $article, string $key): bool
    {
        return match ($key) {
            'title' => $this->isBlankArticleAttribute($article->title)
                || $this->isBlankArticleAttribute($article->full_description),
            'short_description' => $this->isBlankArticleAttribute($article->short_description)
                || $this->isBlankArticleAttribute($article->full_description),
            'meta_title', 'meta_description' => $this->isBlankArticleAttribute($article->getRawOriginal($key))
                || $this->isBlankArticleAttribute($article->full_description),
            'author' => $this->isBlankArticleAttribute($article->author)
                || $article->author === config('rss.article.default_author')
                || $article->author === $article->source_name,
            'source_name' => $this->isBlankArticleAttribute($article->source_name)
                || $article->source_name === config('rss.source_name'),
            default => $this->isBlankArticleAttribute($article->getAttribute($key)),
        };
    }

    private function isBlankArticleAttribute(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return $value === null;
    }

    private function inferSourceNameFromUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $host = parse_url(trim($url), PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        $host = preg_replace('/^www\./i', '', trim($host)) ?? trim($host);

        return $this->normalizeSourceName(Str::lower($host));
    }

    /**
     * @throws \RuntimeException
     */
    public function fetchFeedXml(string $url, Response|ConnectionException|null $firstAttemptResult = null): SimpleXMLElement
    {
        $body = $this->fetchWithRetry($url, firstAttemptResult: $firstAttemptResult);
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
        $title = preg_replace('/\s+(?:[-—|]\s*(?:Mail\.ru|[^|—-]*\bMail\b))$/u', '', $title) ?? $title;

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

        return trim(Utf8Normalizer::normalizeString($guid) ?? '');
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
        return $this->findDuplicateArticle($guid, $url) instanceof Article;
    }

    private function findDuplicateArticle(string $guid, string $url): ?Article
    {
        if ($guid === '' && $url === '') {
            return null;
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
            ->latest('id')
            ->first();
    }

    private function refreshDuplicateArticle(Article $article, SimpleXMLElement $item, RssFeed $feed): bool
    {
        if ($article->trashed()) {
            return false;
        }

        $updates = [];
        $itemCategories = $this->extractCategories($item);
        $resolvedCategory = $this->resolveItemCategory($feed, $itemCategories);
        $resolvedCategoryId = $resolvedCategory?->id ?? $feed->category_id;
        $subCategory = $this->resolveItemSubCategory($feed, $itemCategories, $resolvedCategory);
        $link = $this->extractLink($item);
        $guid = $this->extractGuid($item);
        $description = $this->extractDescription($item);
        $imageUrl = $this->extractImage($item);
        $author = $this->extractAuthor($item);
        $sourceName = $this->normalizeSourceName($feed->source_name ?: (string) config('rss.source_name', ''));

        if ($this->isBlankArticleAttribute($article->source_url) && $link !== '') {
            $updates['source_url'] = $link;
        }

        if ($this->isBlankArticleAttribute($article->source_guid) && $guid !== '') {
            $updates['source_guid'] = $guid;
        }

        if ($this->isBlankArticleAttribute($article->rss_feed_id) && $feed->exists) {
            $updates['rss_feed_id'] = $feed->id;
        }

        if ($article->category_id !== $resolvedCategoryId) {
            $updates['category_id'] = $resolvedCategoryId;
        }

        if ($article->sub_category_id !== $subCategory?->id) {
            $updates['sub_category_id'] = $subCategory?->id;
        }

        if ($this->isBlankArticleAttribute($article->short_description) && $description !== '') {
            $updates['short_description'] = $this->makeShortDescription(
                $description,
                (int) config('rss.article.short_description_length', 300),
            );
        }

        if ($this->isBlankArticleAttribute($article->image_url) && $imageUrl !== null) {
            $updates['image_url'] = $imageUrl;
        }

        if ($this->isBlankArticleAttribute($article->author) && $author !== null) {
            $updates['author'] = $author;
        }

        if ($this->isBlankArticleAttribute($article->source_name) && $sourceName !== null) {
            $updates['source_name'] = $sourceName;
        }

        if ($this->isBlankArticleAttribute($article->published_at)) {
            $updates['published_at'] = $this->extractPubDate($item);
        }

        $updates['rss_parsed_at'] = now();

        if ($updates !== []) {
            $article->forceFill($updates)->saveQuietly();
            $article->refresh();
        }

        return $this->enrichExistingArticle($article) || $updates !== [];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildArticleData(SimpleXMLElement $item, int $categoryId, int $rssFeedId, RssFeed $feed): array
    {
        /** @var array<string, mixed> $extraSettings */
        $extraSettings = is_array($feed->extra_settings) ? $feed->extra_settings : [];
        $itemCategories = $this->extractCategories($item);
        $title = $this->extractTitle($item);
        $description = $this->extractDescription($item);
        $fullHtml = $this->extractFullHtml($item);
        $content = $fullHtml !== '' ? $fullHtml : $description;
        $isFeatured = $feed->auto_featured && ! Article::query()->fromFeed($rssFeedId)->exists();
        $shortDescriptionLength = (int) ($extraSettings['short_description_length'] ?? config('rss.article.short_description_length', 300));
        $status = ArticleStatus::fromValue(
            $extraSettings['status'] ?? null,
            $feed->auto_publish ? ArticleStatus::Published : ArticleStatus::Draft,
        );
        $contentType = ArticleContentType::fromValue(
            $extraSettings['content_type'] ?? null,
            ArticleContentType::News,
        );
        $sourceName = $this->normalizeSourceName(
            is_string($extraSettings['source_name'] ?? null) && $extraSettings['source_name'] !== ''
                ? (string) $extraSettings['source_name']
                : ($feed->source_name ?: (string) config('rss.source_name', '')),
        );
        $defaultAuthor = is_string($extraSettings['default_author'] ?? null) && $extraSettings['default_author'] !== ''
            ? (string) $extraSettings['default_author']
            : (string) config('rss.article.default_author', 'Редакция');
        $author = $this->extractAuthor($item)
            ?? ($defaultAuthor !== '' ? $defaultAuthor : $sourceName);
        $resolvedCategory = $this->resolveItemCategory($feed, $itemCategories);
        $resolvedCategoryId = $resolvedCategory?->id ?? $categoryId;
        $subCategory = $this->resolveItemSubCategory($feed, $itemCategories, $resolvedCategory);

        $articleData = [
            'category_id' => $resolvedCategoryId,
            'sub_category_id' => $subCategory?->id,
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
            'status' => $status->value,
            'content_type' => $contentType->value,
            'is_featured' => $isFeatured,
            'importance' => $this->guessImportance($title, $description),
            'reading_time' => $this->calculateReadingTime($content),
            'published_at' => $this->extractPubDate($item),
            'rss_parsed_at' => now(),
        ];

        return Utf8Normalizer::normalize($this->enrichArticleDataFromSource($articleData, $extraSettings));
    }

    /**
     * @param  array<int, string>  $itemCategories
     */
    private function resolveItemCategory(RssFeed $feed, array $itemCategories): ?Category
    {
        $feedCategory = $this->resolveFeedCategory($feed);

        if (! $feedCategory instanceof Category) {
            return null;
        }

        $itemCategoryPaths = $this->extractItemCategoryPaths($itemCategories);

        foreach ($itemCategoryPaths as $itemCategoryPath) {
            if ($this->categoryMatchesValue($feedCategory, $itemCategoryPath['category_name'])) {
                return $feedCategory;
            }
        }

        if (! $this->isAggregateCategory($feedCategory)) {
            return $feedCategory;
        }

        foreach ($itemCategoryPaths as $itemCategoryPath) {
            $resolvedCategory = $this->findCategoryByValue($itemCategoryPath['category_name']);

            if ($resolvedCategory instanceof Category) {
                return $resolvedCategory;
            }
        }

        return $feedCategory;
    }

    /**
     * @param  array<int, string>  $itemCategories
     */
    private function resolveItemSubCategory(
        RssFeed $feed,
        array $itemCategories,
        ?Category $resolvedCategory = null,
    ): ?SubCategory {
        $resolvedCategory ??= $this->resolveItemCategory($feed, $itemCategories);

        if (! $resolvedCategory instanceof Category) {
            return null;
        }

        $resolvedName = $this->resolveItemSubCategoryName($itemCategories, $resolvedCategory);

        if ($resolvedName !== null) {
            return $this->resolveSubCategoryByName($resolvedCategory->id, $resolvedName);
        }

        return $this->resolveFeedSubCategory($feed, $resolvedCategory);
    }

    /**
     * @param  array<int, string>  $itemCategories
     */
    private function resolveItemSubCategoryName(array $itemCategories, Category $resolvedCategory): ?string
    {
        foreach ($this->extractItemCategoryPaths($itemCategories) as $itemCategoryPath) {
            $subCategoryName = $itemCategoryPath['sub_category_name'];

            if ($subCategoryName === null) {
                continue;
            }

            if ($this->categoryMatchesValue($resolvedCategory, $itemCategoryPath['category_name'])) {
                return $subCategoryName;
            }
        }

        return null;
    }

    private function resolveSubCategoryByName(int $categoryId, string $subCategoryName): ?SubCategory
    {
        $normalizedName = trim($subCategoryName);

        if ($normalizedName === '') {
            return null;
        }

        $subCategorySlug = Str::slug(strtr($normalizedName, self::RU_TRANSLIT));

        return $this->firstOrCreateSubCategory(
            $categoryId,
            $normalizedName,
            $subCategorySlug !== '' ? $subCategorySlug : null,
        );
    }

    private function firstOrCreateSubCategory(int $categoryId, string $subCategoryName, ?string $subCategorySlug = null): ?SubCategory
    {
        $normalizedName = trim($subCategoryName);
        $normalizedSlug = trim((string) $subCategorySlug);

        if ($normalizedName === '' && $normalizedSlug === '') {
            return null;
        }

        $query = SubCategory::query()->where('category_id', $categoryId);

        if ($normalizedSlug !== '') {
            $existingSubCategory = (clone $query)->where('slug', $normalizedSlug)->first();

            if ($existingSubCategory instanceof SubCategory) {
                return $existingSubCategory;
            }
        }

        if ($normalizedName !== '') {
            $existingSubCategory = (clone $query)->where('name', $normalizedName)->first();

            if ($existingSubCategory instanceof SubCategory) {
                return $existingSubCategory;
            }
        }

        if ($normalizedSlug === '') {
            $normalizedSlug = Str::slug(strtr($normalizedName, self::RU_TRANSLIT));
        }

        $uniqueSlug = $this->makeUniqueSubCategorySlug($categoryId, $normalizedSlug !== '' ? $normalizedSlug : null, $normalizedName);

        return SubCategory::query()->create([
            'category_id' => $categoryId,
            'name' => $normalizedName !== '' ? $normalizedName : $normalizedSlug,
            'slug' => $uniqueSlug,
            'description' => null,
            'is_active' => true,
            'order' => 0,
        ]);
    }

    private function makeUniqueSubCategorySlug(int $categoryId, ?string $preferredSlug, string $subCategoryName): string
    {
        $baseSlug = trim((string) $preferredSlug);

        if ($baseSlug === '') {
            $baseSlug = Str::slug(strtr($subCategoryName, self::RU_TRANSLIT));
        }

        if ($baseSlug === '') {
            $baseSlug = 'sub-category';
        }

        $existingBySlug = SubCategory::query()->where('slug', $baseSlug)->first();

        if (! $existingBySlug instanceof SubCategory || $existingBySlug->category_id === $categoryId) {
            return $baseSlug;
        }

        $categorySlug = (string) Category::query()->whereKey($categoryId)->value('slug');
        $baseSlug = trim($categorySlug) !== '' ? "{$categorySlug}-{$baseSlug}" : "{$baseSlug}-{$categoryId}";
        $slug = $baseSlug;
        $counter = 2;

        while (SubCategory::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function resolveFeedSubCategory(RssFeed $feed, ?Category $resolvedCategory = null): ?SubCategory
    {
        /** @var array<string, mixed> $extraSettings */
        $extraSettings = is_array($feed->extra_settings) ? $feed->extra_settings : [];
        $subCategoryName = trim((string) ($extraSettings['sub_category_name'] ?? ''));
        $subCategorySlug = trim((string) ($extraSettings['sub_category_slug'] ?? ''));
        $resolvedCategory ??= $this->resolveFeedCategory($feed);

        if (! $resolvedCategory instanceof Category) {
            return null;
        }

        if ($subCategoryName === '' && str_contains($feed->title, ':')) {
            [$feedCategoryName, $feedSubCategoryName] = array_map('trim', explode(':', $feed->title, 2));
            $categoryName = $resolvedCategory->name ?? '';

            if (
                $feedSubCategoryName !== ''
                && $categoryName !== ''
                && Str::lower($feedCategoryName) === Str::lower($categoryName)
            ) {
                $subCategoryName = $feedSubCategoryName;
            }
        }

        if ($subCategorySlug === '' && $subCategoryName !== '') {
            $subCategorySlug = Str::slug(strtr($subCategoryName, self::RU_TRANSLIT));
        }

        if ($subCategoryName === '' && $subCategorySlug === '') {
            return null;
        }

        return $this->firstOrCreateSubCategory(
            $resolvedCategory->id,
            $subCategoryName,
            $subCategorySlug !== '' ? $subCategorySlug : null,
        );
    }

    private function resolveFeedCategory(RssFeed $feed): ?Category
    {
        if ($feed->relationLoaded('category') && $feed->category instanceof Category) {
            return $feed->category;
        }

        $category = $this->findCategoryById(is_numeric($feed->category_id) ? (int) $feed->category_id : null);

        if ($category instanceof Category) {
            $feed->setRelation('category', $category);
        }

        return $category;
    }

    private function findCategoryById(?int $categoryId): ?Category
    {
        if ($categoryId === null) {
            return null;
        }

        return $this->categoryCatalog()
            ->first(fn (Category $category): bool => (int) $category->id === $categoryId);
    }

    private function findCategoryByValue(string $value): ?Category
    {
        $normalizedValue = trim($value);

        if ($normalizedValue === '') {
            return null;
        }

        return $this->categoryCatalog()
            ->first(fn (Category $category): bool => $this->categoryMatchesValue($category, $normalizedValue));
    }

    private function categoryMatchesValue(Category $category, string $value): bool
    {
        $normalizedValue = $this->normalizeTaxonomyValue($value);

        if ($normalizedValue === '') {
            return false;
        }

        $normalizedSlug = $this->makeTaxonomySlug($value);

        return collect([
            $category->name,
            $category->slug,
            $category->rss_key,
        ])
            ->filter(fn (mixed $candidate): bool => is_string($candidate) && trim($candidate) !== '')
            ->contains(function (string $candidate) use ($normalizedSlug, $normalizedValue): bool {
                $normalizedCandidate = $this->normalizeTaxonomyValue($candidate);

                if ($normalizedCandidate === $normalizedValue) {
                    return true;
                }

                if ($normalizedSlug === '') {
                    return false;
                }

                return $this->makeTaxonomySlug($candidate) === $normalizedSlug;
            });
    }

    private function isAggregateCategory(Category $category): bool
    {
        return in_array(Str::lower((string) $category->slug), ['all', 'main'], true);
    }

    /**
     * @param  array<int, string>  $itemCategories
     * @return array<int, array{raw: string, category_name: string, sub_category_name: string|null}>
     */
    private function extractItemCategoryPaths(array $itemCategories): array
    {
        return collect($itemCategories)
            ->map(function (string $itemCategory): ?array {
                $normalizedItemCategory = trim($itemCategory);

                if ($normalizedItemCategory === '') {
                    return null;
                }

                if (! str_contains($normalizedItemCategory, ':')) {
                    return [
                        'raw' => $normalizedItemCategory,
                        'category_name' => $normalizedItemCategory,
                        'sub_category_name' => null,
                    ];
                }

                [$categoryName, $subCategoryName] = array_map('trim', explode(':', $normalizedItemCategory, 2));

                if ($categoryName === '') {
                    return [
                        'raw' => $normalizedItemCategory,
                        'category_name' => $normalizedItemCategory,
                        'sub_category_name' => null,
                    ];
                }

                return [
                    'raw' => $normalizedItemCategory,
                    'category_name' => $categoryName,
                    'sub_category_name' => $subCategoryName !== '' ? $subCategoryName : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Category>
     */
    private function categoryCatalog(): Collection
    {
        if ($this->categoryCatalog instanceof Collection) {
            return $this->categoryCatalog;
        }

        return $this->categoryCatalog = Category::query()->get();
    }

    private function normalizeTaxonomyValue(string $value): string
    {
        return Str::lower(trim(Utf8Normalizer::normalizeString($value) ?? $value));
    }

    private function makeTaxonomySlug(string $value): string
    {
        return Str::slug(strtr(trim($value), self::RU_TRANSLIT));
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

            $existingArticle = $this->findDuplicateArticle($guid, $link);

            if ($existingArticle instanceof Article) {
                $this->logger()->warning("Duplicate found: {$guid}");
                $this->refreshDuplicateArticle($existingArticle, $item, $feed);

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
        return $this->parseFeedWithPrefetchedAttempt($feed, $triggeredBy);
    }

    /**
     * @param  iterable<int, \App\Models\RssFeed>  $feeds
     * @return array<int, array<string, int|string|null>>
     */
    public function parseFeeds(iterable $feeds, string $triggeredBy = 'scheduler'): array
    {
        $feeds = collect($feeds)->values();
        $prefetchedAttempts = $this->prefetchFeedAttemptResults($feeds);

        return $feeds
            ->mapWithKeys(function (RssFeed $feed) use ($triggeredBy, $prefetchedAttempts): array {
                return [
                    $feed->id => $this->parseFeedWithPrefetchedAttempt(
                        $feed,
                        $triggeredBy,
                        $prefetchedAttempts[$feed->id] ?? null,
                    ),
                ];
            })
            ->all();
    }

    private function parseFeedWithPrefetchedAttempt(
        RssFeed $feed,
        string $triggeredBy = 'scheduler',
        Response|ConnectionException|null $firstAttemptResult = null,
    ): array {
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
            $xml = $this->fetchFeedXml($feed->url, $firstAttemptResult);
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

            app(MetricTracker::class)->recordMany(array_filter([
                [
                    'metric' => TrackedMetric::RssParseRun,
                    'measurable' => $feed,
                    'recorded_at' => $log->finished_at ?? now(),
                ],
                $new > 0 ? [
                    'metric' => TrackedMetric::RssArticleImported,
                    'value' => $new,
                    'measurable' => $feed,
                    'recorded_at' => $log->finished_at ?? now(),
                ] : null,
                ($error !== null || $errors > 0) ? [
                    'metric' => TrackedMetric::RssParseFailure,
                    'value' => max(1, $errors),
                    'measurable' => $feed,
                    'recorded_at' => $log->finished_at ?? now(),
                ] : null,
            ]));
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
     * @param  iterable<int, \App\Models\RssFeed>  $feeds
     * @return array<int, \Illuminate\Http\Client\Response|\Illuminate\Http\Client\ConnectionException>
     */
    private function prefetchFeedAttemptResults(iterable $feeds, int $maxRetries = 3): array
    {
        $feeds = collect($feeds)->values();

        if ($feeds->count() < 2) {
            return [];
        }

        $feedIds = $feeds->map(fn (RssFeed $feed): int => $feed->id)->all();
        $concurrency = max(1, min((int) config('rss.parser.batch_concurrency', 5), $feeds->count()));
        $attemptResults = [];

        $batch = Http::batch(function (Batch $batch) use ($feeds, $maxRetries): void {
            foreach ($feeds as $feed) {
                $this->configureFeedRequest($batch->as((string) $feed->id), $feed->url, $feed->url, 1, $maxRetries)
                    ->get('{+endpoint}{?query*}');
            }
        })
            ->concurrency($concurrency)
            ->before(function (Batch $batch) use ($feedIds, $concurrency): void {
                $this->logger()->debug('RSS batch sending.', [
                    'feed_ids' => $feedIds,
                    'concurrency' => $concurrency,
                    'total' => $batch->totalRequests,
                ]);
            })
            ->catch(function (Batch $batch, string|int $key, Response|RequestException|ConnectionException $result) use (&$attemptResults): void {
                unset($batch);

                if ($result instanceof Response || $result instanceof ConnectionException) {
                    $attemptResults[(int) $key] = $result;
                }
            })
            ->finally(function (Batch $batch) use ($feedIds): void {
                $this->logger()->debug('RSS batch finished.', [
                    'feed_ids' => $feedIds,
                    'processed' => $batch->processedRequests(),
                    'failed' => $batch->failedRequests,
                    'finished' => $batch->finished(),
                ]);
            });

        foreach ($batch->send() as $key => $response) {
            if ($response instanceof Response) {
                $attemptResults[(int) $key] = $response;
            }
        }

        return $attemptResults;
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    public function parseAllFeeds(string $triggeredBy = 'scheduler'): array
    {
        return $this->parseFeeds(
            RssFeed::query()
                ->active()
                ->with('category')
                ->get(),
            $triggeredBy,
        );
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    public function parseDueFeeds(string $triggeredBy = 'scheduler'): array
    {
        return $this->parseFeeds(
            RssFeed::query()
                ->dueForParsing()
                ->with('category')
                ->get(),
            $triggeredBy,
        );
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
                'source_name' => (string) config('rss.source_name', ''),
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
