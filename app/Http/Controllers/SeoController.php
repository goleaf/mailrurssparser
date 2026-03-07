<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $urls = [
            '<url><loc>'.$baseUrl.'/#/</loc><changefreq>hourly</changefreq><priority>1.0</priority></url>',
        ];

        Category::query()->active()->get(['slug'])->each(function (Category $category) use (&$urls, $baseUrl): void {
            $urls[] = '<url><loc>'.$baseUrl.'/#/category/'.$category->slug.'</loc><changefreq>hourly</changefreq><priority>0.7</priority></url>';
        });

        Article::query()
            ->published()
            ->select(['slug', 'published_at', 'updated_at'])
            ->orderByDesc('published_at')
            ->chunk(500, function ($articles) use (&$urls, $baseUrl): void {
                foreach ($articles as $article) {
                    $lastModified = $article->updated_at?->toAtomString() ?? $article->published_at?->toAtomString();
                    $urls[] = '<url><loc>'.$baseUrl.'/#/articles/'.$article->slug.'</loc><lastmod>'.$lastModified.'</lastmod><priority>0.8</priority></url>';
                }
            });

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
                $title = htmlspecialchars((string) $article->title, ENT_XML1);
                $description = htmlspecialchars((string) ($article->short_description ?? ''), ENT_XML1);
                $link = $baseUrl.'/#/articles/'.$article->slug;
                $category = htmlspecialchars((string) $article->category?->name, ENT_XML1);
                $pubDate = $article->published_at?->toRfc2822String() ?? now()->toRfc2822String();

                return "<item><title>{$title}</title><link>{$link}</link><guid>{$link}</guid><description>{$description}</description><category>{$category}</category><pubDate>{$pubDate}</pubDate></item>";
            })
            ->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0"><channel>'
            .'<title>'.htmlspecialchars((string) config('app.name', 'Новостной портал'), ENT_XML1).'</title>'
            .'<link>'.$baseUrl.'</link>'
            .'<description>Последние новости портала</description>'
            .$items
            .'</channel></rss>';

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml',
        ]);
    }
}
