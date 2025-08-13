<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail; // This is the INTERFACE
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait; // This is the TRAIT
use Illuminate\Notifications\Notifiable; // This trait is for the notify() method
use Illuminate\Support\Facades\URL; // For URL::temporarySignedRoute
use Carbon\Carbon; // For Carbon::now()
use Illuminate\Database\Eloquent\Casts\Attribute;

class Player extends Model implements MustVerifyEmail
{
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    // ... (your existing fillable, casts, hidden properties) ...
    protected $fillable = [
        'team_id',
        'name',
        'team_name_ref',
        'email',
        'verified_email',
        'verified_mobile_number_full',
        'verified_cricheroes',
        'verified_jersey_name',
        'verified_kit_size_id',
        'jersey_number',
        'verified_jersey_number',
        'verified_batting_profile_id',
        'verified_bowling_profile_id',
        'verified_player_type_id',
        'verified_name',
        'image_path',
        'mobile_country_code',
        'mobile_national_number',
        'mobile_number_full',
        'cricheroes_country_code',
        'cricheroes_national_number',
        'cricheroes_number_full',
        'verified_cricheroes_number_full',
        'jersey_name',
        'kit_size_id',
        'batting_profile_id',
        'bowling_profile_id',
        'player_type_id',
        'is_wicket_keeper',
        'transportation_required',
        'status',
        'email_verified_at',
        'created_by',
        'approved_by',
        'user_id',
        'verified_image_path',
        'verified_transportation_required',
        'no_travel_plan',
        'verified_no_travel_plan',
        'travel_date_from',
        'travel_date_to',
        'location_id',
        'total_matches',
        'total_runs',
        'total_wickets',
        'welcome_email_sent_at',
    ];

    protected $casts = [
        'verified_name' => 'boolean',
        'verified_email' => 'boolean',
        'verified_mobile_country_code' => 'boolean',
        'verified_mobile_national_number' => 'boolean',
        'verified_mobile_number_full' => 'boolean',
        'verified_cricheroes_country_code' => 'boolean',
        'verified_cricheroes_national_number' => 'boolean',
        'verified_cricheroes_number_full' => 'boolean',
        'verified_team_id' => 'boolean',
        'verified_jersey_name' => 'boolean',
        'verified_jersey_number' => 'boolean',
        'verified_kit_size_id' => 'boolean',
        'verified_batting_profile_id' => 'boolean',
        'verified_bowling_profile_id' => 'boolean',
        'verified_player_type_id' => 'boolean',
        'verified_image_path' => 'boolean',
        'verified_is_wicket_keeper' => 'boolean',
        'verified_transportation_required' => 'boolean',
        'verified_no_travel_plan' => 'boolean',

        'email_verified_at' => 'datetime',
        'is_wicket_keeper' => 'boolean',
        'transportation_required' => 'boolean',
    ];

    protected $hidden = [
        // 'password', // If players ever have passwords
    ];

    // ... (your existing relationships) ...
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function kitSize()
    {
        return $this->belongsTo(KitSize::class);
    }

    public function battingProfile()
    {
        return $this->belongsTo(BattingProfile::class);
    }

    public function bowlingProfile()
    {
        return $this->belongsTo(BowlingProfile::class);
    }

    public function playerType()
    {
        return $this->belongsTo(PlayerType::class);
    }

    public function teamAssignments()
    {
        return $this->belongsToMany(Team::class, 'player_team_tournament')
            ->withPivot('tournament_id')
            ->withTimestamps();
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'player_team_tournament')
            ->withPivot('tournament_id')
            ->withTimestamps();
    }

    public function appreciation()
    {
        return $this->hasOne(PlayerAppreciation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Status helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }


    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }


    /**
     * Get the email verification URL for the player.
     * This overrides the default method from the MustVerifyEmail trait.
     *
     * @return string
     */
    public function verificationUrl()
    {
        // Use your custom named route for player verification
        return URL::temporarySignedRoute(
            'verification.verify.player', // Your custom player verification route name
            Carbon::now()->addMinutes(config('auth.verification.expire', 60)), // Expiration time
            [
                'id' => $this->getKey(), // The ID of the Player
                'hash' => sha1($this->getEmailForVerification()), // A hash of the email
            ]
        );
    }

    /**
     * Get the email address that should be used for verification.
     * Required by MustVerifyEmail contract.
     *
     * @return string
     */
    public function getEmailForVerification()
    {
        return $this->email;
    }


    public function getVerifiedFieldsAttribute()
    {
        $allFields = [
            'team_id',
            'name',
            'team_name_ref',
            'email',
            'image',
            'image_path',
            'layout_json',
            'welcome_image_path',
            'mobile_country_code',
            'mobile_national_number',
            'mobile_number_full',
            'cricheroes_country_code',
            'cricheroes_national_number',
            'cricheroes_number_full',
            'jersey_name',
            'kit_size_id',
            'batting_profile_id',
            'bowling_profile_id',
            'player_type_id',
            'status',
            'email_verified_at',
            'verified_jersey_number',
            'created_by',
            'approved_by',
            'user_id',
            'is_wicket_keeper',
            'transportation_required'
        ];

        $verifiedStatuses = [];

        foreach ($allFields as $field) {
            $verifiedKey = 'verified_' . $field;
            $verifiedStatuses[$field] = $this->$verifiedKey ?? false;
        }

        return $verifiedStatuses;
    }

    public function allFieldsVerified(): bool
    {
        return
            $this->verified_name &&
            $this->verified_email &&
            $this->verified_image_path &&
            $this->verified_mobile_number_full &&
            $this->verified_cricheroes_number_full &&
            $this->verified_jersey_name &&
            $this->verified_jersey_number &&
            $this->verified_team_id &&
            $this->verified_kit_size_id &&
            $this->verified_batting_profile_id &&
            $this->verified_bowling_profile_id &&
            $this->verified_player_type_id &&
            $this->verified_is_wicket_keeper &&
            $this->verified_transportation_required;
    }
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }


    public function match()
    {
        return $this->belongsTo(Matches::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function actualTeams()
    {
        return $this->belongsToMany(ActualTeam::class, 'player_team_tournament', 'player_id', 'team_id')
            ->withPivot('role');
    }

    public function location()
    {
        // This line tells Laravel:
        // "This Player model belongs to a PlayerLocation model,
        // and you can find the correct one by matching the 'location_id' on this player
        // with the 'id' on the player_locations table."
        return $this->belongsTo(PlayerLocation::class, 'location_id');
    }


    protected function displayTeamName(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                // Check if the player is retained and if their user has an actual team.
                if ($attributes['player_mode'] === 'retained' && $this->user?->actualTeams->first()) {
                    return $this->user->actualTeams->first()->name;
                }

                // Check if the registration team is 'Others' and show team_name_ref.
                if ($this->team?->name === 'Others') {
                    return $this->team_name_ref;
                }

                // Otherwise, return the normal registration team name.
                return $this->team?->name;
            }
        );
    }
}
