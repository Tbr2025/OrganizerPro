<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionPendingEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Clearing the email log, and previewing what was sent.
 *
 * Clearing DELETES rows, so the boundary matters more than the feature: a pending email is
 * still owed to somebody and a failed one is the record of a delivery that has to be
 * chased. If either could be cleared, the outbox would become a way to lose mail quietly
 * and nobody would know which players never heard anything.
 */
class AuctionEmailOutboxToolsTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function outbox(string $orgName = 'Test Org'): array
    {
        // Distinct names: organizations are uniquely named, so two scenarios in one test
        // collide unless they are told apart.
        $org = $this->makeOrganization($orgName);
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $player = $this->makePlayer($org, ['name' => 'Test Player']);

        foreach ([
            AuctionPendingEmail::STATUS_SENT,
            AuctionPendingEmail::STATUS_SKIPPED,
            AuctionPendingEmail::STATUS_PENDING,
            AuctionPendingEmail::STATUS_FAILED,
        ] as $status) {
            AuctionPendingEmail::create([
                'auction_id' => $auction->id,
                'player_id' => $player->id,
                'type' => AuctionPendingEmail::TYPE_UNSOLD,
                'status' => $status,
            ]);
        }

        return [$org, $auction, $player];
    }

    #[Test]
    public function clearing_removes_finished_rows_and_keeps_the_rest(): void
    {
        [$org, $auction] = $this->outbox();

        $this->actingAs($this->makeAuctionOperator($org))
            ->post(route('admin.auctions.emails.clear', $auction), ['scope' => 'resolved'])
            ->assertRedirect();

        $remaining = AuctionPendingEmail::where('auction_id', $auction->id)
            ->pluck('status')->sort()->values()->all();

        // Sent and skipped go; pending is still owed and failed still has to be chased.
        $this->assertSame(
            [AuctionPendingEmail::STATUS_FAILED, AuctionPendingEmail::STATUS_PENDING],
            $remaining
        );
    }

    #[Test]
    public function clearing_one_auction_does_not_touch_another(): void
    {
        [$org, $auction] = $this->outbox();
        [, $other] = $this->outbox('Other Org');

        $this->actingAs($this->makeAuctionOperator($org))
            ->post(route('admin.auctions.emails.clear', $auction), ['scope' => 'resolved'])
            ->assertRedirect();

        // The id in the URL is the only thing separating one organizer\'s outbox from
        // another\'s, so the delete is scoped to it as well as to the status.
        $this->assertSame(4, AuctionPendingEmail::where('auction_id', $other->id)->count());
    }

    #[Test]
    public function an_unknown_scope_is_refused(): void
    {
        [$org, $auction] = $this->outbox();

        $this->actingAs($this->makeAuctionOperator($org))
            ->post(route('admin.auctions.emails.clear', $auction), ['scope' => 'everything'])
            ->assertSessionHasErrors('scope');

        $this->assertSame(4, AuctionPendingEmail::where('auction_id', $auction->id)->count());
    }

    #[Test]
    public function preview_renders_the_email_without_sending_or_resolving_it(): void
    {
        [$org, $auction, $player] = $this->outbox();

        $row = AuctionPendingEmail::where('auction_id', $auction->id)
            ->where('status', AuctionPendingEmail::STATUS_PENDING)
            ->firstOrFail();

        \Illuminate\Support\Facades\Mail::fake();

        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auctions.emails.preview', [$auction, $row]))
            ->assertOk()
            ->assertSee($player->name, false);

        // Previewing must never send, and must never mark a pending row as handled.
        \Illuminate\Support\Facades\Mail::assertNothingSent();
        $this->assertSame(AuctionPendingEmail::STATUS_PENDING, $row->fresh()->status);
    }

    #[Test]
    public function an_email_from_another_auction_cannot_be_previewed(): void
    {
        [$org, $auction] = $this->outbox();
        [, $other] = $this->outbox('Other Org');

        $foreign = AuctionPendingEmail::where('auction_id', $other->id)->firstOrFail();

        // Route-model binding hands over whatever id is in the URL; nothing else on this
        // route ties the email to the auction.
        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auctions.emails.preview', [$auction, $foreign]))
            ->assertNotFound();
    }
}
