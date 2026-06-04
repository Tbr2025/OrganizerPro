<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'package_type',
        'max_tournaments',
        'auction_enabled',
        'auction_modes',
    ];

    protected $casts = [
        'auction_enabled' => 'boolean',
        'auction_modes' => 'array',
        'max_tournaments' => 'integer',
    ];

    public function locations()
    {
        return $this->hasMany(PlayerLocation::class);
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function actualTeams()
    {
        return $this->hasMany(ActualTeam::class, 'organization_id');
    }

    public function hasReachedTournamentLimit(): bool
    {
        if (is_null($this->max_tournaments)) {
            return false;
        }

        return $this->tournaments()->count() >= $this->max_tournaments;
    }

    public function remainingTournamentSlots(): ?int
    {
        if (is_null($this->max_tournaments)) {
            return null;
        }

        return max(0, $this->max_tournaments - $this->tournaments()->count());
    }

    public function isAuctionEnabled(): bool
    {
        return $this->auction_enabled;
    }

    public function isAuctionModeAllowed(string $mode): bool
    {
        if (!$this->auction_enabled) {
            return false;
        }

        if (empty($this->auction_modes)) {
            return true;
        }

        return in_array($mode, $this->auction_modes);
    }

    public function getPackageLabelAttribute(): string
    {
        return match ($this->package_type) {
            'premium' => 'Premium',
            'enterprise' => 'Enterprise',
            default => 'Starter',
        };
    }

    public function getPackageBadgeClassAttribute(): string
    {
        return match ($this->package_type) {
            'premium' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            'enterprise' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }
}
