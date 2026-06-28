<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionTeamBudget extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'auction_id',
        'actual_team_id',
        'organization_id',
        'budget',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'actual_team_id');
    }
}
