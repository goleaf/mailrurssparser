<?php

namespace App\Services;

use App\Models\Article;
use App\Support\Utf8Normalizer;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Throwable;

class SourceArticleParserService
{
    private CssSelectorConverter $cssSelectorConverter;

    public function __construct()
    {
        $this->cssSelectorConverter = new CssSelectorConverter(true);
    }

    /**
     * @param  array<string, mixed>  $articleData
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function enrichArticleData(array $articleData, array $settings = []): array
    {
        $sourceUrl = $articleData['source_url'] ?? null;

        if (! is_string($sourceUrl) || $sourceUrl === '' || ! $this->isEnabled($settings)) {
            return $articleData;
        }

        try {
            $sourceData = $this->parseUrl($sourceUrl, $settings);
        } catch (Throwable $exception) {
            $this->logger()->warning("Source page enrichment failed for {$sourceUrl}: {$exception->getMessage()}");

            return $articleData;
        }

        foreach ([
            'title',
            'image_url',
            'image_caption',
            'author',
            'author_url',
            'source_name',
            'meta_title',
            'meta_description',
            'canonical_url',
            'published_at',
            'structured_data',
        ] as $key) {
            $value = $sourceData[$key] ?? null;

            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            $articleData[$key] = $value;
        }

        $subtitle = $this->firstNonEmptyString(
            $sourceData['subtitle'] ?? null,
            $sourceData['short_description'] ?? null,
        );

        if ($subtitle !== null) {
            $articleData['short_description'] = $this->makeShortDescription(
                $subtitle,
                $this->shortDescriptionLength($settings),
            );
        } elseif (
            blank($articleData['short_description'] ?? null)
            && filled($sourceData['meta_description'] ?? null)
        ) {
            $articleData['short_description'] = $this->makeShortDescription(
                (string) $sourceData['meta_description'],
                $this->shortDescriptionLength($settings),
            );
        }

        if (filled($sourceData['full_description'] ?? null)) {
            $articleData['full_description'] = $sourceData['full_description'];
        }

        return $articleData;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function parseUrl(string $url, array $settings = []): array
    {
        return $this->parseHtml($this->fetchSourcePage($url, $settings), $url, $settings);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function parseHtml(string $html, string $url, array $settings = []): array
    {
        $xpath = $this->createXPath($html);
        $meta = $this->extractMetaTags($xpath);
        $preloadedArticle = $this->extractMailPreloadedArticleState($xpath);
        $preloadedArticleData = is_array($preloadedArticle)
            ? $this->extractMailPreloadedArticleData($preloadedArticle)
            : [];
        $structuredDataItems = $this->extractStructuredDataItems($xpath);
        $articleStructuredData = $this->extractPrimaryArticleStructuredData($structuredDataItems);
        $canonicalUrl = $this->extractCanonicalUrl($xpath, $url);
        $customImageSelectors = $this->splitSelectors($settings['source_page_image_selector'] ?? null);
        $defaultImageSelectors = Config::collection('rss.source_pages.image_selectors', [])
            ->map(fn (mixed $selector): string => is_string($selector) ? trim($selector) : '')
            ->filter()
            ->values()
            ->all();
        $title = $this->firstNonEmptyString(
            $this->extractTextBySelectors($xpath, $this->titleSelectors($settings)),
            is_string($preloadedArticleData['title'] ?? null) ? $preloadedArticleData['title'] : null,
            is_array($articleStructuredData) ? $this->firstNonEmptyString($articleStructuredData['headline'] ?? null) : null,
            $meta['og:title'] ?? null,
            $meta['twitter:title'] ?? null,
            $this->documentTitle($xpath),
        );
        $subtitle = $this->firstNonEmptyString(
            $this->extractTextBySelectors($xpath, $this->subtitleSelectors($settings)),
            is_string($preloadedArticleData['short_description'] ?? null) ? $preloadedArticleData['short_description'] : null,
            is_string($preloadedArticleData['meta_description'] ?? null) ? $preloadedArticleData['meta_description'] : null,
            is_array($articleStructuredData) ? $this->firstNonEmptyString($articleStructuredData['alternativeHeadline'] ?? null) : null,
            null,
        );
        $description = $this->firstNonEmptyString(
            $subtitle,
            is_string($preloadedArticleData['short_description'] ?? null) ? $preloadedArticleData['short_description'] : null,
            is_string($preloadedArticleData['meta_description'] ?? null) ? $preloadedArticleData['meta_description'] : null,
            is_array($articleStructuredData) ? $this->firstNonEmptyString($articleStructuredData['description'] ?? null) : null,
            $meta['widget:description'] ?? null,
            $meta['og:description'] ?? null,
            $meta['description'] ?? null,
            $meta['twitter:description'] ?? null,
        );
        $bodyHtml = $this->firstNonEmptyString(
            is_string($preloadedArticleData['full_description'] ?? null) ? $preloadedArticleData['full_description'] : null,
        ) ?? '';

        if ($bodyHtml === '') {
            $bodyHtml = $this->extractArticleBodyHtml($xpath, $canonicalUrl, $settings);
        }

        if ($bodyHtml === '' && is_array($articleStructuredData)) {
            $bodyHtml = $this->textToHtmlParagraphs((string) ($articleStructuredData['articleBody'] ?? ''));
        }

        $imageUrl = $this->firstNonEmptyString(
            $this->extractImageUrlBySelectors($xpath, $customImageSelectors, $canonicalUrl),
            is_string($preloadedArticleData['image_url'] ?? null) ? $preloadedArticleData['image_url'] : null,
            is_array($articleStructuredData) ? $this->extractStructuredDataImageUrl($articleStructuredData['image'] ?? null, $canonicalUrl) : null,
            $this->extractImageUrlBySelectors($xpath, $defaultImageSelectors, $canonicalUrl),
            $meta['og:image'] ?? null,
            $meta['twitter:image'] ?? null,
            $meta['yandex_recommendations_image'] ?? null,
        );
        $imageUrl = $imageUrl !== null ? $this->absolutizeUrl($canonicalUrl, $imageUrl) : null;
        $authorElement = $this->selectFirstElement($xpath, $this->authorSelectors($settings));
        $author = $this->firstNonEmptyString(
            $authorElement !== null ? $this->sanitizeText($authorElement->textContent) : null,
            is_string($preloadedArticleData['author'] ?? null) ? $preloadedArticleData['author'] : null,
            is_array($articleStructuredData) ? $this->extractStructuredDataPersonName($articleStructuredData['author'] ?? null) : null,
            $meta['author'] ?? null,
        );
        $authorUrl = $this->firstNonEmptyString(
            $authorElement !== null ? $this->extractLinkFromElement($authorElement, $canonicalUrl) : null,
            is_string($preloadedArticleData['author_url'] ?? null) ? $preloadedArticleData['author_url'] : null,
            is_array($articleStructuredData) ? $this->extractStructuredDataPersonUrl($articleStructuredData['author'] ?? null, $canonicalUrl) : null,
        );
        $publishedAt = $this->firstDateValue(
            is_string($preloadedArticleData['published_at'] ?? null) ? $preloadedArticleData['published_at'] : null,
            is_array($articleStructuredData) ? ($articleStructuredData['datePublished'] ?? null) : null,
            $meta['article:published_time'] ?? null,
        );
        $modifiedAt = $this->firstDateValue(
            is_string($preloadedArticleData['last_edited_at'] ?? null) ? $preloadedArticleData['last_edited_at'] : null,
            is_array($articleStructuredData) ? ($articleStructuredData['dateModified'] ?? null) : null,
            $meta['article:modified_time'] ?? null,
        );
        $sourceName = $this->normalizeSourceName($this->firstNonEmptyString(
            $meta['marker:source'] ?? null,
            is_string($preloadedArticleData['source_name'] ?? null) ? $preloadedArticleData['source_name'] : null,
            is_array($articleStructuredData) ? $this->extractPublisherName($articleStructuredData['publisher'] ?? null) : null,
            $meta['og:site_name'] ?? null,
        ));
        $imageCaption = $this->firstNonEmptyString(
            is_string($preloadedArticleData['image_caption'] ?? null) ? $preloadedArticleData['image_caption'] : null,
            $this->extractImageCaption($xpath, $settings),
            $meta['og:image:alt'] ?? null,
            $meta['twitter:image:alt'] ?? null,
        );
        $keywords = $this->extractKeywords(
            is_array($articleStructuredData) ? ($articleStructuredData['keywords'] ?? null) : null,
            $meta['keywords'] ?? null,
        );
        $section = $this->firstNonEmptyString(
            is_array($articleStructuredData) ? $this->firstNonEmptyString($articleStructuredData['articleSection'] ?? null) : null,
            $meta['article:section'] ?? null,
        );

        return Utf8Normalizer::normalize([
            'title' => $title,
            'subtitle' => $subtitle,
            'short_description' => $description,
            'full_description' => $bodyHtml !== '' ? $bodyHtml : null,
            'image_url' => $imageUrl,
            'image_caption' => $imageCaption,
            'author' => $author,
            'author_url' => $authorUrl,
            'source_name' => $sourceName,
            'meta_title' => $title,
            'meta_description' => $description,
            'canonical_url' => $this->firstNonEmptyString(
                is_string($preloadedArticleData['canonical_url'] ?? null) ? $preloadedArticleData['canonical_url'] : null,
                $canonicalUrl,
            ),
            'published_at' => $publishedAt,
            'structured_data' => $this->buildStructuredData(
                headline: $title,
                subtitle: $subtitle,
                description: $description,
                imageUrl: $imageUrl,
                imageCaption: $imageCaption,
                author: $author,
                authorUrl: $authorUrl,
                publisher: $sourceName,
                canonicalUrl: $canonicalUrl,
                publishedAt: $publishedAt,
                modifiedAt: $modifiedAt,
                section: $section,
                keywords: $keywords,
            ),
        ]);
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

    private function logger(): \Illuminate\Log\Logger
    {
        return Log::channel('rss');
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function isEnabled(array $settings): bool
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

    /**
     * @param  array<string, mixed>  $settings
     */
    private function shortDescriptionLength(array $settings): int
    {
        $length = $settings['short_description_length'] ?? config('rss.article.short_description_length', 300);

        return max(1, (int) $length);
    }

