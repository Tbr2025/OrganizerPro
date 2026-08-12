<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A refused save must say so.
 *
 * An operator set Maximum Squad Size to 8 on an auction whose minimum is 11, along with two
 * retention settings, and pressed Save. The whole request was rejected — but the edit page
 * rendered `$errors` nowhere and the form is Alpine state seeded from the model rather than from
 * old(), so the page reloaded looking untouched with nothing written. Indistinguishable from a
 * broken save, and the two settings that were perfectly valid went down with it.
 *
 * That particular conflict is now impossible: the wizard asks for one Team Size and derives the
 * maximum from it. The class of bug is not, so these tests moved to a refusal that can still
 * happen — a negative team budget, which also sits on step 2 (Financials), so the "reopens on
 * the failing step" guarantee is still exercised where it was.
 */
class AuctionSaveFailureIsVisibleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Organization $org): User
    {
        foreach (['auction.view', 'auction.edit', 'auction.create'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web'], ['group_name' => 'auction']);
        }

        $role = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo(['auction.view', 'auction.edit', 'auction.create']);

        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole($role);

        return $user;
    }

    private function auction(Organization $org, Tournament $tournament): Auction
    {
        return Auction::create([
            'name' => 'A', 'status' => 'scheduled', 'max_budget_per_team' => 100000000,
            'base_price' => 1000000, 'organization_id' => $org->id, 'tournament_id' => $tournament->id,
            'bid_type' => 'open', 'min_squad_size' => 11, 'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 100000000, 'increment' => 100000]],
        ]);
    }

    /** The payload the edit form posts, minus whatever the caller is varying. */
    private function payload(Auction $auction, array $overrides = []): array
    {
        return array_merge([
            'name' => $auction->name,
            'organization_id' => $auction->organization_id,
            'tournament_id' => $auction->tournament_id,
            'status' => 'scheduled',
            'max_budget_per_team' => 100000000,
            'base_price' => 1000000,
            'min_squad_size' => 11,
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 100000000, 'increment' => 100000]],
        ], $overrides);
    }

    #[Test]
    public function a_refused_field_takes_the_whole_form_down_with_it(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create(['name' => 'C', 'slug' => 'c', 'start_date' => '2026-01-01', 'organization_id' => $org->id]);
        $auction = $this->auction($org, $tournament);

        $response = $this->actingAs($this->admin($org))
            ->from(route('admin.auctions.edit', $auction))
            ->put(route('admin.auctions.update', $auction), $this->payload($auction, [
                'max_budget_per_team' => -5,
                'default_retained_value' => 2000000,
                'expected_retained_per_team' => 2,
            ]));

        $response->assertSessionHasErrors('max_budget_per_team');

        // Everything in the request goes down together — which is why the operator has to be
        // told, rather than left to conclude the form is broken.
        $auction->refresh();
        $this->assertNull($auction->default_retained_value);
        $this->assertNull($auction->expected_retained_per_team);
    }

    #[Test]
    public function the_error_is_rendered_on_the_page_the_operator_lands_back_on(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create(['name' => 'C', 'slug' => 'c', 'start_date' => '2026-01-01', 'organization_id' => $org->id]);
        $auction = $this->auction($org, $tournament);
        $admin = $this->admin($org);

        $this->actingAs($admin)
            ->from(route('admin.auctions.edit', $auction))
            ->put(route('admin.auctions.update', $auction), $this->payload($auction, ['max_budget_per_team' => -5]));

        $page = $this->actingAs($admin)->get(route('admin.auctions.edit', $auction));

        $page->assertOk();
        // The layout's banner, on the page the operator lands back on.
        $page->assertSee('Nothing was saved');
        $page->assertSee('max budget per team', false);
    }

    #[Test]
    public function the_wizard_reopens_on_the_step_that_failed_with_the_typed_value_still_there(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create(['name' => 'C', 'slug' => 'c', 'start_date' => '2026-01-01', 'organization_id' => $org->id]);
        $auction = $this->auction($org, $tournament);
        $admin = $this->admin($org);

        $this->actingAs($admin)
            ->from(route('admin.auctions.edit', $auction))
            ->put(route('admin.auctions.update', $auction), $this->payload($auction, [
                'max_budget_per_team' => -5,
                'expected_retained_per_team' => 2,
            ]));

        $html = $this->actingAs($admin)->get(route('admin.auctions.edit', $auction))->getContent();

        // The team budget lives on step 2 (Financials), not on the front of the wizard.
        $this->assertStringContainsString('auctionEditForm(', $html);
        $this->assertMatchesRegularExpression('/auctionEditForm\(.*, 2\)/s', $html);

        // The refused value is handed back, not replaced by the database's NULL. The state is
        // JSON inside the x-data attribute, so its quotes arrive HTML-escaped; and a number
        // typed into the form posts as a string while a test posts an int, so accept either.
        $decoded = html_entity_decode($html, ENT_QUOTES);
        $this->assertMatchesRegularExpression('/"max_budget_per_team":"?-5"?/', $decoded);
        $this->assertMatchesRegularExpression('/"expected_retained_per_team":"?2"?/', $decoded);
    }

    #[Test]
    public function a_valid_save_stores_every_field_that_was_going_down_with_the_refusal(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create(['name' => 'C', 'slug' => 'c', 'start_date' => '2026-01-01', 'organization_id' => $org->id]);
        $auction = $this->auction($org, $tournament);

        $this->actingAs($this->admin($org))
            ->put(route('admin.auctions.update', $auction), $this->payload($auction, [
                'min_squad_size' => 14,
                'default_retained_value' => 2000000,
                'expected_retained_per_team' => 2,
            ]))
            ->assertSessionHasNoErrors();

        $auction->refresh();
        // One Team Size, feeding both columns.
        $this->assertSame(14, $auction->min_squad_size);
        $this->assertSame(14, $auction->max_squad_size);
        $this->assertEquals(2000000, $auction->default_retained_value);
        $this->assertSame(2, $auction->expected_retained_per_team);
    }
}
