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
        'billing_mode',
    ];

    protected $casts = [
        'auction_enabled' => 'boolean',
        'auction_modes' => 'array',
        'max_tournaments' => 'integer',
    ];

    /**
     * Every organization pays per feature in credits. The old package columns
     * (package_type, max_tournaments, auction_enabled, auction_modes, billing_mode)
     * are kept in the table but no longer limit anything.
     */
    public function usesCredits(): bool
    {
        return true;
    }

    public function wallet()
    {
        return $this->hasOne(\App\Models\Credits\CreditWallet::class);
    }

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

    /** No tournament cap: creating is free and publishing costs credits. */
    public function hasReachedTournamentLimit(): bool
    {
        return false;
    }

    public function remainingTournamentSlots(): ?int
    {
        return null;
    }

    /** Auctions are unlocked per tournament with credits (CreditFeature::AUCTION). */
    public function isAuctionEnabled(): bool
    {
        return true;
    }

    public function isAuctionModeAllowed(string $mode): bool
    {
        return true;
    }
}
