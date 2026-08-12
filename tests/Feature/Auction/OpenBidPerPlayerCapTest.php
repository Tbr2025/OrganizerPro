<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * A per-player ceiling for OPEN bidding, and what a team is told about it.
 *
 * The squad-reserve rule stops a team going broke — hold back enough to fill the places you
 * still need — but does nothing to stop one spending most of its purse on a single marquee
 * player and filling the rest of the squad at the minimum. The sealed round has always had a
 * per-player cap; open bidding had none.
 *
 * The default is NO cap, and that matters more than the feature: an auction that does not
 * configure this must behave exactly as it did before the column existed.
 */
class OpenBidPerPlayerCapTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function pools(): AuctionPoolService
    {
        return app(AuctionPoolService::class);
    }

    /**
     * A team with a 100M purse, 11 places to fill and a 1M floor per place.
     *
     * Organization names are unique, so a test building two scenarios needs two names.
     */
    private function scenario(array $auctionOverrides = [], string $orgName = 'Test Org'): array
    {
        $org = $this->makeOrganization($orgName);
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, array_merge([
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'bid_type' => 'open',
            'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000,
            'min_squad_size' => 11,
            'min_price_per_player' => 1_000_000,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ], $auctionOverrides));

        $team = $this->makeTeam($org, 'Alpha', $tournament);

        return compact('org', 'tournament', 'auction', 'team');
    }

    #[Test]
    public function with_no_cap_configured_only_the_reserve_rule_binds(): void
    {
        ['auction' => $auction, 'team' => $team] = $this->scenario();

        // 100M purse, 10 places left after this one at 1M each = 10M held back.
        $this->assertSame(90_000_000.0, $this->pools()->openBidCeiling($auction, $team->id));
        $this->assertTrue($this->pools()->canAffordWithReserve($auction, $team->id, 90_000_000));
        $this->assertFalse($this->pools()->canAffordWithReserve($auction, $team->id, 90_000_001));
    }

    #[Test]
    public function a_configured_cap_binds_below_the_reserve_maximum(): void
    {
        ['auction' => $auction, 'team' => $team] = $this->scenario([
            'max_bid_pct_of_budget' => 30,
        ]);

        // 30% of 100M is well under the 90M the reserve rule would allow.
        $this->assertSame(30_000_000.0, $this->pools()->openBidCeiling($auction, $team->id));
        $this->assertTrue($this->pools()->canAffordWithReserve($auction, $team->id, 30_000_000));
        $this->assertFalse($this->pools()->canAffordWithReserve($auction, $team->id, 30_100_000));
    }

    #[Test]
    public function the_reserve_still_wins_when_it_is_the_tighter_rule(): void
    {
        ['auction' => $auction, 'team' => $team] = $this->scenario([
            // 95% of 100M = 95M, looser than the 90M reserve maximum.
            'max_bid_pct_of_budget' => 95,
        ]);

        $this->assertSame(90_000_000.0, $this->pools()->openBidCeiling($auction, $team->id));
    }

    #[Test]
    public function the_refusal_names_whichever_rule_actually_bound(): void
    {
        ['auction' => $auction, 'team' => $team] = $this->scenario([
            'max_bid_pct_of_budget' => 30,
        ]);

        // Being told to hold money back for empty places, when the money is there and the rule
        // in the way is a single-player limit, sends a manager looking for the wrong fix.
        $capped = $this->pools()->reserveBlockedMessage($auction, $team->id, 40_000_000, 'Alpha');
        $this->assertStringContainsString('more than', $capped);
        $this->assertStringContainsString('on one player', $capped);
        $this->assertStringContainsString('30%', $capped);

        // When the RESERVE is the tighter rule, that is what gets named. A cap of 95% leaves
        // 95M, so the 90M reserve maximum is the one in the way.
        ['auction' => $loose, 'team' => $team2] = $this->scenario(
            ['max_bid_pct_of_budget' => 95],
            'Loose Org'
        );
        $reserved = $this->pools()->reserveBlockedMessage($loose, $team2->id, 92_000_000, 'Bravo');
        $this->assertStringContainsString('must retain', $reserved);
    }

    #[Test]
    public function the_purse_state_reports_the_ceiling_and_whether_one_is_configured(): void
    {
        ['auction' => $auction, 'team' => $team] = $this->scenario(['max_bid_pct_of_budget' => 30]);

        $state = $this->pools()->teamPurseState($auction, $team->id);
        $this->assertEqualsWithDelta(30.0, (float) $state['open_per_player_cap_pct'], 0.001);
        $this->assertSame(30_000_000.0, $state['open_per_player_cap']);
        $this->assertSame(30_000_000.0, $state['open_max_bid']);

        // Null pct is the signal that no ceiling exists — a different statement from 100%, and
        // the team's screen must not draw a limit for it.
        ['auction' => $uncapped, 'team' => $team2] = $this->scenario([], 'Second Org');
        $plain = $this->pools()->teamPurseState($uncapped, $team2->id);
        $this->assertNull($plain['open_per_player_cap_pct']);
        $this->assertSame(90_000_000.0, $plain['open_max_bid']);
    }

    #[Test]
    public function a_team_over_the_cap_is_shown_as_excluded_from_the_player_on_the_block(): void
    {
        ['auction' => $auction, 'team' => $team] = $this->scenario(['max_bid_pct_of_budget' => 30]);

        // The exclusion flag has to use the ceiling the bid is actually checked against, or a
        // team looks able to bid an amount the server then refuses in front of the room.
        $this->assertTrue($this->pools()->teamPurseState($auction, $team->id, 31_000_000)['excluded']);
        $this->assertFalse($this->pools()->teamPurseState($auction, $team->id, 29_000_000)['excluded']);
        $this->assertTrue($this->pools()->isExcluded($auction, $team->id, 31_000_000));
    }

    #[Test]
    public function the_cap_is_saved_from_the_wizard_and_can_be_cleared_again(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();
        $operator = $this->makeSuperadmin($org);

        $payload = [
            'name' => $auction->name,
            'organization_id' => $auction->organization_id,
            'tournament_id' => $auction->tournament_id,
            'status' => 'scheduled',
            'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000,
            'min_squad_size' => 11,
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ];

        $this->actingAs($operator)
            ->put(route('admin.auctions.update', $auction), $payload + ['max_bid_pct_of_budget' => 40])
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(40.0, (float) $auction->fresh()->max_bid_pct_of_budget, 0.001);

        // Absent means cleared, not "keep what was there" — a ceiling you cannot remove is a
        // trap, and preserve-on-absent is the bug the colour fields had.
        $this->actingAs($operator)
            ->put(route('admin.auctions.update', $auction), $payload)
            ->assertSessionHasNoErrors();
        $this->assertNull($auction->fresh()->max_bid_pct_of_budget);
    }
}
