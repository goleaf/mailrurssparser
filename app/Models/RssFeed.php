<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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

    public function metrics(): MorphMany
    {
        return $this->morphMany(Metric::class, 'measurable');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInCategory(Builder $query, Category|int $category): Builder
    {
        $categoryId = $category instanceof Category ? $category->getKey() : $category;

        return $query->where('category_id', $categoryId);
    }

    public function scopeParsed(Builder $query): Builder
    {
        return $query->whereNotNull('last_parsed_at');
    }

    public function scopeWithErrors(Builder $query): Builder
    {
        return $query
            ->whereNotNull('last_error')
            ->where('last_error', '!=', '');
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

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query
                ->where('title', 'like', $like)
                ->orWhere('url', 'like', $like)
                ->orWhere('source_name', 'like', $like)
                ->orWhere('last_error', 'like', $like);
        });
    }

    public function scopeForAdminIndex(Builder $query): Builder
    {
        return $query
            ->with('category')
            ->withCount(['articles', 'parseLogs']);
    }

    protected function categoryName(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->category?->name);
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::get(function (): string {
            return match (true) {
                ! $this->is_active => 'Disabled',
                $this->last_error !== null && $this->last_error !== '' => 'Error',
                $this->next_parse_at !== null && $this->next_parse_at->lte(now()) => 'Due',
                default => 'OK',
            };
        });
    }

    public static function sanitizeSourceName(?string $value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim((string) preg_replace('/^[©\s]+/u', '', (string) $value));
        $value = trim($value, " \t\n\r\0\x0B\"'`«»„“”‚‘’");
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*-\s*Новости$/u', '', $value) ?? $value;
        $value = preg_replace('/\s+-\s+.+$/u', '', $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B\"'`«»„“”‚‘’");

        if ($value === '') {
            return '';
        }

        return in_array(strtolower($value), self::genericSourceNames(), true)
            ? ''
            : $value;
    }

    /**
     * @return array<int, string>
     */
    private static function genericSourceNames(): array
    {
        return [
            strtolower((string) config('rss.feed_host', implode('.', ['news', 'mail', 'ru']))),
            strtolower(implode('.', ['mail', 'ru'])),
            'новости mail',
            'спорт mail',
            'погода mail',
        ];
    }

    protected function sourceName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => static::sanitizeSourceName($value),
            set: fn (?string $value): string => static::sanitizeSourceName($value),
        );
    }

    public function markParsed(int $new, int $skip, int $errors): void
    {
        $this->forceFill([
            'last_parsed_at' => now(),
            'next_parse_at' => now()->plus(minutes: $this->fetch_interval ?: 15),
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
            'next_parse_at' => now()->plus(minutes: $this->fetch_interval ?: 15),
            'is_active' => $failures < 10,
        ])->save();
    }
}
