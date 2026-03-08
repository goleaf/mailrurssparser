<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\NewsletterSubscriberFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'name',
        'category_ids',
        'token',
        'confirmed',
        'confirmed_at',
        'unsubscribed_at',
        'ip_address',
        'country_code',
        'timezone',
        'locale',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_ids' => 'array',
            'confirmed' => 'boolean',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('confirmed', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('confirmed', true)->whereNull('unsubscribed_at');
    }

    protected static function booted(): void
    {
        static::creating(function (NewsletterSubscriber $subscriber): void {
            if ($subscriber->token === null || $subscriber->token === '') {
                $subscriber->token = Str::random(64);
            }
        });
    }
}
