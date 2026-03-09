<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RssParseLog extends Model
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\RssParseLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rss_feed_id',
        'started_at',
        'finished_at',
        'new_count',
        'skip_count',
        'error_count',
        'total_items',
        'duration_ms',
        'success',
        'error_message',
        'item_errors',
        'triggered_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'success' => 'boolean',
            'item_errors' => 'array',
        ];
    }

    public function rssFeed(): BelongsTo
    {
        return $this->belongsTo(RssFeed::class);
    }

    public function scopeRunningAt(Builder $query, DateTimeInterface|string|null $moment = null): Builder
    {
        $moment = $this->normalizeMoment($moment);

        return $query
            ->where('started_at', '<=', $moment)
            ->where(function (Builder $query) use ($moment): void {
                $query->whereNull('finished_at')
                    ->orWhere(function (Builder $query) use ($moment): void {
                        $query->whereNotNull('finished_at')
                            ->whereValueBetween($moment, ['started_at', 'finished_at']);
                    });
            });
    }

    public function scopeOverlappingWindow(
        Builder $query,
        DateTimeInterface|string $from,
        DateTimeInterface|string $to,
    ): Builder {
        $from = $this->normalizeMoment($from);
        $to = $this->normalizeMoment($to);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return $query->where(function (Builder $query) use ($from, $to): void {
            $query->whereBetween('started_at', [$from, $to])
                ->orWhere(function (Builder $query) use ($from): void {
                    $query->runningAt($from);
                });
        });
    }

    public function scopeForAdminIndex(Builder $query): Builder
    {
        return $query
            ->with(['rssFeed.category']);
    }

    private function normalizeMoment(DateTimeInterface|string|null $moment): CarbonImmutable
    {
        if ($moment instanceof DateTimeInterface) {
            return CarbonImmutable::instance($moment);
        }

        return CarbonImmutable::parse($moment ?? now());
    }
}
