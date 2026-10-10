<?php

declare(strict_types=1);

namespace Tests\Feature\Matches;

use App\Models\Ball;
use App\Models\LedDisplay;
use App\Models\LedQueueItem;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesScoredMatch;
use Tests\TestCase;

/**
 * The LED wall: a token link anyone can open, a server-side queue that decides
 * what is on screen, match moments queued with an operator's window to stop
 * them, fillers between balls, and an undo that pulls what it made.
 */
class LedWallTest extends TestCase
{
    use CreatesScoredMatch;
    use RefreshDatabase;

    private LedDisplay $display;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScoredMatch();
        Cache::flush();

        // Opening the console creates the match's display.
        $this->actingAs($this->scorer)->get(route('admin.matches.led', $this->match))->assertOk();
        $this->display = LedDisplay::where('match_id', $this->match->id)->firstOrFail();

        $this->toss($this->lions, 'bat');
    }

    private function wall(): array
    {
        auth()->logout();

        return $this->getJson(route('public.led.state', $this->display->fresh()->token))->assertOk()->json();
    }

    private function settings(array $s): void
    {
        $this->display->update(['settings' => array_merge(LedDisplay::DEFAULT_SETTINGS, $s)]);
    }

    private function queued(string $panel): ?LedQueueItem
    {
        return LedQueueItem::where('led_display_id', $this->display->id)->where('panel', $panel)->latest('id')->first();
    }

    #[Test]
    public function the_wall_opens_by_its_token_without_a_login_and_shows_the_score(): void
    {
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 2])->assertOk();

        auth()->logout();
        $this->get(route('public.led.wall', $this->display->token))->assertOk()->assertSee('Lions', false);

        $w = $this->wall();
        $this->assertSame('scoreboard', $w['now']['panel']);
        $this->assertSame(2, $w['state']['score']['runs']);
        $this->assertSame('Lions 1', $w['state']['striker']['name']);
        $this->assertSame('live', $w['state']['status']);

        $this->get('/led/' . str_repeat('x', 40))->assertNotFound();
    }

    #[Test]
    public function a_new_link_stops_every_screen_on_the_old_one(): void
    {
        $old = $this->display->token;
        $this->actingAs($this->scorer)->post(route('admin.led.regenerate', $this->display))->assertRedirect();

        auth()->logout();
        $this->getJson(route('public.led.state', $old))->assertNotFound();
        $this->getJson(route('public.led.state', $this->display->fresh()->token))->assertOk();
    }

    #[Test]
    public function screens_never_receive_contact_details(): void
    {
        Player::withoutOrganizationScope()->update([
            'email' => \DB::raw("CONCAT('p', id, '@secret.test')"),
            'mobile_number_full' => \DB::raw("CONCAT('9177', LPAD(id, 8, '0'))"),
        ]);
        User::query()->update(['email' => \DB::raw("CONCAT('u', id, '@private.test')")]);
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 4])->assertOk();

        $json = json_encode($this->wall());
        $this->assertStringNotContainsString('@secret.test', $json);
        $this->assertStringNotContainsString('9177000', $json);
        $this->assertStringNotContainsString('@private.test', $json);
        $this->assertStringNotContainsString($this->display->token, $json);

        $live = json_encode($this->getJson(route('public.live-state', $this->match))->assertOk()->json());
        $this->assertStringNotContainsString('@secret.test', $live);
        $this->assertStringNotContainsString('9177000', $live);
    }

    #[Test]
    public function the_public_ticker_state_works_logged_out(): void
    {
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 6])->assertOk();
        auth()->logout();

        $s = $this->getJson(route('public.live-state', $this->match))->assertOk()->json();
        $this->assertSame(6, $s['score']['runs']);
        $this->assertSame(['6'], $s['score']['this_over']);
    }

    #[Test]
    public function a_wicket_waits_for_the_operators_window_then_airs_then_hands_back(): void
    {
        $this->settings(['air_delay_seconds' => 5, 'event_seconds' => 10, 'rotation_seconds' => 600]);
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['is_wicket' => 1, 'dismissal_type' => 'bowled'])->assertOk();

        $item = $this->queued('wicket');
        $this->assertNotNull($item);
        $this->assertSame('queued', $item->status);
        $this->assertStringContainsString('WICKET — Lions 1 0 (1) b Tigers 1', $item->summary);

        $this->assertSame('scoreboard', $this->wall()['now']['panel'], 'Still inside the skip window');

        $this->travel(6)->seconds();
        $now = $this->wall()['now'];
        $this->assertSame('wicket', $now['panel']);
        $this->assertSame('Lions 1', $now['payload']['wicket']['name']);

        $this->travel(11)->seconds();
        $this->assertSame('scoreboard', $this->wall()['now']['panel']);
        $this->assertSame('shown', $item->fresh()->status);
    }

    #[Test]
    public function boundaries_and_over_ends_are_queued(): void
    {
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 6])->assertOk();
        $this->assertStringContainsString('SIX — Lions 1 now 6 (1)', $this->queued('boundary')->summary);

        foreach (range(1, 5) as $_) {
            $this->ball(['lions', 1], ['lions', 2], ['tigers', 1])->assertOk();
        }
        // A one-over match: the over ending is the innings ending.
        $this->assertNotNull($this->queued('innings_summary'));
    }

    #[Test]
    public function an_undone_ball_takes_its_panel_with_it(): void
    {
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['is_wicket' => 1, 'dismissal_type' => 'bowled'])->assertOk();
        $item = $this->queued('wicket');

        $ball = Ball::latest('id')->first();
        $this->actingAs($this->scorer)->deleteJson(route('admin.balls.destroy', [$this->match, $ball]))->assertOk();

        $this->assertSame('skipped', $item->fresh()->status);
        $this->travel(30)->seconds();
        $this->assertNotSame('wicket', $this->wall()['now']['panel']);
    }

    #[Test]
    public function with_approval_on_a_moment_waits_until_the_operator_approves_it(): void
    {
        $this->settings(['require_approval' => true, 'rotation_seconds' => 600]);
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 4])->assertOk();
        $item = $this->queued('boundary');
        $this->assertSame('held', $item->status);

        $this->travel(60)->seconds();
        $this->assertSame('scoreboard', $this->wall()['now']['panel'], 'Held never airs on its own');

        $this->actingAs($this->scorer)->postJson(route('admin.led.item', [$this->display, $item, 'approve']))->assertOk();
        $this->assertSame('boundary', $this->wall()['now']['panel']);
    }

    #[Test]
    public function the_operator_can_skip_before_it_airs(): void
    {
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 4])->assertOk();
        $item = $this->queued('boundary');

        $this->actingAs($this->scorer)->postJson(route('admin.led.item', [$this->display, $item, 'skip']))->assertOk();
        $this->travel(10)->seconds();

        $this->assertSame('skipped', $item->fresh()->status);
        $this->assertNotSame('boundary', $this->wall()['now']['panel']);
    }

    #[Test]
    public function between_balls_fillers_rotate_and_the_next_ball_cuts_them_short(): void
    {
        $this->settings(['rotation_seconds' => 20, 'panel_seconds' => 8, 'fillers' => ['top_batting', 'top_bowling']]);
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 1])->assertOk();

        $this->travel(10)->seconds();
        $this->assertSame('scoreboard', $this->wall()['now']['panel']);

        $this->travel(15)->seconds();
        $this->assertSame('top_batting', $this->wall()['now']['panel']);

        // Live play beats the filler.
        $this->ball(['lions', 2], ['lions', 1], ['tigers', 1], ['runs' => 2])->assertOk();
        $this->assertSame('scoreboard', $this->wall()['now']['panel']);

        // After the next rotation, the other filler.
        $this->travel(25)->seconds();
        $this->assertSame('top_bowling', $this->wall()['now']['panel']);
    }

    #[Test]
    public function manual_mode_holds_moments_and_runs_no_fillers(): void
    {
        $this->actingAs($this->scorer)->postJson(route('admin.led.control', [$this->display, 'mode']), ['mode' => 'manual'])->assertOk();
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 6])->assertOk();

        $this->assertSame('held', $this->queued('boundary')->status);
        $this->travel(300)->seconds();
        $this->assertSame('scoreboard', $this->wall()['now']['panel']);
    }

    #[Test]
    public function push_freeze_and_back_to_live(): void
    {
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 1])->assertOk();

        $this->actingAs($this->scorer)->postJson(route('admin.led.push', $this->display), ['panel' => 'top_batting'])->assertOk();
        $this->assertSame('top_batting', $this->wall()['now']['panel']);

        $this->actingAs($this->scorer)->postJson(route('admin.led.control', [$this->display, 'freeze']), ['frozen' => true])->assertOk();
        $this->travel(120)->seconds();
        $this->assertSame('top_batting', $this->wall()['now']['panel'], 'Frozen stays up');

        $this->actingAs($this->scorer)->postJson(route('admin.led.control', [$this->display, 'live']))->assertOk();
        $this->assertSame('scoreboard', $this->wall()['now']['panel']);

        // A panel with nothing to show is refused rather than airing blank.
        $this->actingAs($this->scorer)->postJson(route('admin.led.push', $this->display), ['panel' => 'sponsor'])->assertStatus(422);
    }

    #[Test]
    public function pinned_items_air_first_and_items_can_be_reordered(): void
    {
        $this->settings(['require_approval' => true]);
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 4])->assertOk();
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 6])->assertOk();
        [$four, $six] = LedQueueItem::where('panel', 'boundary')->orderBy('id')->get()->all();

        $order = fn () => $this->actingAs($this->scorer)->getJson(route('admin.led.state', $this->display))->json('queue.*.id');
        $this->assertSame([$four->id, $six->id], $order());

        $this->actingAs($this->scorer)->postJson(route('admin.led.item', [$this->display, $six, 'up']))->assertOk();
        $this->assertSame([$six->id, $four->id], $order());

        $this->actingAs($this->scorer)->postJson(route('admin.led.item', [$this->display, $four, 'pin']))->assertOk();
        $this->assertSame([$four->id, $six->id], $order());
    }

    #[Test]
    public function the_console_is_for_operators_of_this_organization_only(): void
    {
        $viewer = User::factory()->create(['organization_id' => $this->tournament->organization_id])->assignRole('Viewer');
        $this->actingAs($viewer)->get(route('admin.matches.led', $this->match))->assertForbidden();

        $outsider = User::factory()->create(['organization_id' => $this->makeOrganization('Other')->id])->assignRole('Scorer');
        $this->actingAs($outsider)->get(route('admin.matches.led', $this->match))->assertForbidden();
        $this->actingAs($outsider)->getJson(route('admin.led.state', $this->display))->assertForbidden();
    }

    #[Test]
    public function settings_are_validated_and_saved(): void
    {
        $this->actingAs($this->scorer)->postJson(route('admin.led.settings', $this->display), [
            'rotation_seconds' => 2, 'panel_seconds' => 8, 'event_seconds' => 10, 'air_delay_seconds' => 5, 'theme' => 'dark',
        ])->assertStatus(422);

        $this->actingAs($this->scorer)->postJson(route('admin.led.settings', $this->display), [
            'rotation_seconds' => 30, 'panel_seconds' => 6, 'event_seconds' => 12, 'air_delay_seconds' => 0,
            'require_approval' => true, 'theme' => 'light', 'fillers' => ['sponsor'],
        ])->assertOk();

        $d = $this->display->fresh();
        $this->assertSame(30, $d->setting('rotation_seconds'));
        $this->assertTrue($d->setting('require_approval'));
        $this->assertSame(['sponsor'], $d->setting('fillers'));
    }

    #[Test]
    public function the_console_page_renders(): void
    {
        $this->actingAs($this->scorer)->get(route('admin.matches.led', $this->match))
            ->assertOk()->assertSee('Up next')->assertSee(route('public.led.wall', $this->display->token), false);
    }
}
