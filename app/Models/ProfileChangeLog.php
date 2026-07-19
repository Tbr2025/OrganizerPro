<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileChangeLog extends Model
{
    public const ACTION_SUBMITTED = 'submitted';
    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_ADMIN_EDIT = 'admin_edit';
    public const ACTION_VERIFIED = 'verified';

    protected $fillable = [
        'player_id',
        'tournament_registration_id',
        'tournament_id',
        'changed_by',
        'action',
        'changes',
        'notes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'tournament_registration_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function scopeOfAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('action', self::ACTION_SUBMITTED);
    }

    public function scopeApproved($query)
    {
        return $query->where('action', self::ACTION_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('action', self::ACTION_REJECTED);
    }

    /**
     * Record a profile change log entry.
     */
    public static function record(
        int $playerId,
        string $action,
        ?array $changes = null,
        ?int $registrationId = null,
        ?int $tournamentId = null,
        ?int $changedBy = null,
        ?string $notes = null
    ): self {
        return static::create([
            'player_id' => $playerId,
            'action' => $action,
            'changes' => $changes,
            'tournament_registration_id' => $registrationId,
            'tournament_id' => $tournamentId,
            'changed_by' => $changedBy ?? auth()->id(),
            'notes' => $notes,
        ]);
    }
}
