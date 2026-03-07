<?php

namespace App\Models;

use Attla\EncodedAttributes\HasEncodedAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bookmark extends Model
{
    use HasEncodedAttributes;

    /** @use HasFactory<\Database\Factories\BookmarkFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_hash',
        'article_id',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
