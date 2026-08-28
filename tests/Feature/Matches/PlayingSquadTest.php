<?php

declare(strict_types=1);

namespace Tests\Feature\Matches;

use App\Models\MatchLineup;
use App\Models\Matches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * A team manager naming the XI for a match.
 *
 * Nothing recorded this before — a squad belongs to a tournament, not to a fixture — which is
 * why the Playing XI poster made the organizer retype eleven names for every render.
 */
class PlayingSquadTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function scenario(): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');
        $mine = $this->makeTeam($org, 'Mine', $tournament);
        $theirs = $this->makeTeam($org, 'Theirs', $tournament);

        $match = Matches::create([
            'tournament_id' => $tournament->id,
            'name' => 'Mine vs Theirs',
            'slug' => 'mine-v-theirs-' . uniqid(),
            'team_a_id' => $mine->id,
            'team_b_id' => $theirs->id,
            'status' => 'upcoming',
        ]);

        // Squad membership is the pivot, which is what the picker reads.
        $players = collect(range(1, 3))->map(function () use ($org, $tournament, $mine) {
            $player = $this->makeApprovedPlayer($org, $tournament);
            \DB::table('player_actual_team_tournament')->insert([
                'player_id' => $player->id, 'actual_team_id' => $mine->id,
                'tournament_id' => $tournament->id, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $player;
        });

        $manager = $this->makeAuctionOperator($org);
        $manager->assignRole(Role::firstOrCreate(['name' => 'Team Manager', 'guard_name' => 'web']));
        $mine->users()->attach($manager->id, ['role' => 'Owner']);

        return [$match, $mine, $theirs, $players, $manager, $tournament, $org];
    }

    #[Test]
    public function a_manager_names_the_eleven_and_it_replaces_what_was_there(): void
    {
        [$match, $mine, , $players, $manager] = $this->scenario();

        $this->actingAs($manager)->get(route('team-manager.matches.lineup', $match))->assertOk();

        $this->actingAs($manager)->post(route('team-manager.matches.lineup.save', $match), [
            'players' => [
                ['player_id' => $players[0]->id, 'role' => 'C'],
                ['player_id' => $players[1]->id, 'role' => 'WK'],
                ['player_id' => '', 'role' => ''],            // an empty row is not a player
                ['player_id' => $players[2]->id, 'role' => 'nonsense'],
            ],
        ])->assertRedirect();

        $saved = $match->lineupFor($mine);
        $this->assertCount(3, $saved);
        $this->assertSame([1, 2, 3], $saved->pluck('batting_order')->all());
        $this->assertSame('C', $saved[0]->role);
        $this->assertSame('WK', $saved[1]->role);
        $this->assertNull($saved[2]->role, 'An unrecognised role must be stored as no role.');

        // Saving again replaces the list wholesale — a diff would leave a dropped player named.
        $this->actingAs($manager)->post(route('team-manager.matches.lineup.save', $match), [
            'players' => [['player_id' => $players[1]->id, 'role' => 'C']],
        ])->assertRedirect();

        $saved = $match->fresh()->lineupFor($mine);
        $this->assertCount(1, $saved);
        $this->assertSame($players[1]->id, $saved[0]->player_id);
    }

    #[Test]
    public function a_manager_cannot_name_a_side_that_is_not_theirs(): void
    {
        [$match, , $theirs, $players, $manager, $tournament, $org] = $this->scenario();

        // A match their team is not in at all.
        $otherA = $this->makeTeam($org, 'Other A', $tournament);
        $otherB = $this->makeTeam($org, 'Other B', $tournament);
        $elsewhere = Matches::create([
            'tournament_id' => $tournament->id, 'name' => 'Other', 'slug' => 'other-' . uniqid(),
            'team_a_id' => $otherA->id, 'team_b_id' => $otherB->id, 'status' => 'upcoming',
        ]);

        $this->actingAs($manager)->get(route('team-manager.matches.lineup', $elsewhere))
            ->assertRedirect(route('team-manager.dashboard'));

        $this->actingAs($manager)->post(route('team-manager.matches.lineup.save', $elsewhere), [
            'players' => [['player_id' => $players[0]->id, 'role' => 'C']],
        ])->assertRedirect(route('team-manager.dashboard'));

        $this->assertSame(0, MatchLineup::where('match_id', $elsewhere->id)->count());

        /*
         * And within their own match they name THEIR side, never the opponent's — the team is
         * resolved from the manager's own memberships, not from anything the request carries.
         */
        $this->actingAs($manager)->post(route('team-manager.matches.lineup.save', $match), [
            'actual_team_id' => $theirs->id,        // ignored
            'players' => [['player_id' => $players[0]->id, 'role' => '']],
        ])->assertRedirect();

        $this->assertSame(0, MatchLineup::where('actual_team_id', $theirs->id)->count());
    }

    #[Test]
    public function a_player_from_another_squad_cannot_be_smuggled_in(): void
    {
        [$match, $mine, , $players, $manager, $tournament, $org] = $this->scenario();

        $outsider = $this->makeApprovedPlayer($org, $tournament);   // never added to the roster

        $this->actingAs($manager)->post(route('team-manager.matches.lineup.save', $match), [
            'players' => [
                ['player_id' => $players[0]->id, 'role' => ''],
                ['player_id' => $outsider->id, 'role' => 'C'],
            ],
        ])->assertRedirect();

        $named = $match->lineupFor($mine)->pluck('player_id')->all();
        $this->assertSame([$players[0]->id], $named, 'Only players in the squad may be named.');
    }
}
