<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleView extends Model
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\ArticleViewFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'article_id',
        'ip_hash',
        'session_hash',
        'country_code',
        'timezone',
        'locale',
        'device_type',
        'referrer_type',
        'referrer_domain',
        'ip_address',
        'session_id',
        'user_agent',
        'referer',
        'viewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function scopeForArticle(Builder $query, Article|int $article): Builder
    {
        $articleId = $article instanceof Article ? $article->getKey() : $article;

        return $query->where('article_id', $articleId);
    }

    public function scopeMatchingViewer(Builder $query, string $ipHash, string $sessionHash = ''): Builder
    {
        return $query->where(function (Builder $query) use ($ipHash, $sessionHash): void {
            $query->where('ip_hash', $ipHash);

            if ($sessionHash !== '') {
                $query->orWhere('session_hash', $sessionHash);
            }
        });
    }

    public function scopeViewedBetween(Builder $query, DateTimeInterface|string $from, DateTimeInterface|string $to): Builder
    {
        return $query->whereBetween('viewed_at', [$from, $to]);
    }

    public function scopeViewedSince(Builder $query, DateTimeInterface|string $moment): Builder
    {
        return $query->where('viewed_at', '>=', $moment);
    }

    public function scopeWithCountryCode(Builder $query): Builder
    {
        return $query
            ->whereNotNull('country_code')
            ->where('country_code', '!=', '');
    }

    public function scopeWithTimezone(Builder $query): Builder
    {
        return $query
            ->whereNotNull('timezone')
            ->where('timezone', '!=', '');
    }
}
