<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionTeamBudget;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * A team's purse now reports both figures the organizer asked for: the budget that was
 * configured for it, and the purse left to bid with once retentions are paid.
 *
 * Before this, `teamsWithPurse()` never emitted `allocated` at all, so every live screen
 * fell back to the auction-wide cap and a team with its own budget saw somebody else's
 * number.
 */
class AuctionPurseSplitTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function pools(): AuctionPoolService
    {
        return app(AuctionPoolService::class);
    }

    #[Test]
    public function the_purse_splits_into_retained_and_auction_spend(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        // One retention at 2M, one purchase at 3M.
        $this->makeAuctionPlayer($auction, [
            'is_retained' => true,
            'team_id' => $team->id,
            'retained_price' => 2_000_000,
            'status' => 'waiting',
        ]);
        $this->makeAuctionPlayer($auction, [
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 3_000_000,
        ]);

        $state = $this->pools()->teamPurseState($auction, $team->id);

        $this->assertSame(10_000_000.0, $state['allocated'], 'the configured total');
        $this->assertSame(2_000_000.0, $state['retained_spent']);
        $this->assertSame(3_000_000.0, $state['auction_spent']);
        $this->assertSame(5_000_000.0, $state['spent']);
        // The purse actually available on the floor: total less retentions.
        $this->assertSame(8_000_000.0, $state['auction_purse']);
        $this->assertSame(5_000_000.0, $state['remaining']);
        $this->assertSame(1, $state['retained_count']);
        $this->assertSame(2, $state['slots_filled']);
    }

    #[Test]
    public function the_purse_uses_a_per_team_budget_override(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        AuctionTeamBudget::create([
            'auction_id' => $auction->id,
            'actual_team_id' => $team->id,
            'organization_id' => $org->id,
            'budget' => 20_000_000,
        ]);

        $state = $this->pools()->teamPurseState($auction, $team->id);

        $this->assertSame(20_000_000.0, $state['allocated'], 'the override, not the uniform cap');
        $this->assertSame(20_000_000.0, $state['auction_purse']);
    }

    #[Test]
    public function a_zero_per_team_budget_row_really_means_zero(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        AuctionTeamBudget::create([
            'auction_id' => $auction->id,
            'actual_team_id' => $team->id,
            'organization_id' => $org->id,
            'budget' => 0,
        ]);

        // Row existence decides, not the value. A blank input deletes the row rather
        // than writing 0, so a zero row can only be a deliberate "no money".
        $this->assertSame(0.0, $this->pools()->teamPurseState($auction, $team->id)['allocated']);
    }

    #[Test]
    public function an_open_tournament_reports_an_uncapped_purse(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $state = $this->pools()->teamPurseState($auction, $team->id);

        $this->assertSame(PHP_FLOAT_MAX, $state['remaining']);
        $this->assertSame(PHP_FLOAT_MAX, $state['auction_purse']);
    }

    #[Test]
    public function slots_filled_still_equals_sold_plus_retained_after_the_split(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'max_budget_per_team' => 10_000_000]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $this->makeAuctionPlayer($auction, ['is_retained' => true, 'team_id' => $team->id, 'retained_price' => 1, 'status' => 'waiting']);
        $this->makeAuctionPlayer($auction, ['is_retained' => true, 'team_id' => $team->id, 'retained_price' => 1, 'status' => 'waiting']);
        $this->makeAuctionPlayer($auction, ['status' => 'sold', 'sold_to_team_id' => $team->id, 'final_price' => 1]);

        $pools = $this->pools();

        $this->assertSame(2, $pools->retainedCount($auction, $team->id));
        $this->assertSame(1, $pools->soldCount($auction, $team->id));
        $this->assertSame(3, $pools->slotsFilled($auction, $team->id));
    }

    #[Test]
    public function building_a_teams_purse_state_stays_cheap(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'max_budget_per_team' => 10_000_000]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $auction->load('tournament');

        DB::enableQueryLog();
        $this->pools()->teamPurseState($auction, $team->id);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // This is called once per team on a two-second poll, so it composing its figures
        // out of a dozen re-lookups is not academic. Guard the arithmetic-not-requery
        // shape of teamPurseState().
        $this->assertLessThanOrEqual(
            6,
            $queries,
            "teamPurseState() issued {$queries} queries; it should derive from a handful of leaf reads."
        );
    }
}
