<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The Playing XI picker offers the tournament squad, not everyone ever linked to the club.
 *
 * It shared the generate page's wide $players collection — home team, approved registrations,
 * team membership — so a player attached to the team but never put in the squad turned up as
 * someone who could be named in the XI.
 */
class PlayingXiSquadRosterTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function only_squad_players_are_offered_for_the_xi(): void
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');
        $team = $this->makeTeam($org, 'Titans', $tournament);

        $inSquad = $this->makeApprovedPlayer($org, $tournament, ['name' => 'Squad Member', 'actual_team_id' => $team->id]);
        $this->makeApprovedPlayer($org, $tournament, ['name' => 'Home Team Only', 'actual_team_id' => $team->id]);

        DB::table('player_actual_team_tournament')->insert([
            'player_id' => $inSquad->id,
            'actual_team_id' => $team->id,
            'tournament_id' => $tournament->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roster = $this->actingAs($this->makeSuperadmin($org))
            ->get(route('admin.tournaments.templates.generate', $tournament) . '?type=playing_xi')
            ->assertOk()
            ->viewData('xiRoster');

        $this->assertSame(['Squad Member'], array_column($roster[(string) $team->id] ?? [], 'name'));
    }
}
