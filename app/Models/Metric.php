<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Metric extends Model
{
    /** @use HasFactory<\Database\Factories\MetricFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category',
        'measurable_type',
        'measurable_id',
        'bucket_start',
        'bucket_date',
        'fingerprint',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bucket_start' => 'datetime',
            'bucket_date' => 'date',
            'value' => 'integer',
        ];
    }

    public function measurable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForAdminIndex(Builder $query): Builder
    {
        return $query->latest('bucket_start');
    }
}