    /**
     * @param  array<string, mixed>  $settings
     *
     * @throws RuntimeException
     */
    private function fetchSourcePage(string $url, array $settings, ?int $maxRetries = null): string
    {
        $maxRetries ??= (int) config('rss.source_pages.max_retries', config('rss.page_parser.max_retries', 2));
        $currentUrl = $url;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout((int) config('rss.source_pages.timeout', config('rss.page_parser.timeout', config('rss.parser.timeout', 30))))
                    ->connectTimeout((int) config('rss.source_pages.connect_timeout', config('rss.page_parser.connect_timeout', config('rss.parser.connect_timeout', 10))))
                    ->withoutVerifying()
                    ->withoutRedirecting()
                    ->withHeaders([
                        'User-Agent' => (string) config('rss.source_pages.user_agent', config('rss.page_parser.user_agent', config('rss.parser.user_agent'))),
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

    /**
     * @throws RuntimeException
     */
    private function createXPath(string $html): DOMXPath
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

    private function documentTitle(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//title');

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        return $this->sanitizeText($nodes->item(0)?->textContent ?? '');
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

            $payload = preg_replace('/^\s*<!--|-->\s*$/', '', $payload) ?? $payload;

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

            if (array_intersect($normalizedTypes, [
                'newsarticle',
                'article',
                'reportage',
                'reportagenewsarticle',
                'analysisnewsarticle',
                'blogposting',
            ])) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $selectors
     */
    private function extractTextBySelectors(DOMXPath $xpath, array $selectors): ?string
    {
        $element = $this->selectFirstElement($xpath, $selectors);

        if (! $element instanceof DOMElement) {
            return null;
        }

        $text = $this->sanitizeText($element->textContent);

        return $text !== '' ? $text : null;
    }

    /**
     * @param  list<string>  $selectors
     */
    private function extractImageUrlBySelectors(DOMXPath $xpath, array $selectors, string $baseUrl): ?string
    {
        $element = $this->selectFirstElement($xpath, $selectors);

        if (! $element instanceof DOMElement) {
            return null;
        }

        foreach (['src', 'data-src', 'content', 'href'] as $attribute) {
            $value = trim($element->getAttribute($attribute));

            if ($value !== '') {
                return $this->absolutizeUrl($baseUrl, $value);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $selectors
     */
    private function selectFirstElement(DOMXPath $xpath, array $selectors, ?DOMNode $contextNode = null): ?DOMElement
    {
        foreach ($selectors as $selector) {
            $expression = $this->cssToXPath($selector);

            if ($expression === null) {
                continue;
            }

            $nodes = $xpath->query($expression, $contextNode);

            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    return $node;
                }
            }
        }

        return null;
    }

    private function cssToXPath(string $selector): ?string
    {
        $selector = trim($selector);

        if ($selector === '') {
            return null;
        }

        try {
            return $this->cssSelectorConverter->toXPath($selector);
        } catch (Throwable) {
            return null;
        }
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

    /**
     * @param  array<string, mixed>  $settings
     */
    private function extractImageCaption(DOMXPath $xpath, array $settings): ?string
    {
        $articleElement = $this->selectFirstElement($xpath, $this->articleSelectors($settings));

        if ($articleElement instanceof DOMElement) {
            $figcaptionNodes = $xpath->query('.//figcaption', $articleElement);

            if ($figcaptionNodes !== false && $figcaptionNodes->length > 0) {
                $caption = $this->sanitizeText($figcaptionNodes->item(0)?->textContent ?? '');

                if ($caption !== '') {
                    return $caption;
                }
            }
        }

        $nodes = $xpath->query('//figcaption');

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $caption = $this->sanitizeText($nodes->item(0)?->textContent ?? '');

        return $caption !== '' ? $caption : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function extractArticleBodyHtml(DOMXPath $xpath, string $baseUrl, array $settings): string
    {
        $container = $this->selectFirstElement($xpath, $this->articleSelectors($settings));

        if ($container instanceof DOMElement) {
            $html = $this->sanitizeArticleElement($container, $baseUrl, $settings);

            if ($html !== '') {
                return $html;
            }
        }

        $fallbackQueries = [
            '//*[@article-item-type="html"]',
            '//*[contains(@class, "article__body")]',
            '//*[contains(@class, "article-content")]',
            '//*[contains(@class, "entry-content")]',
            '//article',
            '//main',
        ];

        foreach ($fallbackQueries as $query) {
            $nodes = $xpath->query($query);

            if ($nodes === false || $nodes->length === 0 || ! $nodes->item(0) instanceof DOMElement) {
                continue;
            }

            $html = $this->sanitizeArticleElement($nodes->item(0), $baseUrl, $settings);

            if ($html !== '') {
                return $html;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function sanitizeArticleElement(DOMElement $element, string $baseUrl, array $settings): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $clone = $document->importNode($element, true);

        if (! $clone instanceof DOMElement) {
            return '';
        }

        $document->appendChild($clone);
        $xpath = new DOMXPath($document);

        foreach (['script', 'style', 'noscript', 'iframe', 'svg', 'form', 'nav', 'aside', 'footer', 'button', 'template'] as $tag) {
            $nodes = $xpath->query('//'.$tag);

            if ($nodes === false) {
                continue;
            }

            foreach (iterator_to_array($nodes) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        foreach ($this->removeSelectors($settings) as $selector) {
            $expression = $this->cssToXPath($selector);

            if ($expression === null) {
                continue;
            }

            $nodes = $xpath->query($expression, $clone);

            if ($nodes === false) {
                continue;
            }

            foreach (iterator_to_array($nodes) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $this->normalizeElementAttributes($clone, $baseUrl);

        $html = $this->innerHtml($clone);
        $html = $this->sanitizeHtml($html);

        return $this->isMeaningfulHtml($html, $settings) ? $html : '';
    }

    private function normalizeElementAttributes(DOMElement $root, string $baseUrl): void
    {
        $this->normalizeSingleElementAttributes($root, $baseUrl);

        foreach ($root->getElementsByTagName('*') as $element) {
            if ($element instanceof DOMElement) {
                $this->normalizeSingleElementAttributes($element, $baseUrl);
            }
        }
    }

    private function normalizeSingleElementAttributes(DOMElement $element, string $baseUrl): void
    {
        $allowedAttributes = match (Str::lower($element->tagName)) {
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt', 'title', 'width', 'height'],
            default => [],
        };

        $attributes = iterator_to_array($element->attributes ?? []);

        foreach ($attributes as $attribute) {
            $name = $attribute->nodeName;

            if (! in_array($name, $allowedAttributes, true)) {
                $element->removeAttribute($name);

                continue;
            }

            if (in_array($name, ['href', 'src'], true)) {
                $element->setAttribute($name, $this->absolutizeUrl($baseUrl, $attribute->nodeValue));
            }
        }
    }

    private function innerHtml(DOMElement $element): string
    {
        $document = $element->ownerDocument;

        if (! $document instanceof DOMDocument) {
            return '';
        }

        $html = '';

        foreach ($element->childNodes as $childNode) {
            $html .= $document->saveHTML($childNode);
        }

        return trim($html);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function isMeaningfulHtml(string $html, array $settings): bool
    {
        return mb_strlen($this->sanitizeText($html)) >= max(
            40,
            (int) ($settings['source_page_min_body_characters']
                ?? config('rss.source_pages.min_body_characters', 180)),
        );
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

    private function detectEncoding(string $body): string
    {
        $encoding = 'UTF-8';

        if (preg_match('/<meta[^>]+charset=["\']?([^"\'>\s]+)/i', $body, $matches) === 1) {
            $encoding = strtoupper(trim($matches[1]));
        }

        if (preg_match('/<\?xml[^>]+encoding=["\']([^"\']+)["\']/i', $body, $matches) === 1) {
            $encoding = strtoupper(trim($matches[1]));
        }

        if (in_array($encoding, ['WINDOWS-1251', 'CP1251'], true) && function_exists('iconv')) {
            $converted = iconv('Windows-1251', 'UTF-8//IGNORE', $body);

            if ($converted !== false) {
                return Utf8Normalizer::normalizeString($converted) ?? '';
            }
        }

        return Utf8Normalizer::normalizeString($body) ?? '';
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

    private function firstDateValue(mixed ...$values): ?Carbon
    {
        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
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

    private function extractStructuredDataPersonUrl(mixed $value, string $baseUrl): ?string
    {
        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $item) {
                $url = $this->extractStructuredDataPersonUrl($item, $baseUrl);

                if ($url !== null) {
                    return $url;
                }
            }

            return null;
        }

        if (is_array($value)) {
            $candidate = trim((string) ($value['url'] ?? ''));

            return $candidate !== '' ? $this->absolutizeUrl($baseUrl, $candidate) : null;
        }

        return null;
    }

    private function extractStructuredDataImageUrl(mixed $value, string $baseUrl): ?string
    {
        if (is_string($value)) {
            return $this->absolutizeUrl($baseUrl, $value);
        }

        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $item) {
                $url = $this->extractStructuredDataImageUrl($item, $baseUrl);

                if ($url !== null) {
                    return $url;
                }
            }

            return null;
        }

        if (is_array($value)) {
            return $this->extractStructuredDataImageUrl($value['url'] ?? null, $baseUrl);
        }

        return null;
    }

    private function extractPublisherName(mixed $value): ?string
    {
        return $this->extractStructuredDataPersonName($value);
    }

    private function extractKeywords(mixed ...$values): array
    {
        $keywords = collect($values)
            ->flatMap(function (mixed $value): array {
                if (is_string($value)) {
                    return preg_split('/\s*,\s*/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                }

                if (is_array($value) && array_is_list($value)) {
                    return collect($value)
                        ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
                        ->map(fn (string $item): string => trim($item))
                        ->values()
                        ->all();
                }

                return [];
            })
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $keywords;
    }

    private function extractLinkFromElement(DOMElement $element, string $baseUrl): ?string
    {
        if (Str::lower($element->tagName) === 'a') {
            $href = trim($element->getAttribute('href'));

            return $href !== '' ? $this->absolutizeUrl($baseUrl, $href) : null;
        }

        foreach ($element->getElementsByTagName('a') as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = trim($link->getAttribute('href'));

            if ($href !== '') {
                return $this->absolutizeUrl($baseUrl, $href);
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

    private function makeShortDescription(string $text, int $length = 300): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));

        if ($text === '') {
            return '';
        }

        return Str::limit($text, $length, '...');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildStructuredData(
        ?string $headline,
        ?string $subtitle,
        ?string $description,
        ?string $imageUrl,
        ?string $imageCaption,
        ?string $author,
        ?string $authorUrl,
        ?string $publisher,
        string $canonicalUrl,
        ?Carbon $publishedAt,
        ?Carbon $modifiedAt,
        ?string $section,
        array $keywords,
    ): ?array {
        if ($headline === null && $description === null && $imageUrl === null) {
            return null;
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $headline,
            'alternativeHeadline' => $subtitle,
            'description' => $description,
            'image' => $imageUrl !== null ? array_filter([
                '@type' => 'ImageObject',
                'url' => $imageUrl,
                'caption' => $imageCaption,
            ]) : null,
            'datePublished' => $publishedAt?->toIso8601String(),
            'dateModified' => $modifiedAt?->toIso8601String(),
            'author' => $author !== null ? array_filter([
                '@type' => 'Person',
                'name' => $author,
                'url' => $authorUrl,
            ]) : null,
            'publisher' => $publisher !== null ? [
                '@type' => 'Organization',
                'name' => $publisher,
            ] : null,
            'mainEntityOfPage' => $canonicalUrl,
            'articleSection' => $section,
            'keywords' => $keywords !== [] ? $keywords : null,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function titleSelectors(array $settings): array
    {
        return $this->selectorList($settings['source_page_title_selector'] ?? null, 'rss.source_pages.title_selectors');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function subtitleSelectors(array $settings): array
    {
        return $this->selectorList($settings['source_page_subtitle_selector'] ?? null, 'rss.source_pages.subtitle_selectors');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function articleSelectors(array $settings): array
    {
        return $this->selectorList($settings['source_page_article_selector'] ?? null, 'rss.source_pages.article_selectors');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function authorSelectors(array $settings): array
    {
        return $this->selectorList($settings['source_page_author_selector'] ?? null, 'rss.source_pages.author_selectors');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function imageSelectors(array $settings): array
    {
        return $this->selectorList($settings['source_page_image_selector'] ?? null, 'rss.source_pages.image_selectors');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function removeSelectors(array $settings): array
    {
        return $this->selectorList($settings['source_page_remove_selectors'] ?? null, 'rss.source_pages.remove_selectors');
    }

    /**
     * @return list<string>
     */
    private function selectorList(mixed $overrideValue, string $configKey): array
    {
        return collect([
            ...$this->splitSelectors($overrideValue),
            ...Config::collection($configKey, [])
                ->map(fn (mixed $selector): string => is_string($selector) ? trim($selector) : '')
                ->filter()
                ->values()
                ->all(),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function splitSelectors(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        return collect(preg_split('/[\r\n,]+/u', $value) ?: [])
            ->map(fn (string $selector): string => trim($selector))
            ->filter()
            ->values()
            ->all();
    }
}
