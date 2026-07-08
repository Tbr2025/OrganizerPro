<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentCustomField extends Model
{
    protected $fillable = [
        'tournament_id',
        'label',
        'type',
        'options',
        'section',
        'required',
        'visible',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Supported field types → human label. */
    public const TYPES = [
        'text' => 'Text',
        'textarea' => 'Paragraph',
        'number' => 'Number',
        'dropdown' => 'Dropdown',
        'checkbox' => 'Checkbox (Yes/No)',
        'date' => 'Date',
    ];

    /** Storage/verification key for this custom field (namespaced to avoid clashes). */
    public function getKeyNameAttribute(): string
    {
        return 'cf_' . $this->id;
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
