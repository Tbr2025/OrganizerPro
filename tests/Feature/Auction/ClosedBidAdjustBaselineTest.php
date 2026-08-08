<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionClosedBidEntry;
use App\Services\Auction\ClosedBidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Where a raise on the organizer's sealed board starts from.
 *
 * Every team used to step from the round FLOOR, so once one team was at 9M the organizer
 * had to press + ten times on a 100K step just to bring the next team level — the board
 * was recording "adjusted x10" for what the room heard as a single raise. A raise now
 * starts from the top of the board, the way an auctioneer works a room.
 */
class ClosedBidAdjustBaselineTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function collectingRound(): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'bid_type' => 'closed',
            'max_budget_per_team' => 100_000_000,
            'closed_bid_starts_at' => 8_000_000,
            'closed_bid_step' => 100_000,
            'closed_bid_max_pct_of_budget' => 100,
        ]);

        $alpha = $this->makeTeam($org, 'Alpha', $tournament);
        $bravo = $this->makeTeam($org, 'Bravo', $tournament);

        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 8_000_000,
        ]);

        $service = app(ClosedBidService::class);
        $round = $service->openRoundFor($player, $auction);
        $service->openEntry($round);
        $service->start($round->fresh());

        return [$service, $round->fresh(), $alpha, $bravo];
    }

    private function entryFor($round, $team): AuctionClosedBidEntry
    {
        return AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
            ->where('actual_team_id', $team->id)
            ->firstOrFail();
    }

    #[Test]
    public function the_first_raise_starts_at_the_floor_when_the_board_is_empty(): void
    {
        [$service, $round, $alpha] = $this->collectingRound();

        $service->adjust($this->entryFor($round, $alpha), null, 'up');

        // Nothing to go over yet, so the floor is the pace.
        $this->assertSame(8_100_000.0, (float) $this->entryFor($round, $alpha)->fresh()->amount);
    }

    #[Test]
    public function a_new_teams_raise_goes_over_the_standing_top_not_the_floor(): void
    {
        [$service, $round, $alpha, $bravo] = $this->collectingRound();

        $service->adjust($this->entryFor($round, $alpha), 9_000_000.0);

        // One press, not eleven.
        $service->adjust($this->entryFor($round, $bravo), null, 'up');

        $this->assertSame(9_100_000.0, (float) $this->entryFor($round, $bravo)->fresh()->amount);
    }

    #[Test]
    public function a_withdrawn_teams_amount_does_not_set_the_pace(): void
    {
        [$service, $round, $alpha, $bravo] = $this->collectingRound();

        $service->adjust($this->entryFor($round, $alpha), 9_000_000.0);
        $service->withdraw($this->entryFor($round, $alpha), null, AuctionClosedBidEntry::ROLE_ADMIN);

        $service->adjust($this->entryFor($round, $bravo), null, 'up');

        // standing() is the one definition of a bid that counts, and a withdrawn bid does
        // not — so the room is back at the floor.
        $this->assertSame(8_100_000.0, (float) $this->entryFor($round, $bravo)->fresh()->amount);
    }

    #[Test]
    public function a_team_with_its_own_amount_steps_from_its_own(): void
    {
        [$service, $round, $alpha, $bravo] = $this->collectingRound();

        $service->adjust($this->entryFor($round, $alpha), 9_000_000.0);
        $service->adjust($this->entryFor($round, $bravo), 8_500_000.0);

        // Bravo is already on the board, so + is a raise of Bravo's bid, not a jump to
        // the top. Correcting a team's own figure must stay a local edit.
        $service->adjust($this->entryFor($round, $bravo), null, 'up');

        $this->assertSame(8_600_000.0, (float) $this->entryFor($round, $bravo)->fresh()->amount);
    }
}
