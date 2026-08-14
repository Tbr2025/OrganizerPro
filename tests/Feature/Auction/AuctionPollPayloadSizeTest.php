<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The organizer panel's poll must stay small, because it runs every two seconds forever.
 *
 * It was sending full Eloquent models for every waiting player and every bid on the block —
 * measured at **314 KB per poll** on a 98-player pool, of which the browser used about 8 KB.
 * That is roughly 1.2 Mbps for one open panel, on a connection an auction hall is also using
 * for a hundred team screens; and a poll that big is slow enough to sit in front of the bid
 * request queued behind it, which is what an operator experiences as "the bid takes a moment
 * to show".
 *
 * The initial page render had always projected these fields. Only the poll had not — one shape
 * described twice, and the expensive copy was the one sent hundreds of times.
 */
class AuctionPollPayloadSizeTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function the_waiting_queue_is_projected_rather_than_sent_whole(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['max_budget_per_team' => 100_000_000]);
        $operator = $this->makeAuctionOperator($org);

        for ($i = 0; $i < 40; $i++) {
            $this->makeAuctionPlayer($auction, ['status' => 'waiting']);
        }

        $payload = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->json();

        $first = $payload['available_players'][0] ?? null;

        $this->assertIsArray($first);
        $this->assertArrayHasKey('name', $first, 'The queue should arrive flat, not nested under a player model.');
        $this->assertArrayNotHasKey('player', $first, 'The whole player model must not travel with it.');

        // The ten fields the panel maps, and nothing else — an eleventh means a model leaked
        // back in.
        /*
         * Two fields joined this list: `is_wicket_keeper` and `travel_plan_label`. Both were on the
         * wall and on the poster and on neither panel, so an operator could see a batting style but
         * not whether the player keeps wicket or can reach the tournament — and those are the two
         * facts that change what a lot is worth.
         *
         * Both are scalars, which is the point of asserting the shape here: the queue must stay a
         * flat projection and never drift back to whole models on a two-second poll.
         */
        $this->assertSame([
            'id', 'name', 'base_price', 'image_path', 'player_type',
            'batting_style', 'bowling_style', 'is_wicket_keeper', 'travel_plan_label',
            'total_matches', 'total_runs', 'total_wickets',
        ], array_keys($first));
    }

    #[Test]
    public function the_bid_log_on_the_block_carries_no_whole_user_or_team_models(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['max_budget_per_team' => 100_000_000]);
        $operator = $this->makeAuctionOperator($org);
        $teamA = $this->makeTeam($org, 'Alpha');
        $teamB = $this->makeTeam($org, 'Bravo');

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        foreach ([$teamA, $teamB, $teamA, $teamB] as $team) {
            $this->actingAs($operator)->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $team->id,
            ])->assertOk();
        }

        $bids = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->json('current_player.bids');

        $this->assertCount(4, $bids);

        // Team and user survive as a name and an id, because the side panel shows them.
        // What must not survive is the rest of those two models, four times over per lot.
        $this->assertSame(['id', 'amount', 'team_id', 'team', 'user', 'created_at'], array_keys($bids[0]));
        $this->assertSame(['id', 'name'], array_keys($bids[0]['team']));
        $this->assertSame(['name'], array_keys($bids[0]['user']));
    }

    #[Test]
    public function a_full_pool_poll_stays_within_a_sane_budget(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['max_budget_per_team' => 100_000_000]);
        $operator = $this->makeAuctionOperator($org);

        for ($i = 0; $i < 100; $i++) {
            $this->makeAuctionPlayer($auction, ['status' => 'waiting']);
        }

        $bytes = strlen((string) $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->getContent());

        /*
         * A deliberately generous ceiling — this is a regression guard, not a target. The
         * real payload for 100 players is around 25 KB; it was 314 KB. Anything approaching
         * 80 KB means whole models have found their way back in, and at two seconds a poll
         * that is a megabit per open panel.
         */
        $this->assertLessThan(80_000, $bytes, "The poll payload has grown to {$bytes} bytes.");
    }
}
