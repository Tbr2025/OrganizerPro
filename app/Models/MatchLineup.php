<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One player named in one team's XI for one match.
 *
 * See the create migration for why this exists: a squad belongs to a tournament, so until now
 * nothing recorded who was actually playing on the day.
 */
class MatchLineup extends Model
{
    protected $fillable = [
        'match_id',
        'actual_team_id',
        'player_id',
        'batting_order',
        'role',
        'created_by',
    ];

    protected $casts = [
        'batting_order' => 'integer',
    ];

    /** The marks a line-up graphic shows. Anything else is not a role, it is a note. */
    public const ROLES = ['C' => 'Captain', 'VC' => 'Vice-captain', 'WK' => 'Wicket-keeper'];

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'actual_team_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** Normalise a submitted role: anything not C/VC/WK is stored as no role at all. */
    public static function normaliseRole(?string $role): ?string
    {
        $role = strtoupper(trim((string) $role));

        return array_key_exists($role, self::ROLES) ? $role : null;
    }
}
