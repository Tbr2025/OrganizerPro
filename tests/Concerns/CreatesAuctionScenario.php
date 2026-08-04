<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Setup helpers for auction tests.
 *
 * Auction models have no factories, so every test used to hand-roll ~40 lines of
 * Model::create() calls. These helpers keep that in one place and, importantly,
 * make the *intent* of each test explicit: an auction created here has the squad
 * reserve switched off unless the test asks for it, so a test about the plain
 * budget cap is not silently also testing the reserve rule.
 */
trait CreatesAuctionScenario
{
    protected function makeOrganization(string $name = 'Test Org'): Organization
    {
        return Organization::create(['name' => $name]);
    }

    protected function makeTournament(Organization $org, string $type = 'auction'): Tournament
    {
        return Tournament::create([
            'name' => 'Tournament ' . uniqid(),
            'slug' => 'tournament-' . uniqid(),
            'start_date' => now()->addWeek(),
            'organization_id' => $org->id,
            'type' => $type,
        ]);
    }

    /**
     * A user who may operate an auction (run the panel, sell, undo).
     *
     * Running an auction requires `auction.edit` — `auction.view` alone is what a
     * Team Manager holds and must not be enough.
     */
    protected function makeAuctionOperator(Organization $org, array $permissions = ['auction.edit', 'auction.view']): User
    {
        $role = Role::firstOrCreate(['name' => 'Auction Operator', 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => 'auction']
            );
            $role->givePermissionTo($permission);
        }

        return User::factory()->create(['organization_id' => $org->id])->assignRole($role);
    }

    /** A user with no auction permissions at all. */
    protected function makePlainUser(Organization $org): User
    {
        return User::factory()->create(['organization_id' => $org->id]);
    }

    /**
     * A Superadmin, who bypasses both the organization global scope and permission
     * checks. Needed to reach rows a scoped user cannot see — e.g. a legacy auction
     * whose organization_id is NULL.
     */
    protected function makeSuperadmin(?Organization $org = null): User
    {
        $role = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);

        foreach (['auction.view', 'auction.edit', 'auction.create', 'auction.delete'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => 'auction']
            ));
        }

        return User::factory()->create(['organization_id' => $org?->id])->assignRole($role);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeAuction(Organization $org, array $attributes = []): Auction
    {
        return Auction::create(array_merge([
            'name' => 'Auction ' . uniqid(),
            'status' => 'running',
            'organization_id' => $org->id,
            'bid_type' => 'open',
            'base_price' => 100,
            'max_budget_per_team' => 1000,
            // Reserve off by default so tests opt in deliberately. Without this the
            // DB defaults (11 slots x 1,000,000) would swamp any small test budget.
            'min_squad_size' => 1,
            'min_price_per_player' => 0,
            'bid_rules' => [['from' => 0, 'to' => 1000000, 'increment' => 100]],
        ], $attributes));
    }

    protected function makeTeam(Organization $org, string $name = 'Team', ?Tournament $tournament = null): ActualTeam
    {
        return ActualTeam::create(array_filter([
            'name' => $name,
            'organization_id' => $org->id,
            'tournament_id' => $tournament?->id,
        ], fn ($v) => $v !== null));
    }

    protected function makePlayer(Organization $org, array $attributes = []): Player
    {
        return Player::create(array_merge([
            'name' => 'Player ' . uniqid(),
            'email' => uniqid() . '@example.test',
            'status' => 'approved',
            'organization_id' => $org->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeAuctionPlayer(Auction $auction, array $attributes = []): AuctionPlayer
    {
        $org = Organization::find($auction->organization_id);
        $player = $attributes['player'] ?? $this->makePlayer($org ?? $this->makeOrganization());
        unset($attributes['player']);

        $base = $attributes['base_price'] ?? 100;

        return AuctionPlayer::create(array_merge([
            'auction_id' => $auction->id,
            'player_id' => $player->id,
            'organization_id' => $auction->organization_id,
            'base_price' => $base,
            'current_price' => $base,
            'starting_price' => $base,
            'status' => 'waiting',
        ], $attributes));
    }

    protected function makePool(Auction $auction, array $attributes = []): AuctionPool
    {
        return AuctionPool::create(array_merge([
            'auction_id' => $auction->id,
            'organization_id' => $auction->organization_id,
            'name' => 'Pool ' . uniqid(),
            'order_mode' => AuctionPool::MODE_SEQUENTIAL,
            'sequence' => 1,
        ], $attributes));
    }

    protected function makeBid(AuctionPlayer $auctionPlayer, ActualTeam $team, float $amount, ?User $user = null): AuctionBid
    {
        return AuctionBid::create([
            'auction_id' => $auctionPlayer->auction_id,
            'auction_player_id' => $auctionPlayer->id,
            'player_id' => $auctionPlayer->player_id,
            'team_id' => $team->id,
            'user_id' => $user?->id,
            'amount' => $amount,
            'bid_source' => 'online',
        ]);
    }
}
