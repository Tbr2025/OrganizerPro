<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Saving an LED template.
 *
 * The `auction_templates` table was empty on a database where the organizer had been using
 * the screen, so these pin down who can actually save and what a save does — including the
 * checkbox fields, which could not be turned OFF at all.
 */
class AuctionTemplateSaveTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** The minimum the form posts. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hall Wall',
            'type' => 'live_display',
            'render_mode' => 'positioned',
            'canvas_width' => 1601,
            'canvas_height' => 910,
        ], $overrides);
    }

    #[Test]
    public function an_operator_with_auction_edit_can_create_a_template(): void
    {
        $org = $this->makeOrganization();

        $this->actingAs($this->makeAuctionOperator($org))
            ->post(route('admin.auction-templates.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, AuctionTemplate::count(), 'the form payload must be enough to save');
    }

    #[Test]
    public function auction_view_alone_cannot_save_a_template(): void
    {
        $org = $this->makeOrganization();

        /*
         * This is the trap. `auction.view` is enough to SEE the LED Templates list, but the
         * store route needs `auction.edit` — so a Team Manager (view only) can open the
         * screen, fill it in, press save and get a 403 they may read as "nothing happened".
         */
        $viewer = $this->makeAuctionOperator($org, ['auction.view']);

        $this->actingAs($viewer)
            ->get(route('admin.auction-templates.index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('admin.auction-templates.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, AuctionTemplate::count());
    }

    #[Test]
    public function active_and_default_can_be_switched_off_again(): void
    {
        $org = $this->makeOrganization();
        $operator = $this->makeAuctionOperator($org);

        $template = AuctionTemplate::create([
            'name' => 'Hall Wall',
            'type' => 'live_display',
            'organization_id' => $org->id,
            'canvas_width' => 1601,
            'canvas_height' => 910,
            'is_active' => true,
            'is_default' => true,
        ]);

        /*
         * An unchecked checkbox sends nothing at all. With a bare `boolean` rule the key is
         * simply absent from $validated, so update() never wrote the column — unticking
         * Active or Default reported success and changed nothing.
         */
        $this->actingAs($operator)
            ->put(route('admin.auction-templates.update', $template), $this->payload([
                'name' => 'Hall Wall',
            ]))
            ->assertSessionHasNoErrors();

        $template->refresh();

        $this->assertFalse((bool) $template->is_active, 'unticking Active must actually save');
        $this->assertFalse((bool) $template->is_default, 'unticking Default must actually save');
    }

    #[Test]
    public function making_one_org_default_does_not_clear_another_orgs(): void
    {
        $mine = $this->makeOrganization('Mine');
        $theirs = $this->makeOrganization('Theirs');

        $ours = AuctionTemplate::create([
            'name' => 'Ours', 'type' => 'live_display', 'organization_id' => $mine->id,
            'canvas_width' => 1601, 'canvas_height' => 910, 'is_default' => false,
        ]);
        $other = AuctionTemplate::create([
            'name' => 'Theirs', 'type' => 'live_display', 'organization_id' => $theirs->id,
            'canvas_width' => 1601, 'canvas_height' => 910, 'is_default' => true,
        ]);

        // The "clear the previous default" sweep had no organization filter, so setting a
        // default in one org silently un-defaulted every other org's wall.
        $this->actingAs($this->makeAuctionOperator($mine))
            ->post(route('admin.auction-templates.set-default', $ours))
            ->assertRedirect();

        $this->assertTrue((bool) $ours->fresh()->is_default);
        $this->assertTrue(
            (bool) $other->fresh()->is_default,
            "another organization's default must be left alone"
        );
    }
}
