<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Events\PlayerSoldEvent;
use App\Models\ActualTeam;
use App\Models\AuctionPlayer;
use App\Models\AuctionPendingEmail;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

/**
 * The single path a player takes when they are acquired by a team — whether via
 * an open-bid SELL, a sealed-bid award, or a post-auction allotment.
 *
 * Previously sellPlayer() wrote five stores (auction_players, players,
 * player_actual_team_tournament, actual_team_users, Spatie roles) while
 * sellToTeam() wrote only two, so every sealed-bid and offline sale was missing
 * from the team's tournament roster. Both now come through here.
 *
 * Every sale captures a snapshot of the state it replaced so that Undo can put
 * it back exactly — see revert().
 */
class AuctionSaleService
{
    public function __construct(
        private readonly AuctionPoolService $pools,
    ) {
    }

    /**
     * Snapshot everything applySale() is about to overwrite.
     *
     * Taken *before* the sale, stored on the action log, and handed back to
     * revert(). Captures prior Spatie roles specifically because the sale used
     * to call syncRoles(['Player']), which silently stripped a Team Manager of
     * their manager role with no way back.
     *
     * @param  int|null  $buyingTeamId  Team about to acquire the player — needed to
     *                                  tell "the sale added this roster row" from
     *                                  "they were already on that roster".
     * @return array<string, mixed>
     */
    public function snapshot(AuctionPlayer $auctionPlayer, ?int $buyingTeamId = null): array
    {
        $auctionPlayer->loadMissing('player.user');
        $player = $auctionPlayer->player;
        $tournamentId = $auctionPlayer->auction?->tournament_id;

        $pivot = null;
        if ($player && $tournamentId) {
            $pivot = DB::table('player_actual_team_tournament')
                ->where('player_id', $player->id)
                ->where('tournament_id', $tournamentId)
                ->first();
        }

        $rosterTeamId = $buyingTeamId ?? $auctionPlayer->sold_to_team_id;
        $rosterRole = null;
        $rosterExisted = false;
        if ($player?->user_id && $rosterTeamId) {
            $row = DB::table('actual_team_users')
                ->where('actual_team_id', $rosterTeamId)
                ->where('user_id', $player->user_id)
                ->first();
            $rosterExisted = $row !== null;
            $rosterRole = $row->role ?? null;
        }

        return [
            'auction_player' => [
                'status' => $auctionPlayer->status,
                'sold_to_team_id' => $auctionPlayer->sold_to_team_id,
                'final_price' => $auctionPlayer->final_price,
                'current_price' => $auctionPlayer->current_price,
                'current_bid_team_id' => $auctionPlayer->current_bid_team_id,
                'sold_at' => $auctionPlayer->sold_at,
            ],
            'player' => $player ? [
                'id' => $player->id,
                'player_mode' => $player->player_mode,
                'actual_team_id' => $player->actual_team_id,
                'user_id' => $player->user_id,
            ] : null,
            'tournament_id' => $tournamentId,
            'pivot' => $pivot ? [
                'existed' => true,
                'actual_team_id' => $pivot->actual_team_id,
            ] : ['existed' => false],
            'roster' => [
                'existed' => $rosterExisted,
                'role' => $rosterRole,
            ],
            'roles' => $player?->user
                ? $player->user->getRoleNames()->all()
                : [],
        ];
    }

    /**
     * Apply a sale: mark the player sold and write every downstream store.
     *
     * @param  string  $mode  'sold' for a normal sale, 'allot' for a post-auction allotment
     * @return array<string, mixed> The pre-sale snapshot, for the action log
     */
    public function applySale(
        AuctionPlayer $auctionPlayer,
        ActualTeam $team,
        float $amount,
        string $mode = 'sold'
    ): array {
        $auctionPlayer->loadMissing('auction');
        $auction = $auctionPlayer->auction;
        $snapshot = $this->snapshot($auctionPlayer, $team->id);
        $snapshot['sale_mode'] = $mode;

        DB::transaction(function () use ($auctionPlayer, $team, $amount, $auction) {
            $auctionPlayer->update([
                'status' => 'sold',
                'sold_to_team_id' => $team->id,
                'final_price' => $amount,
                'current_price' => $amount,
                'current_bid_team_id' => $team->id,
                // When the sale happened. `updated_at` cannot answer this — any later edit
                // moves it — and reports need a date that stays put.
                'sold_at' => now(),
            ]);

            $player = $auctionPlayer->player;
            if (! $player) {
                return;
            }

            $player->update([
                'player_mode' => 'retained',
                'actual_team_id' => $team->id,
            ]);

            if ($auction?->tournament_id) {
                DB::table('player_actual_team_tournament')->updateOrInsert(
                    ['player_id' => $player->id, 'tournament_id' => $auction->tournament_id],
                    ['actual_team_id' => $team->id, 'created_at' => now(), 'updated_at' => now()]
                );
            }

            if ($player->user_id) {
                $team->users()->syncWithoutDetaching([
                    $player->user_id => ['role' => 'Player'],
                ]);

                $user = $player->user;
                // assignRole(), not syncRoles(): a Team Manager who also
                // registered as a player must keep their manager role.
                // Guarded on the role existing so a missing/renamed role can never
                // fail a sale mid-auction.
                if ($user
                    && ! $user->hasAnyRole(['Superadmin', 'Admin'])
                    && ! $user->hasRole('Player')
                    && Role::where('name', 'Player')->where('guard_name', 'web')->exists()
                ) {
                    $user->assignRole('Player');
                }
            }
        });

        $fresh = $auctionPlayer->fresh();

        try {
            broadcast(new PlayerSoldEvent($fresh, $team));
        } catch (\Throwable $e) {
            Log::error('Failed to broadcast PlayerSoldEvent: ' . $e->getMessage());
        }

        $this->sendWelcomeCard($auctionPlayer, $auction?->tournament_id);

        return $snapshot;
    }

