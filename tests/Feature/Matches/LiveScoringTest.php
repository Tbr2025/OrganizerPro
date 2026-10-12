<?php

declare(strict_types=1);

namespace Tests\Feature\Matches;

use App\Models\ActualTeam;
use App\Models\ActualTeamUser;
use App\Models\Ball;
use App\Models\Matches;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\RolesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesScoredMatch;
use Tests\TestCase;

/**
 * Ball-by-ball scoring through the real endpoints: the innings and the batters
 * live on the match (not the scorer's session), retries record once, a second
 * scorer cannot silently overwrite, undo restores, and the chase finishes the
 * match with the right result.
 */
class LiveScoringTest extends TestCase
{
    use CreatesScoredMatch;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScoredMatch();
    }

    private function state(): array
    {
        return $this->actingAs($this->scorer)->getJson(route('admin.matches.state', $this->match))->assertOk()->json();
    }

    #[Test]
    public function the_toss_decides_who_bats_first_and_the_innings_lives_on_the_match(): void
    {
        // Lions win the toss and bowl: Tigers (team B) bat first.
        $this->toss($this->lions, 'bowl');
        $this->assertSame($this->tigers->id, (int) $this->match->fresh()->batting_first_team_id);

        $this->ball(['tigers', 1], ['tigers', 2], ['lions', 1], ['runs' => 4])->assertOk();

        $ball = Ball::firstOrFail();
        $this->assertSame(1, $ball->innings);
        $this->assertSame([1, 1], [$ball->over, $ball->ball_in_over]);

        $state = $this->state();
        $this->assertSame(4, $state['teamBStats']['runs'], 'Team B batted: its box shows the runs');
        $this->assertFalse($state['teamAStats']['batted']);
        $this->assertSame($this->tigers->id, $state['battingTeamId']);

        // A different browser session sees the same innings and batters.
        $this->app['session']->flush();
        $this->assertSame($this->squad['tigers'][1], $this->state()['currentStriker']);
    }

    #[Test]
    public function batters_must_be_on_the_batting_side(): void
    {
        $this->toss($this->lions, 'bat');

        $this->ball(['tigers', 1], ['tigers', 2], ['lions', 1])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertSame(0, Ball::count());
    }

    #[Test]
    public function a_retried_request_records_the_ball_once(): void
    {
        $this->toss($this->lions, 'bat');
        $uuid = (string) Str::uuid();

        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 1, 'event_uuid' => $uuid])->assertOk();
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 1, 'event_uuid' => $uuid])
            ->assertOk()->assertJsonPath('message', 'Ball already recorded.');

        $this->assertSame(1, Ball::count());
    }

    #[Test]
    public function a_second_scorer_working_from_a_stale_score_is_refused(): void
    {
        $this->toss($this->lions, 'bat');
        $version = $this->state()['scoreVersion'];

        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['expected_version' => $version])->assertOk();
        // Another screen still holds the old version.
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['expected_version' => $version])->assertStatus(409);

        $this->assertSame(1, Ball::count());
    }

    #[Test]
    public function strike_and_bowler_are_stored_and_undo_puts_them_back(): void
    {
        $this->toss($this->lions, 'bat');

        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 1])->assertOk();
        $match = $this->match->fresh();
        $this->assertSame($this->squad['lions'][2], (int) $match->striker_id, 'Single: they crossed');
        $this->assertSame($this->squad['lions'][1], (int) $match->non_striker_id);

        $this->ball(['lions', 2], ['lions', 1], ['tigers', 1], ['runs' => 4])->assertOk();
        $last = Ball::orderByDesc('id')->first();
        $first = Ball::orderBy('id')->first();

        // Only the latest delivery can be undone.
        $this->actingAs($this->scorer)->deleteJson(route('admin.balls.destroy', [$this->match, $first]))->assertStatus(422);

        $this->actingAs($this->scorer)->deleteJson(route('admin.balls.destroy', [$this->match, $last]))->assertOk();
        $match = $this->match->fresh();
        $this->assertSame(1, Ball::count());
        $this->assertSame($this->squad['lions'][2], (int) $match->striker_id);
        $this->assertSame($this->squad['tigers'][1], (int) $match->current_bowler_id);
    }

    #[Test]
    public function a_run_out_can_dismiss_the_non_striker_with_runs_counted(): void
    {
        $this->toss($this->lions, 'bat');

        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], [
            'runs' => 1, 'is_wicket' => 1, 'dismissal_type' => 'run_out',
            'out_batsman' => 'non_striker', 'fielder_id' => $this->squad['tigers'][5],
        ])->assertOk();

        $ball = Ball::firstOrFail();
        $this->assertSame($this->squad['lions'][2], (int) $ball->out_batsman_id);
        $this->assertSame(1, $ball->runs);

        $state = $this->state();
        $this->assertSame(1, $state['totalRuns']);
        $this->assertSame([$this->squad['lions'][2]], $state['outBatsmenIds']);
        $this->assertTrue($state['needsNewBatsman']);

        // The dismissed batter cannot come back.
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1])->assertStatus(422);
    }

    #[Test]
    public function a_full_one_over_match_ends_with_the_right_result(): void
    {
        // Lions bat first: 1 over, 10 runs.
        $this->toss($this->lions, 'bat');
        foreach ([4, 0, 6, 0, 0, 0] as $r) {
            $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => $r])->assertOk();
        }

        // Innings over: another ball is refused.
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1])->assertStatus(422);
        $this->assertTrue($this->state()['inningsComplete']);

        $this->actingAs($this->scorer)->post(route('admin.matches.switchInnings', $this->match))->assertRedirect();
        $this->assertSame(2, (int) $this->match->fresh()->current_innings);

        // Tigers chase 11: a wide then 6, 6.
        $this->ball(['tigers', 1], ['tigers', 2], ['lions', 1], ['extra_type' => 'wide', 'extra_runs' => 1])->assertOk();
        $this->ball(['tigers', 1], ['tigers', 2], ['lions', 1], ['runs' => 6])->assertOk();
        $state = $this->state();
        $this->assertSame(['target' => 11, 'runs_needed' => 4, 'balls_left' => 5, 'required_run_rate' => 4.8], $state['chase']);

        $this->ball(['tigers', 1], ['tigers', 2], ['lions', 1], ['runs' => 6])->assertOk();

        $state = $this->state();
        $this->assertTrue($state['matchComplete']);
        $this->assertSame(['winner_team_id' => $this->tigers->id, 'type' => 'wickets', 'margin' => 10], $state['result']);

        // Nothing more can be scored.
        $this->ball(['tigers', 1], ['tigers', 2], ['lions', 1])->assertStatus(422);

        // The summary page writes the result the scorer saw.
        $admin = User::factory()->create(['organization_id' => $this->tournament->organization_id])->assignRole('Admin');
        $this->actingAs($admin)->get(route('admin.matches.summary.edit', $this->match))->assertOk();
        $result = $this->match->fresh()->result;
        $this->assertSame($this->tigers->id, (int) $result->winner_team_id);
        $this->assertSame('wickets', $result->result_type);
        $this->assertSame(10, (int) $result->team_a_score);
        $this->assertSame(13, (int) $result->team_b_score);
        $this->assertTrue((bool) $result->team_a_batting_first);
    }

    #[Test]
    public function the_scorer_ends_an_innings_early_and_a_double_click_does_not_flip_it_back(): void
    {
        $this->match->update(['overs' => 20]);
        $this->toss($this->lions, 'bat');
        foreach (range(1, 6) as $_) {
            $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 2])->assertOk();
        }

        foreach ([1, 2] as $_) {
            $this->actingAs($this->scorer)->post(route('admin.matches.switchInnings', $this->match), ['to' => 2])->assertRedirect();
        }
        $this->assertSame(2, (int) $this->match->fresh()->current_innings);

        $state = $this->state();
        $this->assertTrue($state['inningsBreak']);
        $this->assertSame(13, $state['target']);
        $html = $this->actingAs($this->scorer)->get(route('admin.matches.show', $this->match))->assertOk()->getContent();
        $this->assertStringContainsString('Innings break', $html);
        $this->assertStringContainsString('need 13 to win', $html);

        // The chase starts with the first ball; the break is over.
        $this->ball(['tigers', 1], ['tigers', 2], ['lions', 1], ['runs' => 1])->assertOk();
        $this->assertFalse($this->state()['inningsBreak']);
    }

    #[Test]
    public function the_scorer_ends_or_abandons_a_match_and_can_reopen_it(): void
    {
        $this->match->update(['overs' => 20]);
        $this->toss($this->lions, 'bat');
        foreach (range(1, 3) as $_) {
            $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 4])->assertOk();
        }

        // Rain: abandoned, no result, nothing more can be scored.
        $this->actingAs($this->scorer)->post(route('admin.matches.endMatch', $this->match), ['outcome' => 'abandoned', 'reason' => 'Rain'])
            ->assertRedirect()->assertSessionHas('success');
        $match = $this->match->fresh();
        $this->assertNotNull($match->scoring_ended_at);
        $this->assertSame('completed', $match->status);
        $this->assertSame('no_result', $match->result->result_type);
        $this->assertSame('Match abandoned (Rain) — no result', $match->result->result_summary);
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1])->assertStatus(422);
        $this->assertTrue($this->state()['matchComplete']);

        // Reopen, then end it with a winner chosen by the scorer.
        $this->actingAs($this->scorer)->post(route('admin.matches.reopenScoring', $this->match))->assertRedirect();
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1])->assertOk();
        $this->actingAs($this->scorer)->post(route('admin.matches.endMatch', $this->match), ['outcome' => 'team_a'])->assertRedirect();
        $result = $this->match->fresh()->result;
        $this->assertSame($this->lions->id, (int) $result->winner_team_id);
        $this->assertSame('runs', $result->result_type);
        $this->assertSame(12, (int) $result->margin);

        // "Use the scored result" needs a finished chase.
        $this->actingAs($this->scorer)->post(route('admin.matches.reopenScoring', $this->match));
        $this->actingAs($this->scorer)->post(route('admin.matches.endMatch', $this->match), ['outcome' => 'auto'])->assertSessionHas('error');
    }

    #[Test]
    public function after_an_over_the_scorer_picks_a_new_bowler_who_did_not_bowl_it(): void
    {
        $this->match->update(['overs' => 5]);
        $this->toss($this->lions, 'bat');
        foreach (range(1, 6) as $_) {
            $this->ball(['lions', 1], ['lions', 2], ['tigers', 1])->assertOk();
        }

        $state = $this->state();
        $this->assertTrue($state['overComplete']);
        $this->assertNull($state['currentBowler'], 'Nobody is guessed for the new over');
        $this->assertSame($this->squad['tigers'][1], $state['lastOverBowler']);

        // Same bowler again: refused. A different one: fine, and mid-over he stays.
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1])->assertStatus(422)->assertJsonValidationErrors('bowler_id');
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 2])->assertOk();
        $state = $this->state();
        $this->assertFalse($state['overComplete']);
        $this->assertSame($this->squad['tigers'][2], $state['currentBowler']);
    }

    #[Test]
    public function only_the_dismissals_the_laws_allow_off_a_wide_or_no_ball_are_taken(): void
    {
        $this->toss($this->lions, 'bat');
        $bat = fn (array $extra) => $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], $extra);

        // Caught off a no-ball is not out.
        $bat(['extra_type' => 'no_ball', 'extra_runs' => 1, 'is_wicket' => 1, 'dismissal_type' => 'caught'])
            ->assertStatus(422)->assertJsonValidationErrors('dismissal_type');
        // Bowled off a wide is not out either.
        $bat(['extra_type' => 'wide', 'extra_runs' => 1, 'is_wicket' => 1, 'dismissal_type' => 'bowled'])
            ->assertStatus(422)->assertJsonValidationErrors('dismissal_type');
        $this->assertSame(0, Ball::count());

        // Stumped off a wide is out, and the wide still counts.
        $bat(['extra_type' => 'wide', 'extra_runs' => 1, 'is_wicket' => 1, 'dismissal_type' => 'stumped', 'fielder_id' => $this->squad['tigers'][2]])->assertOk();
        $ball = Ball::latest('id')->first();
        $this->assertSame(['wide', 1, 'stumped'], [$ball->extra_type, (int) $ball->extra_runs, $ball->dismissal_type]);
        $state = $this->state();
        $this->assertSame([1, 1, '0.0'], [$state['totalRuns'], $state['totalWickets'], $state['oversDisplay']]);
    }

    #[Test]
    public function the_ball_after_a_no_ball_is_a_free_hit_until_a_legal_ball(): void
    {
        $this->toss($this->lions, 'bat');
        $bat = fn (array $extra = []) => $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], $extra);

        $bat(['extra_type' => 'no_ball', 'extra_runs' => 1])->assertOk();
        $this->assertTrue($this->state()['freeHit']);

        // A wide on the free hit: the free hit carries on.
        $bat(['extra_type' => 'wide', 'extra_runs' => 1])->assertOk();
        $this->assertTrue($this->state()['freeHit']);

        // Bowled on a free hit is not out; run out is.
        $bat(['is_wicket' => 1, 'dismissal_type' => 'bowled'])->assertStatus(422)->assertJsonValidationErrors('dismissal_type');
        $bat(['runs' => 1, 'is_wicket' => 1, 'dismissal_type' => 'run_out', 'out_batsman' => 'non_striker'])->assertOk();
        $state = $this->state();
        $this->assertFalse($state['freeHit'], 'A legal ball ends the free hit');
        $this->assertSame([3, 1], [$state['totalRuns'], $state['totalWickets']]); // nb + wd + 1 run
    }

    #[Test]
    public function a_bowler_cannot_bowl_more_than_the_match_quota(): void
    {
        $this->match->update(['overs' => 5]); // a fifth of 5 overs = 1 each
        $this->toss($this->lions, 'bat');
        $over = function (int $bowler) {
            foreach (range(1, 6) as $_) {
                $this->ball(['lions', 1], ['lions', 2], ['tigers', $bowler])->assertOk();
            }
        };
        $over(1);
        $over(2);
        $this->assertSame(1, $this->state()['bowlerQuota']);
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1])->assertStatus(422)->assertJsonValidationErrors('bowler_id');

        // Local rules: two overs each.
        $this->actingAs($this->scorer)->post(route('admin.matches.bowlerLimit', $this->match), ['bowler_overs_limit' => 2])->assertSessionHas('success');
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1])->assertOk();
        $this->assertSame(2, $this->state()['bowlerQuota']);
    }

    #[Test]
    public function the_scoring_page_renders_before_during_and_after_an_innings(): void
    {
        $this->actingAs($this->scorer)->get(route('admin.matches.show', $this->match))->assertOk();

        $this->toss($this->lions, 'bowl');
        $this->ball(['tigers', 1], ['tigers', 2], ['lions', 1], ['runs' => 2])->assertOk();
        $html = $this->actingAs($this->scorer)->get(route('admin.matches.show', $this->match))->assertOk()->getContent();
        $this->assertStringContainsString('Tigers', $html);
        $this->assertStringContainsString('let scoreVersion = ', $html);

        foreach (range(1, 5) as $_) {
            $this->ball(['tigers', 1], ['tigers', 2], ['lions', 1])->assertOk();
        }
        $html = $this->actingAs($this->scorer)->get(route('admin.matches.show', $this->match))->assertOk()->getContent();
        $this->assertStringContainsString('1st Innings Complete', $html);
        $this->assertStringContainsString('Start 2nd Innings (Lions batting)', $html);
    }

    #[Test]
    public function scoring_another_organizations_match_is_refused(): void
    {
        $outsider = User::factory()->create(['organization_id' => $this->makeOrganization('Other')->id])->assignRole('Scorer');
        $this->toss($this->lions, 'bat');

        $this->actingAs($outsider)->postJson(route('admin.balls.ajaxStore', $this->match), [
            'batsman_id' => $this->squad['lions'][1], 'non_striker_id' => $this->squad['lions'][2],
            'bowler_id' => $this->squad['tigers'][1], 'runs' => 4,
        ])->assertForbidden();

        $this->assertSame(0, Ball::count());
    }

    #[Test]
    public function the_migration_backfill_reads_old_balls_the_way_the_old_code_did(): void
    {
        // Rows as the pre-innings code wrote them: no innings, no non-striker,
        // no out batter, and nothing on the match.
        $legacy = fn (string $side, int $bat, string $bowlSide, array $extra = []) => \DB::table('balls')->insertGetId(array_merge([
            'match_id' => $this->match->id,
            'batsman_id' => $this->squad[$side][$bat],
            'bowler_id' => $this->squad[$bowlSide][1],
            'over' => 1, 'ball_in_over' => 1, 'runs' => 1, 'extra_runs' => 0, 'is_wicket' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ], $extra));
        $a = $legacy('lions', 1, 'tigers');
        $w = $legacy('lions', 2, 'tigers', ['is_wicket' => 1, 'dismissal_type' => 'bowled', 'runs' => 0]);
        $b = $legacy('tigers', 1, 'lions');

        (require database_path('migrations/2026_10_09_000004_add_innings_state_to_matches_and_balls.php'))->backfill();

        $this->assertSame(1, (int) \DB::table('balls')->where('id', $a)->value('innings'));
        $this->assertSame(2, (int) \DB::table('balls')->where('id', $b)->value('innings'));
        $this->assertSame($this->squad['lions'][2], (int) \DB::table('balls')->where('id', $w)->value('out_batsman_id'));

        $match = $this->match->fresh();
        $this->assertSame($this->lions->id, (int) $match->batting_first_team_id);
        $this->assertSame(2, (int) $match->current_innings);

        $state = $this->state();
        $this->assertSame(['runs' => 1, 'wickets' => 1, 'overs' => '0.2', 'completedOvers' => 0], $state['innings1Stats']);
        $this->assertSame(1, $state['teamBStats']['runs']);

        // Running it again changes nothing.
        (require database_path('migrations/2026_10_09_000004_add_innings_state_to_matches_and_balls.php'))->backfill();
        $this->assertSame(3, \DB::table('balls')->whereNotNull('innings')->count());
    }

    #[Test]
    public function a_caught_wicket_records_the_fielders_squad_row(): void
    {
        // balls.fielder_id used to reference players.id while the page sends a
        // squad-row id, so this 500'd unless the two numbers happened to match.
        $this->assertSame('actual_team_users', \DB::selectOne(
            "SELECT REFERENCED_TABLE_NAME r FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'balls' AND COLUMN_NAME = 'fielder_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
        )->r);

        $this->toss($this->lions, 'bat');
        $fielder = $this->squad['tigers'][7];
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], [
            'is_wicket' => 1, 'dismissal_type' => 'caught', 'fielder_id' => $fielder,
        ])->assertOk();

        $this->assertSame($fielder, (int) Ball::firstOrFail()->fielder_id);
        $this->assertSame('c Tigers 7 b Tigers 1', $this->state()['lastWicket']['how_out']);
    }

    #[Test]
    public function the_named_xi_leads_the_dropdowns_and_the_rest_of_the_squad_follows(): void
    {
        $playerId = fn (int $n) => ActualTeamUser::find($this->squad['lions'][$n])->player->id;
        foreach ([[5, 1], [3, 2]] as [$n, $order]) {
            \App\Models\MatchLineup::create([
                'match_id' => $this->match->id, 'actual_team_id' => $this->lions->id,
                'player_id' => $playerId($n), 'batting_order' => $order,
            ]);
        }
        $this->toss($this->lions, 'bat');

        $html = $this->actingAs($this->scorer)->get(route('admin.matches.show', $this->match))->assertOk()->getContent();
        preg_match('/<select id="batsmanSelect".*?<\/select>/s', $html, $select);
        preg_match_all('/>\s*(Lions \d+)/', $select[0], $names);

        $this->assertSame(['Lions 5', 'Lions 3'], array_slice($names[1], 0, 2), 'XI first, in batting order');
        $this->assertCount(11, $names[1], 'The rest of the squad is still selectable');
    }

    #[Test]
    public function the_public_and_admin_scorecards_render_with_ball_data(): void
    {
        $this->toss($this->lions, 'bat');
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 4])->assertOk();
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['extra_type' => 'wide', 'extra_runs' => 1])->assertOk();
        $this->match->update(['is_published' => true]);

        $this->get(route('public.match.scorecard', $this->match->slug))->assertOk();
        $this->actingAs($this->scorer)->get(route('admin.matches.scorecard', $this->match))->assertOk()
            ->assertViewHas('totalRuns', 5); // extras counted (it read a non-existent `extra_run`)

        // And once a result is saved, its numbers show.
        \App\Models\MatchResult::create([
            'match_id' => $this->match->id, 'team_a_batting_first' => true,
            'team_a_score' => 151, 'team_a_wickets' => 7, 'team_a_overs' => 20,
            'team_b_score' => 140, 'team_b_wickets' => 9, 'team_b_overs' => 20,
            'winner_team_id' => $this->lions->id, 'result_type' => 'runs', 'margin' => 11,
        ]);
        $this->get(route('public.match.scorecard', $this->match->slug))->assertOk()
            ->assertSee('151/7')->assertSee('140/9');
    }
}
