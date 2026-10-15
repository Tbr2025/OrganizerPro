<?php

declare(strict_types=1);

namespace App\Models\Credits;

use Illuminate\Database\Eloquent\Model;

/** A chargeable feature and its price, set by Super Admin. */
class CreditFeature extends Model
{
    public const TOURNAMENT_PUBLISH = 'tournament_publish';
    public const POSTER = 'poster';
    public const AUCTION = 'auction_access';
    public const LEDWALL = 'ledwall';
    public const SCORING = 'scoring';

    protected $fillable = ['key', 'name', 'description', 'cost', 'charge_trigger', 'scope', 'is_active', 'sort'];

    protected $casts = ['cost' => 'integer', 'is_active' => 'boolean'];
}
