<?php

declare(strict_types=1);

namespace Tests\Feature\Matches;

use App\Models\MatchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesScoredMatch;
use Tests\TestCase;

/**
 * The match format, set in the scoring area: innings each, overs, players a
 * side, bowler limit and powerplays — and a two-innings match scored through
 * to its result.
 */
class MatchFormatTest extends TestCase
{
    use CreatesScoredMatch;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScoredMatch();
        Cache::flush();
    }

    private function state(): array
    {
        return $this->actingAs($this->scorer)->getJson(route('admin.matches.state', $this->match))->assertOk()->json();
    }

    private function format(array $data)
    {
        return $this->actingAs($this->scorer)->post(route('admin.matches.format', $this->match), $data + [
            'overs' => 1, 'innings_per_team' => 1,
        ]);
    }

    /** One over of the same score each ball; even runs keep the striker. */
    private function over(string $bat, string $bowl, int $runs): void
    {
        foreach (range(1, 6) as $_) {
            $this->ball([$bat, 1], [$bat, 2], [$bowl, 1], ['runs' => $runs])->assertOk();
        }
    }

    private function next(int $to): void
    {
        $this->actingAs($this->scorer)->post(route('admin.matches.switchInnings', $this->match), ['to' => $to])->assertRedirect();
    }

    #[Test]
    public function the_format_is_set_before_scoring_and_shown_in_the_scoring_area(): void
    {
        $html = $this->actingAs($this->scorer)->get(route('admin.matches.show', $this->match))->assertOk()->getContent();
        $this->assertStringContainsString('Set up the match before scoring', $html);

        $this->format([
            'overs' => 10, 'innings_per_team' => 2, 'players_per_team' => 8, 'bowler_overs_limit' => 3,
            'powerplays' => [['from' => 1, 'to' => 3], ['from' => '', 'to' => '']],
        ])->assertRedirect(route('admin.matches.show', $this->match))->assertSessionHasNoErrors();

        $m = $this->match->fresh();
        $this->assertSame([10, 2, 8, 3], [$m->overs, $m->innings_per_team, $m->players_per_team, $m->bowler_overs_limit]);
        $this->assertEquals([['from' => 1, 'to' => 3]], $m->powerplays);

        $state = $this->state();
        $this->assertSame(4, $state['totalInnings']);
        $this->assertTrue($state['inPowerplay']);
        $snapshot = app(\App\Services\Scoring\MatchStateService::class)->snapshot($this->match->fresh());
        $this->assertSame(7, $snapshot['innings'][1]['wicket_limit'], '8 a side: all out at 7');

        // Never beyond the innings.
        $this->format(['overs' => 10, 'bowler_overs_limit' => 11])->assertSessionHasErrors('bowler_overs_limit');
        $this->format(['overs' => 10, 'powerplays' => [['from' => 1, 'to' => 12]]])->assertSessionHasErrors('powerplays.0.to');
        $this->format(['innings_per_team' => 9])->assertSessionHasErrors('innings_per_team');

        // A team manager may not change it.
        $this->actingAs(\App\Models\User::factory()->create()->assignRole('Team Manager'))
            ->post(route('admin.matches.format', $this->match), ['overs' => 5, 'innings_per_team' => 1])->assertForbidden();
    }

    #[Test]
    public function the_format_never_goes_below_what_has_been_played(): void
    {
        $this->format(['overs' => 2, 'innings_per_team' => 2]);
        $this->toss($this->lions, 'bat');
        $this->over('lions', 'tigers', 2);
        $this->next(2);
        $this->next(3);

        $this->format(['overs' => 2, 'innings_per_team' => 1])->assertSessionHasErrors('innings_per_team');
        $this->format(['overs' => 2, 'innings_per_team' => 2])->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_two_innings_match_is_decided_on_the_aggregate_and_the_last_innings_chases(): void
    {
        $this->format(['overs' => 1, 'innings_per_team' => 2])->assertSessionHasNoErrors();
        $this->toss($this->lions, 'bat');

        $this->over('lions', 'tigers', 2);   // Lions 12
        $this->next(2);
        $this->over('tigers', 'lions', 4);   // Tigers 24: lead by 12
        $this->next(3);

        $state = $this->state();
        $this->assertSame(3, $state['currentInnings']);
        $this->assertTrue($state['inningsBreak']);
        $html = $this->actingAs($this->scorer)->get(route('admin.matches.show', $this->match))->assertOk()->getContent();
        $this->assertStringContainsString('Lions trail by 12', $html);
        $this->assertStringContainsString('3rd innings of 4', $html);

        $this->over('lions', 'tigers', 6);   // Lions 12 & 36 = 48: Tigers need 25
        $this->assertSame('12/0 & 36/0', $this->state()['teamAStats']['display']);
        $this->next(4);

        $state = $this->state();
        $this->assertSame(25, $state['target']);
        $wall = $this->getJson(route('public.live-state', $this->match))->assertOk()->json();
        $this->assertSame('innings_break', $wall['status']);
        $this->assertSame('Tigers need 25 to win', $wall['break']['text']);
        $this->assertSame(['12/0 & 36/0', '24/0'], array_column($wall['team_scores'], 'display'));

        foreach ([6, 6, 6, 6, 2] as $r) {
            $this->ball(['tigers', 1], ['tigers', 2], ['lions', 1], ['runs' => $r])->assertOk();
        }

        $state = $this->state();
        $this->assertTrue($state['matchComplete']);
        $this->assertSame(['winner_team_id' => $this->tigers->id, 'type' => 'wickets', 'margin' => 10], $state['result']);
        $this->assertSame('Tigers won by 10 wickets', $this->getJson(route('public.live-state', $this->match))->json('result.text'));

        $this->actingAs($this->scorer)->post(route('admin.matches.endMatch', $this->match), ['outcome' => 'auto'])->assertRedirect();
        $result = MatchResult::where('match_id', $this->match->id)->firstOrFail();
        $this->assertSame([48, 50], [(int) $result->team_a_score, (int) $result->team_b_score], 'Aggregates, not one innings');

        // The opener's 12 and 36 are two innings in one match, not one 48.
        $playerId = \App\Models\ActualTeamUser::find($this->squad['lions'][1])->player->id;
        $stat = \App\Models\PlayerStatistic::where('tournament_id', $this->tournament->id)->where('player_id', $playerId)->firstOrFail();
        $this->assertSame([2, 1, 48, 36], [(int) $stat->innings_batted, (int) $stat->matches, (int) $stat->runs, (int) $stat->highest_score]);
    }

    #[Test]
    public function a_side_that_cannot_catch_up_loses_by_an_innings(): void
    {
        $this->format(['overs' => 1, 'innings_per_team' => 2]);
        $this->toss($this->lions, 'bat');

        $this->over('lions', 'tigers', 0);   // Lions 0
        $this->next(2);
        $this->over('tigers', 'lions', 6);   // Tigers 36
        $this->next(3);
        $this->over('lions', 'tigers', 2);   // Lions 12: still 24 behind, no innings left

        $state = $this->state();
        $this->assertTrue($state['matchComplete']);
        $this->assertSame(['winner_team_id' => $this->tigers->id, 'type' => 'innings', 'margin' => 24], $state['result']);
        $this->assertSame('Tigers won by an innings and 24 runs', $this->getJson(route('public.live-state', $this->match))->json('result.text'));

        $this->actingAs($this->scorer)->post(route('admin.matches.endMatch', $this->match), ['outcome' => 'auto'])->assertRedirect();
        $this->assertSame('Tigers won by an innings and 24 runs', MatchResult::where('match_id', $this->match->id)->value('result_summary'));
    }

    #[Test]
    public function an_unfinished_two_innings_match_can_be_drawn_and_one_innings_matches_cannot_switch_past_two(): void
    {
        $this->format(['overs' => 1, 'innings_per_team' => 2]);
        $this->toss($this->lions, 'bat');
        $this->over('lions', 'tigers', 2);

        $this->actingAs($this->scorer)->post(route('admin.matches.endMatch', $this->match), ['outcome' => 'draw'])->assertRedirect();
        $this->assertSame('Match Drawn', MatchResult::where('match_id', $this->match->id)->value('result_summary'));

        $this->actingAs($this->scorer)->post(route('admin.matches.reopenScoring', $this->match));
        $this->format(['overs' => 1, 'innings_per_team' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->scorer)->post(route('admin.matches.switchInnings', $this->match), ['to' => 3])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, (int) $this->match->fresh()->current_innings);
    }

    #[Test]
    public function players_per_team_sets_all_out(): void
    {
        $this->format(['overs' => 1, 'players_per_team' => 3]);
        $this->toss($this->lions, 'bat');
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['is_wicket' => 1, 'dismissal_type' => 'bowled'])->assertOk();
        $this->ball(['lions', 3], ['lions', 2], ['tigers', 1], ['is_wicket' => 1, 'dismissal_type' => 'bowled'])->assertOk();

        $state = $this->state();
        $this->assertTrue($state['inningsComplete'], '3 a side: all out at 2 wickets');
        $this->assertTrue($state['isAllOut']);
    }
}
