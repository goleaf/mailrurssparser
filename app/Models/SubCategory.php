<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SubCategory extends Model
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\SubCategoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
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
            ->withCount('articles')
            ->orderBy('order')
            ->orderBy('name');
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
