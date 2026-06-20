<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $slug
 * @property string $source
 * @property array  $variants
 * @property bool   $is_active
 */
class Font extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'source',
        'variants',
        'is_active',
    ];

    protected $casts = [
        'variants'  => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Pick the best variant file for a requested weight/style.
     *
     * Falls back gracefully: exact (weight+style) → same style nearest weight
     * → any nearest weight. Returns the stored file path (relative to
     * public/fonts) or null if the font has no variants.
     */
    public function resolveVariantFile(int $weight, string $style = 'normal'): ?string
    {
        $variants = collect($this->variants ?? [])->filter(fn ($v) => !empty($v['file']));
        if ($variants->isEmpty()) {
            return null;
        }

        // 1. Exact weight + style match.
        $exact = $variants->first(
            fn ($v) => (int) ($v['weight'] ?? 400) === $weight
                && ($v['style'] ?? 'normal') === $style
        );
        if ($exact) {
            return $exact['file'];
        }

        // 2. Nearest weight within the same style.
        $sameStyle = $variants->filter(fn ($v) => ($v['style'] ?? 'normal') === $style);
        $pool = $sameStyle->isNotEmpty() ? $sameStyle : $variants;

        $nearest = $pool->sortBy(fn ($v) => abs(((int) ($v['weight'] ?? 400)) - $weight))->first();

        return $nearest['file'] ?? null;
    }
}
