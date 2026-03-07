<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
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
}
