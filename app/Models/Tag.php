<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use RalphJSmit\Laravel\SEO\Support\SEOData;

class Tag extends Model
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\TagFactory> */
    use HasFactory;

    use HasSEO;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'color',
        'description',
        'usage_count',
        'is_trending',
        'is_featured',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
            'is_trending' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class)->withTimestamps();
    }

    public function scopeTrending(Builder $query): Builder
    {
        return $query->where('is_trending', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->orderByDesc('usage_count');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query
                ->where('name', 'like', $like)
                ->orWhere('slug', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    public function scopeForAdminIndex(Builder $query): Builder
    {
        return $query->withCount('articles');
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * @return array<string, mixed>
     */
    public function getSeoData(): array
    {
        $title = $this->seo?->title ?: '#'.$this->name;
        $description = $this->seo?->description ?: ($this->description ?: 'Подборка материалов по тегу '.$this->name.'.');
        $image = $this->seo?->image;
        $canonicalUrl = $this->seo?->canonical_url ?: route('tag.show', ['slug' => $this->slug]);
        $robots = $this->seo?->robots ?: config('seo.robots.default', 'index, follow');

        return [
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'robots' => $robots,
            'canonical_url' => $canonicalUrl,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $image,
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => $image,
        ];
    }

    public function getDynamicSEOData(): SEOData
    {
        $seoData = $this->getSeoData();

        return new SEOData(
            title: $seoData['title'],
            description: $seoData['description'],
            image: $seoData['image'],
            url: route('tag.show', ['slug' => $this->slug]),
            site_name: config('app.name'),
            robots: $seoData['robots'],
            canonical_url: $seoData['canonical_url'],
            openGraphTitle: $seoData['og_title'],
        );
    }

    protected static function booted(): void
    {
        static::creating(function (Tag $tag): void {
            if (($tag->slug === null || $tag->slug === '') && $tag->name !== null && $tag->name !== '') {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }
}
