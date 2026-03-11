<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use RalphJSmit\Laravel\SEO\Support\SEOData;

class SubCategory extends Model
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\SubCategoryFactory> */
    use HasFactory;

    use HasSEO;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'is_active',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('order')->orderBy('name');
    }

    public function scopeInCategory(Builder $query, Category|int $category): Builder
    {
        $categoryId = $category instanceof Category ? $category->getKey() : $category;

        return $query->where('category_id', $categoryId);
    }

    public function scopeByCategory(Builder $query, Category|int $category): Builder
    {
        return $query->inCategory($category);
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query
            ->withCount('articles')
            ->orderByDesc('articles_count')
            ->orderBy('name');
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
        return $query
            ->with('category')
            ->withCount('articles');
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::query()
            ->where('slug', $slug)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSeoData(): array
    {
        $title = $this->seo?->title ?: $this->name;
        $description = $this->seo?->description ?: Str::limit((string) $this->description, 160);
        $image = $this->seo?->image;
        $canonicalUrl = $this->seo?->canonical_url;

        if (($canonicalUrl === null || $canonicalUrl === '') && $this->category !== null) {
            $canonicalUrl = route('category.show', [
                'slug' => $this->category->slug,
                'sub' => $this->slug,
            ]);
        }

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
            'meta_title' => $title,
            'meta_description' => $description,
        ];
    }

    public function getDynamicSEOData(): SEOData
    {
        $seoData = $this->getSeoData();

        return new SEOData(
            title: $seoData['title'],
            description: $seoData['description'],
            image: $seoData['image'],
            url: $seoData['canonical_url'],
            site_name: config('app.name'),
            robots: $seoData['robots'],
            canonical_url: $seoData['canonical_url'],
            openGraphTitle: $seoData['og_title'],
        );
    }

    protected static function booted(): void
    {
        static::creating(function (SubCategory $subCategory): void {
            if (($subCategory->slug === null || $subCategory->slug === '') && $subCategory->name !== null && $subCategory->name !== '') {
                $subCategory->slug = Str::slug($subCategory->name);
            }
        });
    }
}
