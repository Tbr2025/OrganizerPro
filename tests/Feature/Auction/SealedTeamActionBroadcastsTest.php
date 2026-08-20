<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Events\SealedRoundChanged;
use App\Services\Auction\ClosedBidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * A team acting in a sealed round tells the room.
 *
 * It used to tell nobody. Every organizer action announced itself, but a team accepting,
 * submitting, withdrawing or being reinstated broadcast nothing at all — so the only way another
 * screen learned about it was its own poll, and the organizer panel backs that off to thirty
 * seconds while the socket is healthy. The desk could sit half a minute behind the room during
 * the one part of an auction where the organizer is doing nothing but waiting on the teams. The
 * panel's own comment says exactly that.
 *
 * The other half of the fix is `switchBidType`, the manual "take the room to a sealed bid" path,
 * which opens a round and also said nothing.
 *
 * Two properties are load-bearing and are asserted here rather than assumed:
 *   - a REFUSED action announces nothing, or a screen would show a state that never happened;
 *   - the frame carries no amounts, because it travels on the same public channel as the open-bid
 *     price and the whole point of a sealed round is that the figures are private until reveal.
 */
class SealedTeamActionBroadcastsTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /**
     * An auction sitting in a live sealed round, with a team invited and the clock started.
     *
     * @return array<string, mixed>
     */
    private function sealedRound(array $auctionOverrides = []): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, array_merge([
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'bid_type' => 'closed',
            'max_budget_per_team' => 100_000_000,
            'closed_bid_starts_at' => 8_000_000,
            'closed_bid_step' => 100_000,
            'closed_bid_max_pct_of_budget' => 100,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ], $auctionOverrides));

        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $manager = $this->makePlainUser($org);
        $team->users()->attach($manager->id, ['role' => 'Owner']);

        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 8_000_000,
        ]);

        $service = app(ClosedBidService::class);
        $round = $service->openRoundFor($player->fresh(), $auction->fresh());
        $service->openEntry($round);
        $service->start($round->fresh());

        return compact('org', 'tournament', 'auction', 'team', 'manager', 'player', 'round');
    }

    #[Test]
    public function a_team_accepting_and_submitting_each_announce_the_round(): void
    {
        $ctx = $this->sealedRound();

        Event::fake([SealedRoundChanged::class]);

        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.accept', $ctx['auction']))
            ->assertOk();

        Event::assertDispatched(SealedRoundChanged::class, 1);

        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.submit', $ctx['auction']), [
                'amount' => 9_000_000,
            ])
            ->assertOk();

        // One per accepted action, not one per request: the guards below refuse before announcing.
        Event::assertDispatched(SealedRoundChanged::class, 2);
    }

    #[Test]
    public function a_withdrawal_announces_too(): void
    {
        $ctx = $this->sealedRound();

        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.accept', $ctx['auction']))->assertOk();
        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.submit', $ctx['auction']), ['amount' => 9_000_000])->assertOk();

        Event::fake([SealedRoundChanged::class]);

        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.withdraw', $ctx['auction']))
            ->assertOk();

        Event::assertDispatched(SealedRoundChanged::class, 1);
    }

    #[Test]
    public function the_frame_carries_no_amounts(): void
    {
        $ctx = $this->sealedRound();

        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.accept', $ctx['auction']))->assertOk();

        $captured = null;
        Event::listen(SealedRoundChanged::class, function ($event) use (&$captured) {
            $captured = $event->broadcastWith();
        });

        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.submit', $ctx['auction']), [
                'amount' => 9_000_000,
            ])->assertOk();

        $this->assertNotNull($captured, 'the submission must announce');

        /*
         * This channel is public — the same one the open-bid price travels on, which is safe only
         * because that price is already on the wall. A sealed amount is not, and must never ride
         * along. Asserted on the key set rather than on values so that adding any amount-shaped
         * field in future fails here.
         */
        $this->assertSame(
            ['auction_player_id', 'state', 'round_number'],
            array_keys($captured),
            'the sealed frame must carry only what player and what state — never a figure'
        );

        $flat = json_encode($captured);
        $this->assertStringNotContainsString('9000000', $flat);
        $this->assertStringNotContainsString('amount', $flat);
    }

    #[Test]
    public function a_refused_action_announces_nothing(): void
    {
        // A paused auction refuses sealed actions, and a refusal must not put a state on the wall.
        $ctx = $this->sealedRound();
        $ctx['auction']->update(['status' => 'paused']);

        Event::fake([SealedRoundChanged::class]);

        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.submit', $ctx['auction']), [
                'amount' => 9_000_000,
            ])
            ->assertStatus(423);

        Event::assertNotDispatched(SealedRoundChanged::class);
    }

    #[Test]
    public function a_player_role_is_refused_and_announces_nothing(): void
    {
        $ctx = $this->sealedRound();

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'player', 'guard_name' => 'web']);
        $ctx['manager']->assignRole($role);

        Event::fake([SealedRoundChanged::class]);

        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.submit', $ctx['auction']), [
                'amount' => 9_000_000,
            ])
            ->assertStatus(403);

        Event::assertNotDispatched(SealedRoundChanged::class);
    }

    #[Test]
    public function taking_the_room_to_a_sealed_bid_by_hand_announces_it(): void
    {
        /*
         * `switchBidType` is the manual flip to sealed. It opens the round — a fix made earlier,
         * because setting the flag alone left a phase with nothing behind it — but it announced
         * nothing, so the wall kept showing open bidding until somebody's poll came round. This is
         * the moment every screen in the hall most needs to change.
         */
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'bid_type' => 'open',
            'max_budget_per_team' => 100_000_000,
            'closed_bid_starts_at' => 8_000_000,
            'closed_bid_step' => 100_000,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 8_000_000]);

        Event::fake([SealedRoundChanged::class]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.switch-bid-type', $auction), [
                'bid_type' => 'closed',
            ])
            ->assertOk();

        Event::assertDispatched(SealedRoundChanged::class, 1);
    }

    #[Test]
    public function switching_back_to_open_announces_nothing(): void
    {
        // announce() no-ops on a null round, so an open-bid switch stays quiet.
        $ctx = $this->sealedRound();

        Event::fake([SealedRoundChanged::class]);

        $this->actingAs($this->makeAuctionOperator($ctx['org']))
            ->postJson(route('admin.auction.organizer.api.switch-bid-type', $ctx['auction']), [
                'bid_type' => 'open',
            ])
            ->assertOk();

        Event::assertNotDispatched(SealedRoundChanged::class);
    }
}
