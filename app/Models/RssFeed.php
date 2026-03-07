<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RssFeed extends Model
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\RssFeedFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'title',
        'url',
        'source_name',
        'language',
        'is_active',
        'auto_publish',
        'auto_featured',
        'fetch_interval',
        'last_parsed_at',
        'next_parse_at',
        'articles_parsed_total',
        'last_run_new_count',
        'last_run_skip_count',
        'last_run_error_count',
        'consecutive_failures',
        'last_error',
        'extra_settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'auto_publish' => 'boolean',
            'auto_featured' => 'boolean',
            'last_parsed_at' => 'datetime',
            'next_parse_at' => 'datetime',
            'extra_settings' => 'array',
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

    public function parseLogs(): HasMany
    {
        return $this->hasMany(RssParseLog::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDueForParsing(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('next_parse_at')
                    ->orWhere('next_parse_at', '<=', now());
            });
    }

    public function markParsed(int $new, int $skip, int $errors): void
    {
        $this->forceFill([
            'last_parsed_at' => now(),
            'next_parse_at' => now()->addMinutes($this->fetch_interval ?: 15),
            'articles_parsed_total' => $this->articles_parsed_total + $new,
            'last_run_new_count' => $new,
            'last_run_skip_count' => $skip,
            'last_run_error_count' => $errors,
            'consecutive_failures' => 0,
            'last_error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $failures = $this->consecutive_failures + 1;

        $this->forceFill([
            'consecutive_failures' => $failures,
            'last_error' => $error,
            'last_run_error_count' => max(1, $this->last_run_error_count),
            'next_parse_at' => now()->addMinutes($this->fetch_interval ?: 15),
            'is_active' => $failures < 10,
        ])->save();
    }
}
