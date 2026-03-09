<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'rss_url',
        'rss_key',
        'color',
        'icon',
        'meta_title',
        'meta_description',
        'description',
        'order',
        'is_active',
        'show_in_menu',
        'articles_count_cache',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_menu' => 'boolean',
        ];
    }

    public function subCategories(): HasMany
    {
        return $this->hasMany(SubCategory::class);
    }

    public function activeSubCategories(): HasMany
    {
        return $this->subCategories()
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('name');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function rssFeeds(): HasMany
    {
        return $this->hasMany(RssFeed::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('order');
    }

    public function scopeInMenu(Builder $query): Builder
    {
        return $query->where('show_in_menu', true);
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
                ->orWhere('rss_key', 'like', $like)
                ->orWhere('meta_title', 'like', $like);
        });
    }

    public function scopeForAdminIndex(Builder $query): Builder
    {
        return $query->withCount(['articles', 'subCategories', 'rssFeeds']);
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if (($category->slug === null || $category->slug === '') && $category->name !== null && $category->name !== '') {
                $category->slug = Str::slug($category->name);
            }
        });
    }
}
