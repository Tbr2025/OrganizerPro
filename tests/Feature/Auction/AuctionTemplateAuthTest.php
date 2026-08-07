<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionTemplate;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Auction templates used to have no authorization whatsoever.
 *
 * The routes carried no `permission:` gate, and `organizer.access` returns early for
 * anyone who is not a *pure* Organizer — so a logged-in account with no roles at all
 * could create, edit and delete every organization's templates. There was also no
 * `organization_id` on the table, so nothing could tell one org's rows from another's.
 *
 * That was tolerable while a template was positioned-element JSON. It is not tolerable
 * once a template can hold raw HTML served on a public URL.
 */
class AuctionTemplateAuthTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function makeTemplate(?Auction $auction, ?Organization $org, array $attrs = []): AuctionTemplate
    {
        return AuctionTemplate::create(array_merge([
            'name' => 'Template ' . uniqid(),
            'type' => 'live_display',
            'auction_id' => $auction?->id,
            'organization_id' => $org?->id,
            'canvas_width' => 1601,
            'canvas_height' => 910,
            'is_active' => true,
        ], $attrs));
    }

    #[Test]
    public function a_user_with_no_roles_cannot_reach_the_template_admin(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $template = $this->makeTemplate($auction, $org);
        $nobody = $this->makePlainUser($org);

        // Every one of these returned 200 before the permission gate was added.
        $this->actingAs($nobody)->get(route('admin.auction-templates.index'))->assertForbidden();
        $this->actingAs($nobody)->get(route('admin.auction-templates.create'))->assertForbidden();
        $this->actingAs($nobody)->get(route('admin.auction-templates.edit', $template))->assertForbidden();
        $this->actingAs($nobody)->put(route('admin.auction-templates.update', $template), [])->assertForbidden();
        $this->actingAs($nobody)->delete(route('admin.auction-templates.destroy', $template))->assertForbidden();
        $this->actingAs($nobody)->post(route('admin.auction-templates.set-default', $template))->assertForbidden();
    }

    #[Test]
    public function a_user_with_no_roles_cannot_delete_a_template(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $template = $this->makeTemplate($auction, $org);

        $this->actingAs($this->makePlainUser($org))
            ->delete(route('admin.auction-templates.destroy', $template))
            ->assertForbidden();

        $this->assertDatabaseHas('auction_templates', ['id' => $template->id]);
    }

    #[Test]
    public function an_operator_cannot_edit_another_organizations_template(): void
    {
        $mine = $this->makeOrganization('Mine');
        $theirs = $this->makeOrganization('Theirs');
        $theirTournament = $this->makeTournament($theirs);
        $theirAuction = $this->makeAuction($theirs, ['tournament_id' => $theirTournament->id]);
        $theirTemplate = $this->makeTemplate($theirAuction, $theirs);

        $operator = $this->makeAuctionOperator($mine);

        $this->actingAs($operator)
            ->get(route('admin.auction-templates.edit', $theirTemplate))
            ->assertForbidden();

        $this->actingAs($operator)
            ->delete(route('admin.auction-templates.destroy', $theirTemplate))
            ->assertForbidden();

        $this->assertDatabaseHas('auction_templates', ['id' => $theirTemplate->id]);
    }

    #[Test]
    public function the_listing_hides_another_organizations_templates(): void
    {
        $mine = $this->makeOrganization('Mine');
        $theirs = $this->makeOrganization('Theirs');

        $mineTemplate = $this->makeTemplate(null, $mine, ['name' => 'My Neon Wall']);
        $theirTemplate = $this->makeTemplate(null, $theirs, ['name' => 'Their Secret Wall']);

        $this->actingAs($this->makeAuctionOperator($mine))
            ->get(route('admin.auction-templates.index'))
            ->assertOk()
            ->assertSee('My Neon Wall')
            ->assertDontSee('Their Secret Wall');
    }

    #[Test]
    public function a_global_template_is_visible_to_everyone_but_editable_only_by_a_superadmin(): void
    {
        $org = $this->makeOrganization();
        $global = $this->makeTemplate(null, null, ['name' => 'House Style', 'is_default' => true]);

        // Readable — the LED wall falls back to the global default, so hiding it would
        // break every organization's display.
        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auction-templates.index'))
            ->assertOk()
            ->assertSee('House Style');

        // But not writable: it is shared, so an edit would change everyone's screen.
        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auction-templates.edit', $global))
            ->assertForbidden();

        $this->actingAs($this->makeSuperadmin($org))
            ->get(route('admin.auction-templates.edit', $global))
            ->assertOk();
    }

    #[Test]
    public function a_created_template_is_stamped_with_the_authors_organization(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->post(route('admin.auction-templates.store'), [
                'name' => 'Fresh Template',
                'type' => 'live_display',
                'auction_id' => $auction->id,
                'canvas_width' => 1920,
                'canvas_height' => 1080,
            ])->assertRedirect();

        $this->assertDatabaseHas('auction_templates', [
            'name' => 'Fresh Template',
            'organization_id' => $org->id,
        ]);
    }

    #[Test]
    public function template_resolution_is_deterministic_when_several_match(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $this->makeTemplate($auction, $org, ['name' => 'First']);
        $preferred = $this->makeTemplate($auction, $org, ['name' => 'Preferred', 'is_default' => true]);
        $this->makeTemplate($auction, $org, ['name' => 'Third']);

        // Unordered, this returned whichever row the engine happened to hand back, so
        // an unrelated insert could silently change what the LED wall rendered.
        $this->assertSame($preferred->id, AuctionTemplate::forAuction($auction->id)?->id);
    }
}
