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
        'prompt_tokens',
        'completion_tokens',
        'cost_usd',
        'generated_at',
        'created_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'pdf_size' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'cost_usd' => 'float',
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

    /**
     * What generations have actually cost, across every match.
     *
     * Only rows that recorded a cost are counted — a report generated before usage tracking
     * existed would otherwise drag the average towards zero.
     *
     * @return array{count: int, total: float, average: float}
     */
    public static function spendSummary(): array
    {
        $priced = static::query()->whereNotNull('cost_usd');
        $count = (clone $priced)->count();

        if ($count === 0) {
            return ['count' => 0, 'total' => 0.0, 'average' => 0.0];
        }

        $total = (float) (clone $priced)->sum('cost_usd');

        return ['count' => $count, 'total' => $total, 'average' => $total / $count];
    }

    /** Enough text to be worth sending to a model — a scanned PDF yields almost nothing. */
    public function hasUsableText(): bool
    {
        return mb_strlen(trim((string) $this->extracted_text)) >= 120;
    }
}
