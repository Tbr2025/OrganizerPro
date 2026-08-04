<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Amounts read on the K / M / B ladder with a per-auction unit.
 *
 * Six separate screens previously hardcoded the Indian Lakh/Crore ladder, so an auction
 * run in points showed "10 Cr" — a unit its organizer never chose.
 */
class AuctionAmountUnitTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function the_ladder_is_k_m_b_and_never_lakh_or_crore(): void
    {
        $this->assertSame('500', format_points(500));
        $this->assertSame('50K', format_points(50000));
        $this->assertSame('100K', format_points(100000));
        $this->assertSame('1M', format_points(1000000));
        $this->assertSame('1.5M', format_points(1500000));
        $this->assertSame('10M', format_points(10000000));   // was "1 Cr"
        $this->assertSame('2.5B', format_points(2500000000));
        $this->assertSame('-1.5M', format_points(-1500000));

        // Null means "not configured", not zero.
        $this->assertSame('—', format_points(null));
        $this->assertSame('0', format_points(0, '0'));
    }

    #[Test]
    public function named_units_read_after_the_figure(): void
    {
        $org = $this->makeOrganization();

        $points = $this->makeAuction($org, ['amount_unit' => Auction::UNIT_POINTS]);
        $this->assertSame('10M Points', $points->formatAmount(10000000));

        $coins = $this->makeAuction($org, ['amount_unit' => Auction::UNIT_COINS]);
        $this->assertSame('10M Coins', $coins->formatAmount(10000000));
    }

    #[Test]
    public function dollars_read_before_the_figure(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['amount_unit' => Auction::UNIT_USD]);

        $this->assertSame('$10M', $auction->formatAmount(10000000));
        $this->assertSame('$', $auction->amountUnitLabel());
        $this->assertTrue($auction->amountUnitIsPrefix());
    }

    #[Test]
    public function a_custom_unit_uses_its_own_label(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'amount_unit' => Auction::UNIT_CUSTOM,
            'amount_unit_label' => 'Credits',
        ]);

        $this->assertSame('2.5M Credits', $auction->formatAmount(2500000));

        // A blank custom label falls back rather than rendering a bare figure.
        $blank = $this->makeAuction($org, [
            'amount_unit' => Auction::UNIT_CUSTOM,
            'amount_unit_label' => '   ',
        ]);
        $this->assertSame('1M Points', $blank->formatAmount(1000000));
    }

    #[Test]
    public function the_unit_defaults_to_points(): void
    {
        $org = $this->makeOrganization();
        $auction = Auction::create([
            'name' => 'Bare', 'status' => 'scheduled', 'organization_id' => $org->id,
            'bid_type' => 'open', 'max_budget_per_team' => 1000,
        ]);

        // The column default applies in the database…
        $this->assertSame(Auction::UNIT_POINTS, $auction->fresh()->amount_unit);
        // …and an unsaved/legacy row with no unit set still reads as Points rather than
        // rendering a bare figure.
        $this->assertSame('Points', $auction->amountUnitLabel());
        $this->assertFalse($auction->amountUnitIsPrefix());
        $this->assertSame('1M Points', $auction->formatAmount(1000000));
    }

    #[Test]
    public function the_unit_is_saved_from_the_wizard(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)->put(route('admin.auctions.update', $auction), [
            'name' => $auction->name,
            'organization_id' => $org->id,
            'tournament_id' => $tournament->id,
            'status' => 'scheduled',
            'max_budget_per_team' => 10000000,
            'base_price' => 100000,
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 20000000, 'increment' => 100000]],
            'amount_unit' => 'custom',
            'amount_unit_label' => 'Gems',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('custom', $auction->fresh()->amount_unit);
        $this->assertSame('5M Gems', $auction->fresh()->formatAmount(5000000));
    }

    #[Test]
    public function a_custom_unit_requires_a_label(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)->put(route('admin.auctions.update', $auction), [
            'name' => $auction->name,
            'organization_id' => $org->id,
            'tournament_id' => $tournament->id,
            'status' => 'scheduled',
            'max_budget_per_team' => 10000000,
            'base_price' => 100000,
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 20000000, 'increment' => 100000]],
            'amount_unit' => 'custom',
            'amount_unit_label' => '',
        ])->assertSessionHasErrors('amount_unit_label');
    }

    #[Test]
    public function every_screen_is_told_which_unit_to_use(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'amount_unit' => Auction::UNIT_USD,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        // Organizer panel poll.
        $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->assertJsonPath('amount_unit.label', '$')
            ->assertJsonPath('amount_unit.prefix', true);

        // Public audience display — unauthenticated, same unit.
        $this->getJson("/auction/{$auction->id}/active-player")
            ->assertOk()
            ->assertJsonPath('amount_unit.label', '$')
            ->assertJsonPath('amount_unit.prefix', true);
    }

    #[Test]
    public function reserve_messages_use_the_configured_unit(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 10000000,
            'min_squad_size' => 5,
            'min_price_per_player' => 1000000,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers');

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($ap, $team, 9000000, $operator);

        $response = $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertStatus(400);

        // Figures in the blocked-bid message read on the K/M/B ladder.
        $this->assertStringContainsString('4M', $response->json('message'));
        $this->assertStringNotContainsString('Cr', $response->json('message'));
    }
}
