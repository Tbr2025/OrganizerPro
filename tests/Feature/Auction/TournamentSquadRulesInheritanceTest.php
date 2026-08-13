<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\TournamentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Squad rules belong to the tournament, and an auction inherits them.
 *
 * Squad size, how many icon players a team keeps and what they cost, and the price a player
 * starts at are facts about the competition rather than about one auction evening. They lived
 * only on `auctions`, so a tournament running two auctions had to have them typed twice and could
 * disagree with itself — and `min_players_per_team` / `max_players_per_team` were already
 * collected on the tournament's own edit screen and read by absolutely nothing.
 *
 * The whole mechanism is six getters on Auction going through `rule()`. Everything downstream —
 * the reserve rule, the sealed ceilings, the full-squad exclusion, the team dashboard, the LED
 * wall — reads those getters, so nothing else had to change.
 */
class TournamentSquadRulesInheritanceTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function settingsFor($tournament, array $rules): TournamentSetting
    {
        return TournamentSetting::updateOrCreate(['tournament_id' => $tournament->id], $rules);
    }

    #[Test]
    public function an_auction_uses_the_tournaments_rules_by_default(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $this->settingsFor($tournament, [
            'min_players_per_team' => 20,
            'max_players_per_team' => 20,
            'icon_players_per_team' => 4,
            'icon_player_value' => 7_000_000,
            'player_base_value' => 500_000,
        ]);

        // Its own columns say something different, and are ignored.
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'min_squad_size' => 8,
            'max_squad_size' => 8,
            'expected_retained_per_team' => 2,
            'default_retained_value' => 2_000_000,
            'base_price' => 1_000_000,
            'overrides_tournament_rules' => false,
        ]);

        $this->assertSame(20, $auction->minSquadSize());
        $this->assertSame(20, $auction->maxSquadSize());
        $this->assertSame(4, $auction->expectedRetainedPerTeam());
        $this->assertSame(7_000_000.0, $auction->defaultRetainedValue());
        $this->assertSame(500_000.0, $auction->playerBasePrice());

        // 20 places, 4 kept as icons — 16 to buy. The rule the whole auction is built on.
        $this->assertSame(16, $auction->minSquadSize() - $auction->expectedRetainedPerTeam());
    }

    #[Test]
    public function overriding_makes_the_auction_ignore_the_tournament(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $this->settingsFor($tournament, [
            'min_players_per_team' => 20,
            'max_players_per_team' => 20,
            'icon_players_per_team' => 4,
            'icon_player_value' => 7_000_000,
            'player_base_value' => 500_000,
        ]);

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'min_squad_size' => 8,
            'max_squad_size' => 8,
            'expected_retained_per_team' => 2,
            'default_retained_value' => 2_000_000,
            'base_price' => 1_000_000,
            'overrides_tournament_rules' => true,
        ]);

        $this->assertSame(8, $auction->minSquadSize());
        $this->assertSame(2, $auction->expectedRetainedPerTeam());
        $this->assertSame(2_000_000.0, $auction->defaultRetainedValue());
        $this->assertSame(1_000_000.0, $auction->playerBasePrice());
    }

    #[Test]
    public function a_rule_the_tournament_has_not_decided_falls_through_to_the_auction(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        // Size decided at tournament level, the rest left blank.
        $this->settingsFor($tournament, ['min_players_per_team' => 15, 'max_players_per_team' => 15]);

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'min_squad_size' => 8,
            'expected_retained_per_team' => 3,
            'default_retained_value' => 4_000_000,
            'overrides_tournament_rules' => false,
        ]);

        /*
         * Null means "not decided here", not "zero". Writing a blank field as null and then
         * treating null as an answer would give every team a squad of nothing and free icon
         * players — which is why the controller drops blanks rather than saving them.
         */
        $this->assertSame(15, $auction->minSquadSize(), 'Decided at tournament level.');
        $this->assertSame(3, $auction->expectedRetainedPerTeam(), 'Not decided there — the auction answers.');
        $this->assertSame(4_000_000.0, $auction->defaultRetainedValue());
    }

    #[Test]
    public function the_squad_size_the_tournament_sets_decides_when_a_team_is_full(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $this->settingsFor($tournament, ['min_players_per_team' => 2, 'max_players_per_team' => 2]);

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
            // Its own size is roomier and must not be the one that counts.
            'min_squad_size' => 11,
            'max_squad_size' => 11,
            'overrides_tournament_rules' => false,
        ]);

        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $pools = app(\App\Services\Auction\AuctionPoolService::class);

        $this->assertFalse($pools->squadIsFull($auction, $team->id));

        foreach (range(1, 2) as $i) {
            $this->makeAuctionPlayer($auction, [
                'status' => 'sold', 'sold_to_team_id' => $team->id, 'final_price' => 1_000,
            ]);
        }

        /*
         * Two players against a tournament squad of two is full — and a full squad cannot bid,
         * because openBidCeiling() returns zero for one. So a tournament setting nothing in the
         * auction referred to now closes teams out of the bidding, which is the point.
         */
        $this->assertTrue($pools->squadIsFull($auction->fresh(), $team->id));
        $this->assertSame(0.0, $pools->openBidCeiling($auction->fresh(), $team->id));
        $this->assertFalse($pools->canAffordWithReserve($auction->fresh(), $team->id, 1.0));
    }

    #[Test]
    public function the_amount_switch_follows_the_tournament_unless_overridden(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $this->settingsFor($tournament, ['show_amounts' => false]);

        $inheriting = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'show_squad_values' => true,
            'overrides_tournament_rules' => false,
        ]);

        $this->assertFalse($inheriting->showsSquadValues(), 'The tournament says no.');

        $overriding = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'show_squad_values' => true,
            'overrides_tournament_rules' => true,
        ]);

        $this->assertTrue($overriding->showsSquadValues());
    }

    #[Test]
    public function the_tournament_form_saves_the_rules_and_ignores_blanks(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $this->settingsFor($tournament, ['icon_players_per_team' => 3]);

        // tournament.edit, not just the Superadmin role — checkAuthorization() wants the
        // permission and makeSuperadmin() grants only auction ones.
        $editor = $this->makeSuperadmin($org);
        $editor->givePermissionTo(
            \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => 'tournament.edit', 'guard_name' => 'web'],
                ['group_name' => 'tournament']
            )
        );

        $this->actingAs($editor)
            ->put(route('admin.tournaments.update', $tournament), [
                'name' => $tournament->name,
                'organization_id' => $org->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-02-01',
                'min_players_per_team' => 18,
                'max_players_per_team' => 18,
                // Left blank on the form — must not wipe the 3 already saved.
                'icon_players_per_team' => '',
                'icon_player_value' => 6_500_000,
                'show_amounts' => '0',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $settings = $tournament->fresh()->settings;

        $this->assertSame(18, (int) $settings->min_players_per_team);
        $this->assertSame(6_500_000.0, (float) $settings->icon_player_value);
        $this->assertFalse((bool) $settings->show_amounts);
        // A blank is "no change", not "set to nothing".
        $this->assertSame(3, (int) $settings->icon_players_per_team);
    }
}
