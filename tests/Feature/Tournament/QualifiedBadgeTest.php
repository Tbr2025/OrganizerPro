<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Models\Matches;
use App\Models\PointTableEntry;
use App\Models\TournamentGroup;
use App\Services\Poster\PointTablePosterService;
use App\Services\Tournament\PointTableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * "Qualified" is a claim about the end of the league stage, not about today's standings.
 *
 * updatePositions() marks the top two qualified every time a result is entered, so after one
 * round the public table already told two sides they were through. The flag still exists — an
 * organizer sets it by hand, and posters read it — but the public tag waits for the fixtures.
 */
class QualifiedBadgeTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function scenario(): array
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');
        $group = TournamentGroup::create(['tournament_id' => $tournament->id, 'name' => 'Pool A']);

        $teams = collect(['Titans', 'Spartans'])->map(fn ($name) => $this->makeTeam($org, $name, $tournament));

        foreach ($teams as $index => $team) {
            PointTableEntry::create([
                'tournament_id' => $tournament->id,
                'tournament_group_id' => $group->id,
                'actual_team_id' => $team->id,
                'matches_played' => 1, 'won' => 1, 'lost' => 0, 'tied' => 0, 'no_result' => 0,
                'points' => 2, 'position' => $index + 1, 'qualified' => true,
            ]);
        }

        $fixture = fn (string $status, bool $cancelled = false, string $stage = 'league') => Matches::create([
            'tournament_id' => $tournament->id,
            'tournament_group_id' => $group->id,
            'name' => 'Fixture', 'slug' => 'fixture-' . uniqid(),
            'team_a_id' => $teams[0]->id, 'team_b_id' => $teams[1]->id,
            'status' => $status, 'stage' => $stage, 'is_cancelled' => $cancelled,
        ]);

        return [$tournament, $group, $fixture];
    }

    #[Test]
    public function the_tag_stays_off_while_a_league_fixture_is_still_to_be_played(): void
    {
        [$tournament, $group, $fixture] = $this->scenario();
        $fixture('completed');
        $fixture('upcoming');

        $this->assertFalse(app(PointTableService::class)->qualificationDecided($tournament, $group->id));

        $this->get(route('public.tournament.point-table', $tournament->slug))
            ->assertOk()
            ->assertDontSee('Qualified');
    }

    #[Test]
    public function the_tag_appears_once_the_league_stage_is_done(): void
    {
        [$tournament, $group, $fixture] = $this->scenario();
        $fixture('completed');
        $fixture('completed');

        $this->assertTrue(app(PointTableService::class)->qualificationDecided($tournament, $group->id));

        $this->get(route('public.tournament.point-table', $tournament->slug))
            ->assertOk()
            ->assertSee('Qualified');
    }

    #[Test]
    public function the_point_table_poster_follows_the_same_rule_as_the_page(): void
    {
        Storage::fake('public');

        [, $group, $fixture] = $this->scenario();
        $fixture('completed');
        $pending = $fixture('upcoming');

        // A green "qualified" row while a league game is still to be played puts a claim on a
        // poster that the public table deliberately withholds.
        $provisional = Storage::disk('public')->get((new PointTablePosterService())->generate($group->fresh()));

        $pending->update(['status' => 'completed']);
        $decided = Storage::disk('public')->get((new PointTablePosterService())->generate($group->fresh()));

        $this->assertNotSame(
            $provisional,
            $decided,
            'Finishing the league stage did not change the poster — the qualified rows are not gated.'
        );
    }

    #[Test]
    public function a_cancelled_fixture_does_not_hold_the_table_open(): void
    {
        [$tournament, $group, $fixture] = $this->scenario();
        $fixture('completed');
        $fixture('upcoming', true);

        $this->assertTrue(app(PointTableService::class)->qualificationDecided($tournament, $group->id));
    }

    #[Test]
    public function a_knockout_round_is_not_part_of_the_league_stage(): void
    {
        [$tournament, $group, $fixture] = $this->scenario();
        $fixture('completed');

        $fixture('upcoming', false, 'final');

        $this->assertTrue(
            app(PointTableService::class)->qualificationDecided($tournament, $group->id),
            'An unplayed final must not keep the league table provisional.'
        );
    }

    #[Test]
    public function a_group_with_no_league_fixtures_has_decided_nothing(): void
    {
        [$tournament, $group] = $this->scenario();

        $this->assertFalse(app(PointTableService::class)->qualificationDecided($tournament, $group->id));
    }
}
