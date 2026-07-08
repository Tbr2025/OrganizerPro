<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\AuctionTeamBudget;
use Illuminate\Support\Collection;

/**
 * Pool ordering ("lots") and per-team budget maths for the auction.
 */
class AuctionPoolService
{
    /**
     * (Re)draw lot numbers for every player in a pool according to its order mode.
     * lot_number is 1..N in the drawn order.
     */
    public function generateLotNumbers(AuctionPool $pool): void
    {
        // Only biddable (non-retained) players get a draw position.
        $players = $pool->players()->where('is_retained', false)->orderBy('id')->get();
        $ordered = $this->orderPlayers($players, $pool->order_mode);

        foreach ($ordered->values() as $i => $auctionPlayer) {
            $auctionPlayer->update(['lot_number' => $i + 1]);
        }
    }

    /**
     * Return the pool's players in the order implied by the given mode.
     */
    public function orderPlayers(Collection $players, string $mode): Collection
    {
        $list = $players->values();

        return match ($mode) {
            AuctionPool::MODE_RANDOM => $list->shuffle()->values(),
            AuctionPool::MODE_ODD_EVEN => $this->oddEvenOrder($list),
            // Manual keeps the operator-assigned lot_number ordering (nulls last).
            AuctionPool::MODE_MANUAL => $list->sortBy(fn ($p) => $p->lot_number ?? PHP_INT_MAX)->values(),
            // Sequential = insertion order (already sorted by id).
            default => $list,
        };
    }

    /**
     * Odd positions first, then even: indices 0,2,4,… then 1,3,5,…
     * (1st, 3rd, 5th players drawn first, then 2nd, 4th, 6th).
     */
    protected function oddEvenOrder(Collection $list): Collection
    {
        $odd = [];
        $even = [];
        foreach ($list->values() as $i => $player) {
            if ($i % 2 === 0) {
                $odd[] = $player;
            } else {
                $even[] = $player;
            }
        }

        return collect(array_merge($odd, $even));
    }

    /**
     * The next player to auction, respecting pool sequence then lot order,
     * limited to players still waiting.
     */
    public function nextPlayer(Auction $auction): ?AuctionPlayer
    {
        return AuctionPlayer::query()
            ->where('auction_players.auction_id', $auction->id)
            ->where('auction_players.status', 'waiting')
            ->where('auction_players.is_retained', false)
            ->whereNotNull('auction_players.auction_pool_id')
            ->join('auction_pools', 'auction_pools.id', '=', 'auction_players.auction_pool_id')
            ->orderBy('auction_pools.sequence')
            ->orderByRaw('auction_players.lot_number IS NULL, auction_players.lot_number')
            ->select('auction_players.*')
            ->first();
    }

    /**
     * Budget & team-budget conditions apply ONLY to auction-type tournaments.
     * Open tournaments have no budget mechanics.
     */
    public function budgetApplies(Auction $auction): bool
    {
        return $auction->tournament?->isAuction() ?? true;
    }

    /** Per-team allocation, falling back to the auction-wide uniform cap. */
    public function allocatedBudget(Auction $auction, int $actualTeamId): float
    {
        $row = AuctionTeamBudget::where('auction_id', $auction->id)
            ->where('actual_team_id', $actualTeamId)
            ->first();

        if ($row) {
            return (float) $row->budget;
        }

        return (float) ($auction->max_budget_per_team ?? 0);
    }

    /** Amount a team spent on players SOLD to it in the live auction. */
    public function soldSpent(Auction $auction, int $actualTeamId): float
    {
        return (float) AuctionPlayer::where('auction_id', $auction->id)
            ->where('status', 'sold')
            ->where('sold_to_team_id', $actualTeamId)
            ->sum('final_price');
    }

    /** Retention cost of a team's retained players — counts against its budget up front. */
    public function retainedSpent(Auction $auction, int $actualTeamId): float
    {
        return (float) AuctionPlayer::where('auction_id', $auction->id)
            ->where('is_retained', true)
            ->where('team_id', $actualTeamId)
            ->sum('retained_price');
    }

    /** Total committed by a team: sold purchases + retained-player costs. */
    public function spent(Auction $auction, int $actualTeamId): float
    {
        return $this->soldSpent($auction, $actualTeamId) + $this->retainedSpent($auction, $actualTeamId);
    }

    public function remainingBudget(Auction $auction, int $actualTeamId): float
    {
        if (! $this->budgetApplies($auction)) {
            return PHP_FLOAT_MAX; // open tournaments: no budget cap
        }

        return $this->allocatedBudget($auction, $actualTeamId) - $this->spent($auction, $actualTeamId);
    }

    public function canAfford(Auction $auction, int $actualTeamId, float $amount): bool
    {
        return $amount <= $this->remainingBudget($auction, $actualTeamId);
    }
}
