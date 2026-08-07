<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\Player;
use App\Models\PlayerType;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * A throwaway bench for exercising the offline auction and the sealed (closed-bid) round
 * by hand.
 *
 * EVERYTHING it creates is named with the `TEST` prefix, so it can all be removed again
 * with `php artisan auction:test-bench --purge` without touching real data. Safe to run
 * more than once: every write is keyed on a name or email, so a second run tops up
 * rather than duplicating.
 */
class AuctionTestBenchSeeder extends Seeder
{
    /** Everything this seeder creates carries this prefix so it can be found again. */
    public const PREFIX = 'TEST';

    public const PASSWORD = 'password';

    public const PLAYER_COUNT = 100;

    public function run(): void
    {
        $tournament = Tournament::where('type', 'auction')
            ->whereHas('organization', fn ($q) => $q->where('auction_enabled', true))
            ->orderBy('id')
            ->first();

        if (! $tournament) {
            $this->command->error('No auction-type tournament in an auction-enabled organization. Enable auctions on an organization first.');

            return;
        }

        $orgId = $tournament->organization_id;
        $this->command->info("Using tournament #{$tournament->id} \"{$tournament->name}\" (org #{$orgId}).");

        $teams = $this->teams($tournament, $orgId);
        $this->managers($teams, $orgId);
        $players = $this->players($tournament, $orgId);

        $offline = $this->offlineAuction($tournament, $orgId, $players);
        $sealed = $this->sealedAuction($tournament, $orgId, $players);

        $this->command->newLine();
        $this->command->info('Bench ready.');
        $this->command->table(
            ['What', 'Where'],
            [
                ['Offline auction', url("/admin/organizer/auction/{$offline->id}/offline-panel")],
                ['Sealed auction (organizer)', url("/admin/organizer/auction/{$sealed->id}/panel")],
                ['Sealed auction (team view)', url("/admin/team/auction/{$sealed->id}/live")],
                ['LED wall', url("/auction/{$sealed->id}/live")],
                ['Stream ticker', url("/auction/{$sealed->id}/ticker")],
                ['Team logins', 'test.alpha@bench.test … (password: ' . self::PASSWORD . ')'],
            ]
        );
    }

    /** Six teams, so a tie between three of them is easy to stage. */
    private function teams(Tournament $tournament, int $orgId): array
    {
        $names = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot'];
        $teams = [];

        foreach ($names as $i => $name) {
            $teams[] = ActualTeam::firstOrCreate(
                ['name' => self::PREFIX . ' ' . $name],
                [
                    'organization_id' => $orgId,
                    'tournament_id' => $tournament->id,
                    'short_name' => strtoupper(substr($name, 0, 3)),
                    'primary_color' => ['#ef4444', '#3b82f6', '#22c55e', '#eab308', '#a855f7', '#ec4899'][$i],
                ]
            );
        }

        $this->command->info('Teams: ' . count($teams));

        return $teams;
    }

    /**
     * One login per team.
     *
     * Sealed bidding needs a real team-manager session — the team is always taken from
     * the session and never from the request, so it cannot be faked from the panel.
     */
    private function managers(array $teams, int $orgId): void
    {
        $role = Role::where('name', 'Team Manager')->first();

        foreach ($teams as $team) {
            $slug = strtolower(str_replace(self::PREFIX . ' ', '', $team->name));

            $user = User::firstOrCreate(
                ['email' => "test.{$slug}@bench.test"],
                [
                    'name' => $team->name . ' Manager',
                    // users.username is NOT NULL with no default.
                    'username' => 'test_' . $slug,
                    'password' => Hash::make(self::PASSWORD),
                    'organization_id' => $orgId,
                    'email_verified_at' => now(),
                ]
            );

            if ($role && ! $user->hasRole('Team Manager')) {
                $user->assignRole($role);
            }

            $team->users()->syncWithoutDetaching([$user->id => ['role' => 'Owner']]);
        }

        $this->command->info('Team managers: ' . count($teams) . ' (password: ' . self::PASSWORD . ')');
    }

    /** 100 approved players, so there is a real queue to work through. */
    private function players(Tournament $tournament, int $orgId): array
    {
        $types = PlayerType::whereIn('type', ['Batsman', 'Bowler', 'All-Rounder'])->pluck('id')->all()
            ?: PlayerType::take(3)->pluck('id')->all();

        // Real profiles, so the LED wall's batting/bowling rows have something to show.
        // The column on both tables is `style`, not `name`.
        $batting = \App\Models\BattingProfile::pluck('id')->all();
        $bowling = \App\Models\BowlingProfile::pluck('id')->all();

        $first = ['Arjun', 'Rohit', 'Kabir', 'Ishan', 'Vihaan', 'Aryan', 'Reyansh', 'Advik', 'Dhruv', 'Kiran'];
        $last = ['Sharma', 'Patel', 'Nair', 'Iyer', 'Reddy', 'Menon', 'Rao', 'Bose', 'Gill', 'Khan'];

        $players = [];

        for ($i = 1; $i <= self::PLAYER_COUNT; $i++) {
            $name = sprintf('%s %s %s %02d', self::PREFIX, $first[$i % 10], $last[intdiv($i, 10) % 10], $i);

            $player = Player::firstOrCreate(
                ['email' => sprintf('test.player%03d@bench.test', $i)],
                [
                    'name' => $name,
                    'status' => 'approved',
                    'organization_id' => $orgId,
                    'player_type_id' => $types[$i % count($types)],
                    'batting_profile_id' => $batting ? $batting[$i % count($batting)] : null,
                    'bowling_profile_id' => $bowling ? $bowling[$i % count($bowling)] : null,
                    // The ticker's career strip reads these three.
                    'total_matches' => 20 + ($i % 90),
                    'total_runs' => 150 + ($i * 27) % 3000,
                    'total_wickets' => ($i % 40),
                ]
            );

            // Only an APPROVED registration for this tournament makes a player poolable.
            TournamentRegistration::firstOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'player_id' => $player->id,
                    'type' => 'player',
                ],
                [
                    'organization_id' => $orgId,
                    'status' => 'approved',
                ]
            );