    /**
     * Put back exactly what applySale() overwrote.
     *
     * Only stores the sale actually changed are touched: a roster row or Spatie
     * role the player already had before the sale is left alone.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function revert(AuctionPlayer $auctionPlayer, array $snapshot): void
    {
        // The team the sale put this player on — captured before we overwrite it.
        $soldToTeamId = $auctionPlayer->sold_to_team_id;

        DB::transaction(function () use ($auctionPlayer, $snapshot, $soldToTeamId) {
            $before = $snapshot['auction_player'] ?? [];

            $auctionPlayer->update([
                'status' => $before['status'] ?? 'on_auction',
                'sold_to_team_id' => $before['sold_to_team_id'] ?? null,
                'final_price' => $before['final_price'] ?? null,
                'current_price' => $before['current_price'] ?? $auctionPlayer->base_price,
                'current_bid_team_id' => $before['current_bid_team_id'] ?? null,
                // An undone sale stops claiming a sale time.
                'sold_at' => $before['sold_at'] ?? null,
            ]);

            $playerSnapshot = $snapshot['player'] ?? null;
            if (! $playerSnapshot) {
                return;
            }

            $player = Player::find($playerSnapshot['id']);
            if (! $player) {
                return;
            }

            $player->update([
                'player_mode' => $playerSnapshot['player_mode'],
                'actual_team_id' => $playerSnapshot['actual_team_id'],
            ]);

            $tournamentId = $snapshot['tournament_id'] ?? null;
            $pivot = $snapshot['pivot'] ?? ['existed' => false];
            if ($tournamentId) {
                $query = DB::table('player_actual_team_tournament')
                    ->where('player_id', $player->id)
                    ->where('tournament_id', $tournamentId);

                if (! empty($pivot['existed'])) {
                    $query->update([
                        'actual_team_id' => $pivot['actual_team_id'],
                        'updated_at' => now(),
                    ]);
                } else {
                    // The sale created this row; remove it.
                    $query->delete();
                }
            }

            $roster = $snapshot['roster'] ?? ['existed' => false];
            // The sale added the player to this team's roster, so take them back
            // out. When the snapshot says they were already on it, restore the
            // role they held rather than removing them.
            if ($player->user_id && $soldToTeamId) {
                $rosterQuery = DB::table('actual_team_users')
                    ->where('actual_team_id', $soldToTeamId)
                    ->where('user_id', $player->user_id);

                if (empty($roster['existed'])) {
                    $rosterQuery->delete();
                } elseif (! empty($roster['role'])) {
                    $rosterQuery->update(['role' => $roster['role']]);
                }
            }

            // Restore the exact role set the user held before the sale — this is why
            // the snapshot captures role names.
            $roles = $snapshot['roles'] ?? [];
            $user = $player->user;
            if ($user && ! $user->hasAnyRole(['Superadmin', 'Admin'])) {
                if (! empty($roles)) {
                    $existing = Role::whereIn('name', $roles)->where('guard_name', 'web')->pluck('name')->all();
                    if ($existing) {
                        $user->syncRoles($existing);
                    }
                } elseif ($user->hasRole('Player')) {
                    $user->removeRole('Player');
                }
            }
        });
    }

    /**
     * Detach a currently-sold player from their team without a snapshot.
     *
     * Used by re-bid / re-auction, which put a sold player back on the block. Those
     * paths reset the auction row but used to leave players.actual_team_id, the
     * tournament roster pivot and the team roster still pointing at the buyer — so a
     * re-auctioned player stayed on the old team's squad.
     */
    public function clearTeamAttachment(AuctionPlayer $auctionPlayer): void
    {
        $auctionPlayer->loadMissing('auction', 'player');
        $player = $auctionPlayer->player;
        $teamId = $auctionPlayer->sold_to_team_id;

        if (! $player) {
            return;
        }

        $tournamentId = $auctionPlayer->auction?->tournament_id;

        DB::transaction(function () use ($player, $teamId, $tournamentId) {
            $player->update(['player_mode' => 'normal', 'actual_team_id' => null]);

            if ($tournamentId) {
                DB::table('player_actual_team_tournament')
                    ->where('player_id', $player->id)
                    ->where('tournament_id', $tournamentId)
                    ->delete();
            }

            if ($teamId && $player->user_id) {
                DB::table('actual_team_users')
                    ->where('actual_team_id', $teamId)
                    ->where('user_id', $player->user_id)
                    ->where('role', 'Player')
                    ->delete();
            }
        });
    }

    /**
     * Raise the "welcome to the team" card.
     *
     * Handed to AuctionMailService rather than sent here. The card renders a poster, so
     * sending it inline cost real time on every single sale — with deferred dispatch it
     * waits in the outbox and goes out once the auction is over.
     */
    private function sendWelcomeCard(AuctionPlayer $auctionPlayer, ?int $tournamentId): void
    {
        if (! $tournamentId) {
            return;
        }

        try {
            app(AuctionMailService::class)->raise(
                $auctionPlayer->auction,
                AuctionPendingEmail::TYPE_WELCOME_CARD,
                $auctionPlayer,
                $auctionPlayer->soldToTeam
            );
        } catch (\Throwable $e) {
            // Best-effort: a mail problem must never fail the sale itself.
            Log::error('Failed to raise retained welcome card on auction sale: ' . $e->getMessage());
        }
    }
}
