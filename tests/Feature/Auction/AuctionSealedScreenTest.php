<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The wall's sealed screen, configured per auction.
 *
 * A sealed round is the longest single thing a hall looks at — the price freezes and one screen
 * carries the room for as long as the teams take. It was built from whatever logos the auction
 * happened to have and two fixed English sentences.
 */
class AuctionSealedScreenTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAuctionScenario;

    #[Test]
    public function the_built_in_wording_is_used_until_an_organizer_sets_their_own(): void
    {
        $auction = $this->makeAuction($this->makeOrganization());

        $this->assertSame('Sealed Bid In Progress', $auction->sealedHeading());
        $this->assertSame('Amounts are revealed once every team has submitted', $auction->sealedMessage());

        $auction->update(['sealed_heading' => 'المزايدة المغلقة', 'sealed_message' => 'يرجى الانتظار']);

        $this->assertSame('المزايدة المغلقة', $auction->fresh()->sealedHeading());
        $this->assertSame('يرجى الانتظار', $auction->fresh()->sealedMessage());
    }

    /**
     * Blank means "use the default", never "show nothing".
     *
     * An empty headline on the one screen a hall stares at for minutes is not what anybody means
     * by clearing a box.
     */
    #[Test]
    public function clearing_a_field_falls_back_rather_than_leaving_the_wall_blank(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['sealed_heading' => 'Something', 'sealed_message' => 'Else']);

        $this->actingAs($this->makeAuctionOperator($org))
            ->post(route('admin.auctions.sealed-screen.update', $auction), [
                'sealed_heading' => '   ',
                'sealed_message' => '',
            ])
            ->assertRedirect();

        $auction->refresh();

        $this->assertNull($auction->sealed_heading);
        $this->assertSame('Sealed Bid In Progress', $auction->sealedHeading());
        $this->assertSame('Amounts are revealed once every team has submitted', $auction->sealedMessage());
    }

    #[Test]
    public function an_uploaded_mark_wins_over_the_auction_and_tournament_logos(): void
    {
        Storage::fake('public');

        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['auction_logo' => 'auction-logos/existing.png']);

        // Falls back while nothing of its own is set.
        $this->assertStringContainsString('existing.png', (string) $auction->sealed_logo_url);

        $this->actingAs($this->makeAuctionOperator($org))
            ->post(route('admin.auctions.sealed-screen.update', $auction), [
                'sealed_logo' => UploadedFile::fake()->image('sealed.png'),
            ])
            ->assertRedirect();

        $auction->refresh();

        $this->assertNotNull($auction->sealed_logo);
        Storage::disk('public')->assertExists($auction->sealed_logo);
        $this->assertStringContainsString($auction->sealed_logo, (string) $auction->sealed_logo_url);

        // Removing it goes back to the auction's own logo rather than to nothing.
        $this->actingAs($this->makeAuctionOperator($org))
            ->delete(route('admin.auctions.sealed-screen.logo.destroy', $auction))
            ->assertRedirect();

        $this->assertNull($auction->fresh()->sealed_logo);
        $this->assertStringContainsString('existing.png', (string) $auction->fresh()->sealed_logo_url);
    }

    #[Test]
    public function the_page_is_configuration_and_needs_auction_edit(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);

        // `auction.view` is what a Team Manager holds, and event setup is not theirs to open.
        $viewer = $this->makeAuctionOperator($org, ['auction.view']);

        $this->actingAs($viewer)
            ->get(route('admin.auctions.sealed-screen.index', $auction))
            ->assertForbidden();

        // And the organizer who owns the auction can.
        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auctions.sealed-screen.index', $auction))
            ->assertOk();
    }
}
