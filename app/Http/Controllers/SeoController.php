<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $urls = [
            $this->urlTag(
                loc: $baseUrl.'/#/',
                changefreq: 'hourly',
                priority: '1.0',
            ),
        ];

        Category::query()->get(['slug'])->each(function (Category $category) use (&$urls, $baseUrl): void {
            $urls[] = $this->urlTag(
                loc: $baseUrl.'/#/category/'.$category->slug,
                changefreq: 'hourly',
                priority: '0.7',
            );
        });

        Article::query()
            ->published()
            ->select(['id', 'slug', 'published_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $articles) use (&$urls, $baseUrl): void {
                foreach ($articles as $article) {
                    $lastModified = $article->updated_at?->toAtomString() ?? $article->published_at?->toAtomString();
                    $urls[] = $this->urlTag(
                        loc: $baseUrl.'/#/articles/'.$article->slug,
                        lastmod: $lastModified,
                        priority: '0.8',
                    );
                }
            }, column: 'id');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .implode('', $urls)
            .'</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function rss(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $items = Article::query()
            ->published()
            ->with('category')
            ->latest('published_at')
            ->limit(50)
            ->get()
            ->map(function (Article $article) use ($baseUrl): string {
                $link = $baseUrl.'/#/articles/'.$article->slug;
                $pubDate = $article->published_at?->toRfc2822String() ?? now()->toRfc2822String();

                return '<item>'
                    .'<title>'.$this->escapeXml((string) $article->title).'</title>'
                    .'<link>'.$this->escapeXml($link).'</link>'
                    .'<guid>'.$this->escapeXml($link).'</guid>'
                    .'<description>'.$this->escapeXml((string) ($article->short_description ?? '')).'</description>'
                    .'<category>'.$this->escapeXml((string) $article->category?->name).'</category>'
                    .'<pubDate>'.$this->escapeXml($pubDate).'</pubDate>'
                    .'</item>';
            })
            ->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0"><channel>'
            .'<title>'.$this->escapeXml((string) config('app.name', 'Новостной портал')).'</title>'
            .'<link>'.$this->escapeXml($baseUrl).'</link>'
            .'<description>'.$this->escapeXml('Последние новости портала').'</description>'
            .'<language>ru</language>'
            .'<lastBuildDate>'.$this->escapeXml(now()->toRfc2822String()).'</lastBuildDate>'
            .$items
            .'</channel></rss>';

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml',
        ]);
    }

    private function urlTag(string $loc, ?string $changefreq = null, ?string $priority = null, ?string $lastmod = null): string
    {
        $segments = [
            '<loc>'.$this->escapeXml($loc).'</loc>',
        ];

        if ($lastmod !== null) {
            $segments[] = '<lastmod>'.$this->escapeXml($lastmod).'</lastmod>';
        }

        if ($changefreq !== null) {
            $segments[] = '<changefreq>'.$this->escapeXml($changefreq).'</changefreq>';
        }

        if ($priority !== null) {
            $segments[] = '<priority>'.$this->escapeXml($priority).'</priority>';
        }

        return '<url>'.implode('', $segments).'</url>';
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
