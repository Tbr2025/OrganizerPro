<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named person allowed to run one auction, and what they may do in it.
 *
 * See the migration for why this exists alongside the permissions rather than instead of them:
 * a permission says what a role may do, this says which auction they may do it on.
 */
class AuctionOperator extends Model
{
    /** See the board and every figure on it, and nothing else. */
    public const ABILITY_OBSERVE = 'observe';

    /** Take bids, correct the price, run the clock. The auctioneer's own job. */
    public const ABILITY_CONTROL = 'control';

    /** Sell, pass, mark unsold, undo. Ends a lot, so it is deliberately separate from control. */
    public const ABILITY_SELL = 'sell';

    /** Start, reopen and close pools. */
    public const ABILITY_POOLS = 'pools';

    /** The wall and the ticker — boards, breaks, artwork. */
    public const ABILITY_SCREENS = 'screens';

    protected $fillable = ['auction_id', 'user_id', 'abilities'];

    protected $casts = ['abilities' => 'array'];

    /**
     * Every ability, with the sentence an organizer reads when granting it.
     *
     * @return array<string, string>
     */
    public static function abilities(): array
    {
        return [
            self::ABILITY_OBSERVE => 'Watch the panel — every figure, no controls',
            self::ABILITY_CONTROL => 'Take bids and correct the price',
            self::ABILITY_SELL => 'Sell, pass and undo',
            self::ABILITY_POOLS => 'Start and reopen pools',
            self::ABILITY_SCREENS => 'Control the wall and the ticker',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Does this operator hold an ability?
     *
     * `observe` is implied by every other one: somebody who can take bids can obviously see the
     * board they are bidding on, and requiring it to be ticked separately is a trap that would
     * lock an auctioneer out of the screen they were just granted control of.
     */
    public function can(string $ability): bool
    {
        $held = $this->abilities ?? [];

        if ($ability === self::ABILITY_OBSERVE) {
            return ! empty($held);
        }

        return in_array($ability, $held, true);
    }
}
