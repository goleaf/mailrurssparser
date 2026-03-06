<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleView extends Model
{
    /** @use HasFactory<\Database\Factories\ArticleViewFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'article_id',
        'ip_address',
        'session_id',
        'user_agent',
        'referer',
        'viewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
