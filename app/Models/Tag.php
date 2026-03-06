<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\TagFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'color',
        'usage_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
        ];
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
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
