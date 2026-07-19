<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ActualTeam;
use App\Models\BattingProfile;
use App\Models\BowlingProfile;
use App\Models\PlayerType;
use App\Models\PlayerLocation;
use App\Models\KitSize;

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
     * Format raw changes array for human-readable display.
     *
     * Maps raw column names to labels, resolves foreign key IDs to names,
     * formats boolean/special values, and skips empty values.
     */
    public static function formatChangesForDisplay(array $changes): array
    {
        $labels = [
            'name' => 'Name',
            'mobile_number_full' => 'Mobile Number',
            'jersey_name' => 'Jersey Name',
            'cricheroes_number_full' => 'CricHeroes Number',
            'cricheroes_profile_url' => 'CricHeroes Profile',
            'jersey_number' => 'Jersey Number',
            'team_name_ref' => 'Registration Team',
            'location_id' => 'Location',
            'total_matches' => 'Total Matches',
            'total_runs' => 'Total Runs',
            'total_wickets' => 'Total Wickets',
            'travel_date_from' => 'Travel From',
            'travel_date_to' => 'Travel To',
            'no_travel_plan' => 'No Travel Plan',
            'tshirt_size' => 'T-Shirt Size',
            'pant_size' => 'Pant Size',
            'batting_profile_id' => 'Batting Profile',
            'bowling_profile_id' => 'Bowling Profile',
            'player_type_id' => 'Player Type',
            'kit_size_id' => 'Kit Size',
            'actual_team_id' => 'Team',
            'is_wicket_keeper' => 'Wicket Keeper',
            'transportation_required' => 'Transportation',
            'image_path' => 'Profile Photo',
            'email' => 'Email',
            'country' => 'Country',
            'visa_status' => 'Visa Status',
        ];

        $formatted = [];

        foreach ($changes as $field => $value) {
            // Skip empty/null values
            if (is_null($value) || $value === '') {
                continue;
            }

            $label = $labels[$field] ?? ucwords(str_replace('_', ' ', $field));

            // Format the display value
            $displayValue = match ($field) {
                'is_wicket_keeper' => $value ? 'Yes' : 'No',
                'transportation_required' => $value ? 'Yes' : 'No',
                'no_travel_plan' => $value ? 'No travel' : 'Has travel plan',
                'image_path' => 'Updated',
                'actual_team_id' => optional(ActualTeam::find($value))->name ?? (string) $value,
                'batting_profile_id' => optional(BattingProfile::find($value))->name ?? (string) $value,
                'bowling_profile_id' => optional(BowlingProfile::find($value))->name ?? (string) $value,
                'player_type_id' => optional(PlayerType::find($value))->name ?? (string) $value,
                'location_id' => optional(PlayerLocation::find($value))->name ?? (string) $value,
                'kit_size_id' => optional(KitSize::find($value))->name ?? (string) $value,
                default => is_array($value) ? json_encode($value) : (string) $value,
            };

            $formatted[$label] = $displayValue;
        }

        return $formatted;
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
