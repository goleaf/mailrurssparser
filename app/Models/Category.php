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
        'description',
        'order',
        'is_active',
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

    public function subCategories(): HasMany
    {
        return $this->hasMany(SubCategory::class);
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

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if (($category->slug === null || $category->slug === '') && $category->name !== null && $category->name !== '') {
                $category->slug = Str::slug($category->name);
            }
        });
    }
}
