<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\ActualTeamUser;
use App\Models\TournamentRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The admin's team-preview list counted staff as players, and listed sides nobody approved.
 *
 * "Squad of Cuba — 6 players" was four players, a manager and an owner: the count came from
 * `ActualTeam::players()`, which is `hasMany(ActualTeamUser)` — every membership row whatever its
 * role, unscoped to the tournament and knowing nothing about the auction. So it could not answer
 * the question somebody opening this screen mid-auction is actually asking: how far through is
 * this squad, and what has it got left to spend.
 */
class TeamSelectorShowsSquadProgressTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function staff_are_not_counted_as_squad_members(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 50_000_000,
            'min_squad_size' => 8,
        ]);

        $team = $this->makeTeam($org, 'Squad of Cuba', $tournament);

        // A manager and an owner alongside the players — the rows that inflated the count.
        foreach (['Manager', 'Owner'] as $role) {
            ActualTeamUser::create([
                'actual_team_id' => $team->id,
                'user_id' => $this->makePlainUser($org)->id,
                'role' => $role,
            ]);
        }

        $html = $this->actingAs($this->makeSuperadmin($org))
            ->get(route('team.auction.bidding.show', $auction))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Squad of Cuba', $html);
        // The old wording is gone, and with it the number that came from counting staff.
        $this->assertStringNotContainsString('6 players', $html);
        // What replaced it: squad progress against the size the auction requires.
        $this->assertStringContainsString('/8 squad', $html);
        $this->assertStringContainsString('still to buy', $html);
    }

    #[Test]
    public function a_team_whose_registration_is_not_approved_is_not_offered(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $approved = $this->makeTeam($org, 'Let In', $tournament);
        $pending = $this->makeTeam($org, 'Still Waiting', $tournament);

        TournamentRegistration::create([
            'tournament_id' => $tournament->id, 'organization_id' => $org->id,
            'type' => 'team', 'actual_team_id' => $approved->id, 'status' => 'approved',
        ]);
        TournamentRegistration::create([
            'tournament_id' => $tournament->id, 'organization_id' => $org->id,
            'type' => 'team', 'actual_team_id' => $pending->id, 'status' => 'pending',
        ]);

        /*
         * The same rule the sealed board and the ticker use — participatingTeams(). Who may LOOK
         * at a list is a permissions question; who may spend money in this auction is not, and a
         * pending side offered here with a purse beside it can go on to win a player.
         */
        $html = $this->actingAs($this->makeSuperadmin($org))
            ->get(route('team.auction.bidding.show', $auction))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Let In', $html);
        $this->assertStringNotContainsString('Still Waiting', $html);
    }
}