            $players[] = $player;
        }

        $this->command->info('Players: ' . count($players) . ' (all approved)');

        return $players;
    }

    /**
     * An auction fixed in OFFLINE mode: the organizer takes bids by hand from the panel,
     * clicking team badges to raise.
     */
    private function offlineAuction(Tournament $tournament, int $orgId, array $players): Auction
    {
        $auction = Auction::firstOrCreate(
            ['name' => self::PREFIX . ' Offline Auction'],
            [
                'organization_id' => $orgId,
                'tournament_id' => $tournament->id,
                'status' => 'running',
                'start_at' => now(),
                'end_at' => now()->addDays(2),
                'max_budget_per_team' => 100_000_000,
                'base_price' => 1_000_000,
                'bid_type' => 'open',
                // The organizer drives every bid; teams have no screen in this mode.
                'open_bid_mode' => 'offline',
                'mode_manually_overridden' => true,
                'bid_timer_seconds' => 30,
                'timer_enabled' => false,
                // Offline bidding reaches the sealed threshold the same way online does,
                // so the offline bench needs one too.
                'closed_bid_starts_at' => 8_000_000,
                'closed_bid_step' => 100_000,
                'closed_bid_max_pct_of_budget' => 70,
                'closed_bid_max_rebid_rounds' => 2,
                'closed_bid_requires_acceptance' => true,
                'min_squad_size' => 5,
                'min_price_per_player' => 1_000_000,
                'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 500_000]],
                'amount_unit' => 'points',
                // Nothing is emailed from a rehearsal.
                'email_test_mode' => true,
            ]
        );

        $this->pool($auction, array_slice($players, 0, 40), 'Offline Pool');

        return $auction;
    }

    /**
     * An auction that reaches its sealed threshold quickly: base 5M, 1M steps, sealed
     * from 8M. Four open raises and the round opens.
     */
    private function sealedAuction(Tournament $tournament, int $orgId, array $players): Auction
    {
        $auction = Auction::firstOrCreate(
            ['name' => self::PREFIX . ' Sealed Auction'],
            [
                'organization_id' => $orgId,
                'tournament_id' => $tournament->id,
                'status' => 'running',
                'start_at' => now(),
                'end_at' => now()->addDays(2),
                'max_budget_per_team' => 100_000_000,
                'base_price' => 5_000_000,
                'bid_type' => 'open',
                'open_bid_mode' => 'online',
                'mode_manually_overridden' => false,
                'bid_timer_seconds' => 60,
                'bid_timer_reset_seconds' => 45,
                'timer_enabled' => true,
                'timer_expiry_action' => 'manual',
                // Sealed from 8M. With a 1M open step that is three raises from base.
                'closed_bid_starts_at' => 8_000_000,
                'closed_bid_step' => 100_000,
                'closed_bid_max_pct_of_budget' => 70,
                'closed_bid_max_rebid_rounds' => 2,
                'closed_bid_timer_seconds' => 120,
                'closed_bid_requires_acceptance' => true,
                'closed_bid_tie_breaker' => 'lot',
                // Deliberately loose so the reserve does not mask the per-player cap
                // while you are testing.
                'min_squad_size' => 5,
                'min_price_per_player' => 1_000_000,
                'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 1_000_000]],
                'amount_unit' => 'points',
                'email_test_mode' => true,
            ]
        );

        $this->pool($auction, array_slice($players, 40, 60), 'Sealed Pool');

        return $auction;
    }

    /** One enabled pool with the players drawn in sequence. */
    private function pool(Auction $auction, array $players, string $poolName): void
    {
        $pool = AuctionPool::firstOrCreate(
            ['auction_id' => $auction->id, 'name' => self::PREFIX . ' ' . $poolName],
            [
                'organization_id' => $auction->organization_id,
                'order_mode' => AuctionPool::MODE_SEQUENTIAL,
                'sequence' => 1,
                'is_enabled' => true,
            ]
        );

        foreach (array_values($players) as $i => $player) {
            AuctionPlayer::firstOrCreate(
                ['auction_id' => $auction->id, 'player_id' => $player->id],
                [
                    'auction_pool_id' => $pool->id,
                    'organization_id' => $auction->organization_id,
                    'lot_number' => $i + 1,
                    'base_price' => $auction->base_price,
                    'current_price' => $auction->base_price,
                    'starting_price' => $auction->base_price,
                    'status' => 'waiting',
                    'is_retained' => false,
                ]
            );
        }

        // Activated here so the panel's NEXT button serves a player straight away. The
        // auction runs pool-by-pool, and an inactive pool means an empty queue.
        if ($pool->status !== AuctionPool::STATUS_ACTIVE) {
            app(\App\Services\Auction\AuctionPoolService::class)->activatePool($auction, $pool);
        }

        $this->command->info("{$auction->name}: pool with " . count($players) . ' players (active)');
    }
}
