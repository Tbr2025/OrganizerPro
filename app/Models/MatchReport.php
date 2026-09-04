<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A CricHeroes match-report PDF and the blog post drafted from it.
 */
class MatchReport extends Model
{
    protected $fillable = [
        'match_id',
        'pdf_path',
        'pdf_name',
        'pdf_size',
        'extracted_text',
        'post_id',
        'model',
        'generated_at',
        'created_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'pdf_size' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Enough text to be worth sending to a model — a scanned PDF yields almost nothing. */
    public function hasUsableText(): bool
    {
        return mb_strlen(trim((string) $this->extracted_text)) >= 120;
    }
}
