<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Middleware\SimulateBandwidth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Rehearsing the auction on the connection the hall actually has.
 *
 * The properties that matter here are not the simulation — they are the guarantees around it,
 * because this is a switch that makes the system look broken on purpose, kept in the same
 * application that runs the live auction.
 */
class BandwidthSimulatorTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function no_limit_is_the_default_and_clearing_returns_to_it(): void
    {
        $org = $this->makeOrganization();
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)
            ->get(route('admin.network-test.index'))
            ->assertOk()
            ->assertSee('No limit');

        $this->actingAs($operator)
            ->post(route('admin.network-test.update'), ['kbps' => 512, 'minutes' => 10])
            ->assertRedirect();

        $this->assertSame(512, session(SimulateBandwidth::SESSION_KEY)['kbps']);

        /*
         * 0 is "No limit" — the way this is turned OFF, and it forgets rather than remembering
         * an off state. It was validated `min:32`, so this post was rejected outright, the
         * session kept its limit, and the one control that must always work did nothing.
         */
        $this->actingAs($operator)
            ->post(route('admin.network-test.update'), ['kbps' => 0])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(session(SimulateBandwidth::SESSION_KEY));
    }

    #[Test]
    public function a_limit_lifts_itself_when_its_time_is_up(): void
    {
        $org = $this->makeOrganization();
        $operator = $this->makeAuctionOperator($org);

        Carbon::setTestNow(now());

        $this->actingAs($operator)
            ->post(route('admin.network-test.update'), ['kbps' => 256, 'minutes' => 5]);

        $request = request();
        $request->setLaravelSession(session()->driver());

        $this->assertSame(256, SimulateBandwidth::activeLimitKbps($request));

        /*
         * Expiry is not a convenience. A throttle left on is indistinguishable from the
         * auction breaking, and would be discovered at the worst possible moment — so nobody
         * has to remember, and the check happens on the way past rather than on a schedule.
         */
        Carbon::setTestNow(now()->addMinutes(6));

        $this->assertNull(SimulateBandwidth::activeLimitKbps($request));
        // And it clears itself, so the UI cannot go on claiming to be on.
        $this->assertNull(session(SimulateBandwidth::SESSION_KEY));
    }

    #[Test]
    public function it_refuses_a_deadline_longer_than_the_ceiling(): void
    {
        $org = $this->makeOrganization();

        $this->actingAs($this->makeAuctionOperator($org))
            ->post(route('admin.network-test.update'), ['kbps' => 256, 'minutes' => SimulateBandwidth::MAX_MINUTES + 1])
            ->assertSessionHasErrors('minutes');
    }

    #[Test]
    public function a_throttled_response_says_what_it_cost(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['tournament_id' => $this->makeTournament($org)->id]);
        $operator = $this->makeAuctionOperator($org);
        $this->makeAuctionPlayer($auction);

        $this->actingAs($operator)
            ->post(route('admin.network-test.update'), ['kbps' => 32, 'minutes' => 5]);

        $response = $this->actingAs($operator)
            ->get(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk();

        // The headers are how the operator sees the size of what they just waited for,
        // rather than having to guess from the feel of it.
        $this->assertSame('32', $response->headers->get('X-Simulated-Kbps'));
        $this->assertGreaterThan(0, (int) $response->headers->get('X-Simulated-Bytes'));
    }

    #[Test]
    public function a_write_is_never_held_back(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'max_budget_per_team' => 100_000_000]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        // The slowest link the control allows.
        $this->actingAs($operator)
            ->post(route('admin.network-test.update'), ['kbps' => 32, 'minutes' => 5]);

        $started = microtime(true);

        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $team->id,
            ])
            ->assertOk();

        /*
         * A test that broke the auction would be no test at all. Only GET responses are held,
         * so placing a bid is exactly as fast with the throttle on as with it off.
         */
        $this->assertLessThan(2.0, microtime(true) - $started, 'Placing a bid must not be throttled.');
    }

    #[Test]
    public function it_is_one_browsers_setting_and_not_anybody_elses(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['tournament_id' => $this->makeTournament($org)->id]);
        $tester = $this->makeAuctionOperator($org);
        $everyoneElse = $this->makeAuctionOperator($org);

        $this->actingAs($tester)->post(route('admin.network-test.update'), ['kbps' => 32, 'minutes' => 5]);

        // A fresh session — the other operator running the actual auction.
        $this->flushSession();

        $response = $this->actingAs($everyoneElse)
            ->get(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk();

        $this->assertNull(
            $response->headers->get('X-Simulated-Kbps'),
            'The throttle lives in one session and must never reach anyone else.'
        );
    }

    #[Test]
    public function a_user_without_auction_access_cannot_reach_it(): void
    {
        $this->actingAs($this->makePlainUser($this->makeOrganization()))
            ->get(route('admin.network-test.index'))
            ->assertForbidden();
    }
}
