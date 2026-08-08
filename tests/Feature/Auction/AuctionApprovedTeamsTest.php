<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\ActualTeam;
use App\Models\AuctionTeamBudget;
use App\Models\TournamentRegistration;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Only sides the tournament has approved take part in an auction.
 *
 * An ActualTeam row is not proof of approval. On the live tournament seven teams existed
 * while five registrations had been approved, and the sealed round invited all seven — the
 * same club appearing twice, once approved and once not, each with a purse, a ceiling and a
 * Withdraw button. A pending registration in a sealed round can win a player.
 *
 * Unlike the Groups screen there is no Superadmin bypass here: who may LOOK at a list is a
 * permissions question, who may spend money in this auction is not.
 */
class AuctionApprovedTeamsTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function registerTeam($org, $tournament, string $name, ?string $status): ActualTeam
    {
        $team = $this->makeTeam($org, $name, $tournament);

        // A null status means a team an organizer created directly, with no registration
        // at all — it was never part of the approval flow.
        if ($status !== null) {
            TournamentRegistration::create([
                'tournament_id' => $tournament->id,
                'organization_id' => $org->id,
                'actual_team_id' => $team->id,
                'type' => 'team',
                'status' => $status,
            ]);
        }

        return $team;
    }

    private function scenario(): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        return [$org, $tournament, $auction];
    }

    #[Test]
    public function a_pending_registration_does_not_take_part_in_the_auction(): void
    {
        [$org, $tournament, $auction] = $this->scenario();

        $this->registerTeam($org, $tournament, 'Approved United', 'approved');
        $this->registerTeam($org, $tournament, 'Pending Rovers', 'pending');
        $this->registerTeam($org, $tournament, 'Rejected Athletic', 'rejected');

        $names = app(AuctionPoolService::class)->participatingTeams($auction)->pluck('name')->all();

        $this->assertContains('Approved United', $names);
        $this->assertNotContains('Pending Rovers', $names);
        $this->assertNotContains('Rejected Athletic', $names);
    }

    #[Test]
    public function a_team_created_straight_from_the_admin_is_kept(): void
    {
        [$org, $tournament, $auction] = $this->scenario();

        $this->registerTeam($org, $tournament, 'Manual Wanderers', null);

        // Filtering on the absence of a registration row would hide every team an
        // organizer built by hand, which is most of them on a small tournament.
        $this->assertContains(
            'Manual Wanderers',
            app(AuctionPoolService::class)->participatingTeams($auction)->pluck('name')->all()
        );
    }

    #[Test]
    public function a_budget_row_cannot_let_an_unapproved_team_in(): void
    {
        [$org, $tournament, $auction] = $this->scenario();

        $approved = $this->registerTeam($org, $tournament, 'Approved United', 'approved');
        $pending = $this->registerTeam($org, $tournament, 'Pending Rovers', 'pending');

        foreach ([$approved, $pending] as $team) {
            AuctionTeamBudget::create([
                'auction_id' => $auction->id,
                'actual_team_id' => $team->id,
                'budget' => 10_000_000,
            ]);
        }

        /*
         * Per-team budgets are an organizer's explicit statement of who is in, and they
         * are authoritative over which of the approved teams take part — but they are
         * intersected with approval, not substituted for it. A budget row is a number,
         * not permission to enter the tournament.
         */
        $names = app(AuctionPoolService::class)->participatingTeams($auction)->pluck('name')->all();

        $this->assertSame(['Approved United'], $names);
    }

    #[Test]
    public function the_broadcast_ticker_lists_the_same_teams_as_the_sealed_round(): void
    {
        [$org, $tournament, $auction] = $this->scenario();

        $this->registerTeam($org, $tournament, 'Approved United', 'approved');
        $this->registerTeam($org, $tournament, 'Pending Rovers', 'pending');

        // One definition, so the strip on the stream and the board the organizer is
        // running cannot disagree in front of an audience about who is even in the room.
        $this->getJson("/auction/{$auction->id}/ticker-feed")
            ->assertOk()
            ->assertSee('Approved United')
            ->assertDontSee('Pending Rovers');
    }
}
