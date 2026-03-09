<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ShareTrackRequest;
use App\Models\Article;
use Illuminate\Http\JsonResponse;

class ShareController extends Controller
{
    public function track(ShareTrackRequest $request, int $articleId): JsonResponse
    {
        $platform = $request->validated('platform');
        $article = Article::query()->findOrFail($articleId);

        $article->incrementShares();

        $url = route('articles.show', ['slug' => $article->slug]);
        $title = rawurlencode((string) $article->title);
        $encodedUrl = rawurlencode($url);

        $shareUrl = match ($platform) {
            'vk' => "https://vk.com/share.php?url={$encodedUrl}",
            'telegram' => "https://t.me/share/url?url={$encodedUrl}&text={$title}",
            'whatsapp' => "https://api.whatsapp.com/send?text={$title}%20{$encodedUrl}",
            'twitter' => "https://twitter.com/intent/tweet?text={$title}&url={$encodedUrl}",
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}",
            default => $url,
        };

        return response()->json([
            'success' => true,
            'platform' => $platform,
            'share_url' => $shareUrl,
            'total' => $article->shares_count,
        ]);
    }
}
