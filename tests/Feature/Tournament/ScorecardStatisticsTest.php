<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Models\MatchResult;
use App\Models\Matches;
use App\Services\Tournament\ScorecardStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Tournament leaderboards read off the scorecards.
 *
 * The stats page was empty on every real tournament because it read `player_statistics`, which
 * only the ball-by-ball scorer writes. These assert the scorecard path: the numbers, the name
 * matching, and the guest who is on the card but on nobody's roster.
 */
class ScorecardStatisticsTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function scenario(array $scorecard): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');
        $alpha = $this->makeTeam($org, 'Alpha CC', $tournament);
        $beta = $this->makeTeam($org, 'Beta CC', $tournament);

        $squad = [];
        foreach ([[$alpha, 'Vikesh Kumar'], [$alpha, 'Ajay Saklani'], [$beta, 'Rameez Nawab'], [$beta, 'Faisal TK']] as [$team, $name]) {
            $player = $this->makeApprovedPlayer($org, $tournament, ['name' => $name]);
            \DB::table('player_actual_team_tournament')->insert([
                'player_id' => $player->id, 'actual_team_id' => $team->id,
                'tournament_id' => $tournament->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $squad[$name] = $player;
        }

        $match = Matches::create([
            'tournament_id' => $tournament->id,
            'name' => 'Alpha vs Beta',
            'slug' => 'alpha-v-beta-' . uniqid(),
            'team_a_id' => $alpha->id,
            'team_b_id' => $beta->id,
            'status' => 'completed',
            'stage' => 'league',
        ]);

        MatchResult::create([
            'match_id' => $match->id,
            'team_a_batting_first' => true,
            'scorecard_data' => $scorecard,
        ]);

        return [$tournament, $alpha, $beta, $squad];
    }

    private function card(): array
    {
        return [
            'innings' => [
                [
                    'team_name' => 'Alpha CC',
                    'batting' => [
                        // Role suffix inline, and only a partial name — both have to resolve.
                        ['name' => 'Vikesh  (c)', 'runs' => 62, 'balls' => 31, 'fours' => 10, 'sixes' => 1,
                         'how_out' => 'c Faisal TK b Rameez Nawab'],
                        ['name' => 'Ajay Saklani', 'runs' => 104, 'balls' => 55, 'fours' => 8, 'sixes' => 6,
                         'how_out' => ''],
                        // On the card, on nobody's roster: a guest still belongs on the board.
                        ['name' => 'Sakku Yorcker', 'runs' => 0, 'balls' => 3, 'fours' => 0, 'sixes' => 0,
                         'how_out' => 'run out (Faisal TK)'],
                    ],
                    'did_not_bat' => ['Nobody At All'],
                    'bowling' => [
                        ['name' => 'Rameez Nawab', 'overs' => '4', 'runs' => 24, 'wickets' => 5, 'maidens' => 1],
                        ['name' => 'Faisal TK', 'overs' => '3.4', 'runs' => 30, 'wickets' => 0, 'maidens' => 0],
                    ],
                ],
                [
                    'team_name' => 'Beta CC',
                    'batting' => [
                        ['name' => 'Faisal TK', 'runs' => 12, 'balls' => 9, 'fours' => 1, 'sixes' => 0,
                         'how_out' => 'st Vikesh Kumar b Ajay Saklani'],
                    ],
                    'bowling' => [
                        ['name' => 'Ajay Saklani', 'overs' => '2', 'runs' => 10, 'wickets' => 1, 'maidens' => 0],
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function batting_totals_come_off_the_scorecard(): void
    {
        [$tournament, , , $squad] = $this->scenario($this->card());

        $boards = app(ScorecardStatisticsService::class)->leaderboards($tournament);
        $batting = $boards['batting'];

        $this->assertSame('Ajay Saklani', $batting[0]->player->name, 'Top run scorer leads the list.');
        $this->assertSame(104, $batting[0]->runs);
        $this->assertSame(1, $batting[0]->hundreds);
        $this->assertTrue((bool) $batting[0]->highest_not_out, 'An empty how_out is a not out.');
        $this->assertSame(1, $batting[0]->not_outs);

        $vikesh = $batting->firstWhere('player_id', $squad['Vikesh Kumar']->id);
        $this->assertNotNull($vikesh, '"Vikesh  (c)" must resolve to Vikesh Kumar on the same squad.');
        $this->assertSame(62, $vikesh->runs);
        $this->assertSame(1, $vikesh->fifties);
        $this->assertSame(0, $vikesh->hundreds);
    }

    #[Test]
    public function a_name_on_no_roster_still_gets_a_row(): void
    {
        [$tournament] = $this->scenario($this->card());

        $stats = app(ScorecardStatisticsService::class)->leaderboards($tournament);
        $fielding = $stats['fielding'];

        // Everyone who took a catch, stumping or run out shows up.
        $names = $fielding->map(fn ($s) => $s->player?->name)->all();
        $this->assertContains('Faisal TK', $names);
        $this->assertContains('Vikesh Kumar', $names);

        $faisal = $fielding->first(fn ($s) => $s->player?->name === 'Faisal TK');
        $this->assertSame(1, $faisal->catches);
        $this->assertSame(1, $faisal->run_outs, 'run out (Faisal TK) credits the fielder.');

        $vikesh = $fielding->first(fn ($s) => $s->player?->name === 'Vikesh Kumar');
        $this->assertSame(1, $vikesh->stumpings);
    }

    #[Test]
    public function bowling_figures_and_economy_use_ball_arithmetic(): void
    {
        [$tournament] = $this->scenario($this->card());

        $bowling = app(ScorecardStatisticsService::class)->leaderboards($tournament)['bowling'];

        $rameez = $bowling->first(fn ($s) => $s->player?->name === 'Rameez Nawab');
        $this->assertSame(5, $rameez->wickets);
        $this->assertSame(1, $rameez->five_wickets);
        $this->assertSame(0, $rameez->four_wickets, 'A five-for is not also counted as a four-for.');
        $this->assertSame('5/24', $rameez->best_bowling);
        $this->assertSame(6.0, (float) $rameez->economy, '24 runs off 4 overs is 6.00.');

        // "3.4" is three overs and four balls — 22 balls, not 3.4 overs.
        $faisal = app(ScorecardStatisticsService::class)->leaderboards($tournament)['batting']
            ->concat($bowling)->first(fn ($s) => $s->player?->name === 'Faisal TK' && $s->overs_bowled > 0);
        $this->assertNotNull($faisal);
        $this->assertEqualsWithDelta(3.67, (float) $faisal->overs_bowled, 0.06);
    }

    #[Test]
    public function the_public_page_renders_the_scorecard_leaderboards(): void
    {
        [$tournament] = $this->scenario($this->card());

        $this->get(route('public.tournament.statistics', $tournament->slug))
            ->assertOk()
            ->assertSee('Ajay Saklani')
            ->assertDontSee('No batting statistics available yet.');
    }

    #[Test]
    public function an_appearance_counts_once_however_many_ways_a_player_features(): void
    {
        [$tournament, , , $squad] = $this->scenario($this->card());

        $stats = app(ScorecardStatisticsService::class)->leaderboards($tournament);
        $ajay = $stats['batting']->firstWhere('player_id', $squad['Ajay Saklani']->id);

        // Batted, bowled and took no catch — still one match.
        $this->assertSame(1, $ajay->matches);
    }
}
