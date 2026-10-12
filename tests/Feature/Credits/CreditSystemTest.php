<?php

declare(strict_types=1);

namespace Tests\Feature\Credits;

use App\Models\Credits\CreditFeature;
use App\Models\Credits\CreditFeatureUnlock;
use App\Models\Credits\CreditLedgerEntry;
use App\Models\Credits\CreditTopupRequest;
use App\Models\Credits\Discount;
use App\Models\Matches;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\PermissionService;
use App\Services\RolesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Pay-per-feature credits: the ledger, the gates and the pages.
 */
class CreditSystemTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private CreditService $credits;
    private Organization $org;
    private Tournament $tournament;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->createPermissions();
        app(RolesService::class)->createPredefinedRoles();
        $this->credits = app(CreditService::class);

        $this->org = Organization::create(['name' => 'Club', 'billing_mode' => 'credits']);
        $this->tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-' . uniqid(), 'start_date' => now()->addMonth(), 'end_date' => now()->addMonths(2),
            'organization_id' => $this->org->id, 'status' => 'draft', 'type' => 'open',
        ]);
        $this->admin = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Admin');
        $this->actingAs($this->admin);
    }

    private function price(string $key, int $cost): void
    {
        CreditFeature::where('key', $key)->update(['cost' => $cost, 'is_active' => true]);
    }

    private function fund(int $amount): void
    {
        $this->credits->grant($this->org, $amount, 'promo', 'test');
    }

    private function balance(): int
    {
        return $this->credits->balance($this->org->fresh());
    }

    // ---- the ledger ---------------------------------------------------------

    #[Test]
    public function the_control_centre_shows_every_tab_with_real_data_and_filters(): void
    {
        $this->price(CreditFeature::POSTER, 2);
        $this->fund(50);
        $this->credits->charge($this->org, CreditFeature::POSTER, null, 'cc-1');
        $this->credits->requestTopup($this->org, 100, null, 'UPI-123', 'please', $this->admin);
        \App\Models\Credits\Discount::create(['name' => 'Launch', 'code' => 'LAUNCH', 'type' => 'percent', 'value' => 10, 'applies_to' => 'features', 'is_active' => true]);

        $super = User::factory()->create()->assignRole('Superadmin');
        $page = $this->actingAs($super)->get(route('admin.credits.manage', ['tab' => 'topups']))->assertOk();
        $page->assertSee('control centre')->assertSee('UPI-123')->assertSee('LAUNCH')->assertSee('Adjust balance')
            ->assertSee('All wallets match their history')->assertSee('Club');

        // Filters narrow the history; unknown tabs fall back to the overview.
        $this->actingAs($super)->get(route('admin.credits.manage', ['tab' => 'activity', 'type' => 'usage', 'org' => $this->org->id]))->assertOk()->assertSee('Spent');
        $this->actingAs($super)->get(route('admin.credits.manage', ['tab' => 'organizations', 'mode' => 'credits', 'q' => 'Clu']))->assertOk()->assertSee('Club');
        $this->actingAs($super)->get(route('admin.credits.manage', ['tab' => 'nonsense']))->assertOk();

        // Approving with a note from the new form.
        $topup = \App\Models\Credits\CreditTopupRequest::firstOrFail();
        $this->actingAs($super)->post(route('admin.credits.topups.approve', $topup), ['admin_note' => 'Paid, thanks'])->assertRedirect();
        $this->assertSame('Paid, thanks', $topup->fresh()->admin_note);
        $this->assertSame(148, $this->credits->balance($this->org));
    }

    #[Test]
    public function the_header_balance_and_poster_cost_are_served_and_follow_spending(): void
    {
        $this->price(CreditFeature::POSTER, 3);
        $this->fund(10);

        $this->getJson(route('admin.credits.balance'))->assertOk()
            ->assertJson(['show' => true, 'balance' => 10, 'label' => '10 Credits', 'charges' => true, 'poster_cost' => 3, 'poster_label' => '3 Credits']);

        $this->credits->charge($this->org, CreditFeature::POSTER, null, 'k1');
        $this->getJson(route('admin.credits.balance'))->assertJson(['balance' => 7]);

        // A package-plan organization sees no pill.
        $this->org->update(['billing_mode' => 'package']);
        $this->getJson(route('admin.credits.balance'))->assertJson(['show' => false]);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.credits.balance'), false);
    }

    #[Test]
    public function the_wallet_opens_for_accounts_without_an_organization(): void
    {
        // Superadmin belongs to no organization: lands on one, can switch.
        $super = User::factory()->create(['organization_id' => null])->assignRole('Superadmin');
        $this->actingAs($super)->get(route('admin.credits.index'))->assertOk()->assertSee('Club');

        // An admin with no organization manages credits, so sees every wallet.
        $platformAdmin = User::factory()->create(['organization_id' => null])->assignRole('Admin');
        $this->actingAs($platformAdmin)->get(route('admin.credits.index'))->assertOk()->assertSee('Club');

        // Anyone else not linked to one is told why, not shown a bare 403.
        $loose = User::factory()->create(['organization_id' => null])->assignRole('Organizer');
        $this->actingAs($loose)->get(route('admin.credits.index'))
            ->assertRedirect(route('admin.dashboard'))->assertSessionHas('error');
    }

    #[Test]
    public function every_feature_starts_free_and_package_organizations_never_pay(): void
    {
        $this->assertSame(0, CreditFeature::sum('cost'), 'Nothing is charged until Super Admin sets a price');

        $this->price(CreditFeature::POSTER, 5);
        $package = Organization::create(['name' => 'Legacy']); // default billing_mode = package
        $this->assertNull($this->credits->charge($package, CreditFeature::POSTER));
        $this->assertSame(0, CreditLedgerEntry::count());
    }

    #[Test]
    public function a_charge_moves_the_balance_and_writes_one_row(): void
    {
        $this->price(CreditFeature::POSTER, 3);
        $this->fund(10);

        $entry = $this->credits->charge($this->org, CreditFeature::POSTER, $this->tournament);

        $this->assertSame(-3, $entry->amount);
        $this->assertSame(7, $entry->balance_after);
        $this->assertSame(7, $this->balance());
        $this->assertSame([], $this->credits->reconcile());
    }

    #[Test]
    public function not_enough_changes_nothing(): void
    {
        $this->price(CreditFeature::POSTER, 5);
        $this->fund(4);

        try {
            $this->credits->charge($this->org, CreditFeature::POSTER);
            $this->fail('Expected InsufficientCreditsException');
        } catch (InsufficientCreditsException $e) {
            $this->assertSame(5, $e->required);
            $this->assertSame(4, $e->balance);
        }

        $this->assertSame(4, $this->balance());
        $this->assertSame(1, CreditLedgerEntry::count(), 'Only the funding row');
    }

    #[Test]
    public function a_retried_charge_with_the_same_key_is_charged_once(): void
    {
        $this->price(CreditFeature::POSTER, 2);
        $this->fund(10);

        $a = $this->credits->charge($this->org, CreditFeature::POSTER, null, 'req-1');
        $b = $this->credits->charge($this->org, CreditFeature::POSTER, null, 'req-1');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(8, $this->balance());
    }

    #[Test]
    public function a_refund_gives_it_back_once_and_rows_are_immutable(): void
    {
        $this->price(CreditFeature::POSTER, 4);
        $this->fund(10);
        $charge = $this->credits->charge($this->org, CreditFeature::POSTER);

        $this->credits->refund($charge, 'render failed');
        $this->credits->refund($charge, 'render failed');
        $this->assertSame(10, $this->balance());

        $this->expectException(\LogicException::class);
        $charge->update(['amount' => 0]);
    }

    #[Test]
    public function a_superadmin_acting_on_a_client_never_spends_their_credits(): void
    {
        $this->price(CreditFeature::POSTER, 5);
        $super = User::factory()->create()->assignRole('Superadmin');

        $this->assertNull($this->credits->charge($this->org, CreditFeature::POSTER, null, null, 1, $super));
        $this->assertSame(0, $this->balance());
    }

    #[Test]
    public function super_admin_sees_the_prices_the_client_pays_on_their_wallet(): void
    {
        $this->price(CreditFeature::POSTER, 20);
        $this->price(CreditFeature::AUCTION, 2000);
        $super = User::factory()->create()->assignRole('Superadmin');

        $this->actingAs($super)->get(route('admin.credits.index', ['org' => $this->org->id]))
            ->assertOk()
            ->assertSee('20 Credits')
            ->assertSee('2,000 Credits')
            ->assertSee('You are never charged');

        // Still free for Super Admin to actually use.
        $this->assertSame(0, $this->credits->quote($this->org, CreditFeature::POSTER, 1, $super)['cost']);
    }

    #[Test]
    public function super_admin_sees_the_balance_of_the_organization_in_view(): void
    {
        $this->fund(2000);
        $super = User::factory()->create()->assignRole('Superadmin');
        $this->actingAs($super);

        $this->getJson(route('admin.credits.balance'))->assertJson(['show' => false]);
        $this->getJson(route('admin.credits.balance', ['org' => $this->org->id]))
            ->assertJson(['show' => true, 'balance' => 2000, 'label' => 'Club · 2,000 Credits']);
        $this->getJson(route('admin.credits.balance', ['tournament' => $this->tournament->id]))
            ->assertJson(['show' => true, 'balance' => 2000]);

        // The wallet page hands its organization to the header pill.
        $this->get(route('admin.credits.index', ['org' => $this->org->id]))
            ->assertSee(route('admin.credits.balance', ['org' => $this->org->id]), false);

        // An organization's own people never see another wallet through it.
        $other = Organization::create(['name' => 'Other', 'billing_mode' => 'credits']);
        $organizer = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Organizer');
        $this->actingAs($organizer)->getJson(route('admin.credits.balance', ['org' => $other->id]))
            ->assertJson(['balance' => 2000, 'label' => '2,000 Credits']);
    }

    #[Test]
    public function the_best_automatic_discount_applies_and_is_recorded(): void
    {
        $this->price(CreditFeature::POSTER, 10);
        $this->fund(100);
        Discount::create(['name' => '20% off', 'type' => 'percent', 'value' => 20, 'applies_to' => 'features', 'is_active' => true]);
        Discount::create(['name' => '3 off posters', 'type' => 'fixed', 'value' => 3, 'applies_to' => 'feature:poster', 'is_active' => true]);
        Discount::create(['name' => 'Expired', 'type' => 'percent', 'value' => 90, 'applies_to' => 'features', 'is_active' => true, 'ends_at' => now()->subDay()]);
        Discount::create(['name' => 'Other club only', 'type' => 'percent', 'value' => 90, 'applies_to' => 'features', 'is_active' => true, 'organization_ids' => [999999]]);

        $entry = $this->credits->charge($this->org, CreditFeature::POSTER);

        $this->assertSame(-7, $entry->amount, '3 off beats 20% of 10');
        $this->assertSame(1, \App\Models\Credits\DiscountRedemption::count());
    }

    #[Test]
    public function a_top_up_lands_once_when_super_admin_approves_it(): void
    {
        $this->credits->setSetting('money_per_credit', '10');
        Discount::create(['name' => 'Launch', 'code' => 'LAUNCH', 'type' => 'percent', 'value' => 10, 'applies_to' => 'topup', 'is_active' => true]);

        $this->post(route('admin.credits.topup'), ['credits' => 50, 'discount_code' => 'launch', 'payment_reference' => 'UPI-123'])->assertRedirect();
        $request = CreditTopupRequest::firstOrFail();
        $this->assertSame('450.00', (string) $request->price, '50 × 10 less 10%');
        $this->assertSame(0, $this->balance(), 'Nothing until it is approved');

        $super = User::factory()->create()->assignRole('Superadmin');
        $this->actingAs($super)->post(route('admin.credits.topups.approve', $request))->assertRedirect();
        $this->actingAs($super)->post(route('admin.credits.topups.approve', $request))->assertSessionHas('error');

        $this->assertSame(50, $this->balance());
        $this->assertSame('approved', $request->fresh()->status);
    }

    // ---- gates --------------------------------------------------------------

    #[Test]
    public function publishing_asks_first_then_charges_once_and_applies_the_status(): void
    {
        $this->price(CreditFeature::TOURNAMENT_PUBLISH, 5);

        $this->from(route('admin.tournaments.index'))
            ->put(route('admin.tournaments.settings.status', $this->tournament), ['status' => 'registration'])
            ->assertRedirectContains('/admin/credits/unlock');
        $this->assertSame('draft', $this->tournament->fresh()->status);

        // Not enough yet: the confirm page says so.
        $confirm = route('admin.credits.unlock', ['feature' => 'tournament_publish', 'tournament' => $this->tournament->id, 'status' => 'registration']);
        $this->get($confirm)->assertOk()->assertSee('You need 5 more', false);

        $this->fund(8);
        $this->get($confirm)->assertOk()->assertSee('Unlock for 5 Credits');
        $this->post(route('admin.credits.unlock.store'), ['feature' => 'tournament_publish', 'tournament' => $this->tournament->id, 'status' => 'registration'])->assertRedirect();

        $this->assertSame('registration', $this->tournament->fresh()->status);
        $this->assertSame(3, $this->balance());

        // Back to draft and out again: already paid for.
        $this->put(route('admin.tournaments.settings.status', $this->tournament), ['status' => 'draft']);
        $this->put(route('admin.tournaments.settings.status', $this->tournament), ['status' => 'active'])->assertSessionHas('success');
        $this->assertSame(3, $this->balance());
    }

    #[Test]
    public function a_free_feature_on_credits_records_the_unlock_and_package_organizations_skip_it(): void
    {
        $this->put(route('admin.tournaments.settings.status', $this->tournament), ['status' => 'registration'])->assertSessionHas('success');
        $this->assertTrue(CreditFeatureUnlock::where('feature_key', 'tournament_publish')->exists());

        $this->org->update(['billing_mode' => 'package']);
        $other = Tournament::create(['name' => 'Two', 'slug' => 'two-' . uniqid(), 'start_date' => now(), 'organization_id' => $this->org->id, 'status' => 'draft']);
        $this->put(route('admin.tournaments.settings.status', $other), ['status' => 'registration'])->assertSessionHas('success');
        $this->assertSame(1, CreditFeatureUnlock::count());
    }

    #[Test]
    public function the_led_wall_waits_for_its_unlock(): void
    {
        $this->price(CreditFeature::LEDWALL, 20);
        $a = $this->makeTeam($this->org, 'A', $this->tournament);
        $b = $this->makeTeam($this->org, 'B', $this->tournament);
        $match = Matches::create(['tournament_id' => $this->tournament->id, 'name' => 'M1', 'slug' => 'm1-' . uniqid(), 'team_a_id' => $a->id, 'team_b_id' => $b->id, 'status' => 'live']);

        $this->get(route('admin.matches.led', $match))->assertRedirectContains('feature=ledwall');

        $this->fund(20);
        $this->post(route('admin.credits.unlock.store'), ['feature' => 'ledwall', 'tournament' => $this->tournament->id, 'return' => route('admin.matches.led', $match)])
            ->assertRedirect(route('admin.matches.led', $match));
        $this->get(route('admin.matches.led', $match))->assertOk();
        $this->assertSame(0, $this->balance());
    }

    #[Test]
    public function the_confirm_page_never_redirects_off_site(): void
    {
        $this->fund(5);
        $this->post(route('admin.credits.unlock.store'), ['feature' => 'ledwall', 'tournament' => $this->tournament->id, 'return' => 'https://evil.example/steal'])
            ->assertRedirect(route('admin.credits.index'));
        $this->post(route('admin.credits.unlock.store'), ['feature' => 'ledwall', 'tournament' => $this->tournament->id, 'return' => '//evil.example'])
            ->assertRedirect(route('admin.credits.index'));
    }

    #[Test]
    public function generating_a_poster_charges_once_and_live_previews_are_free(): void
    {
        Storage::fake('public');
        $this->price(CreditFeature::POSTER, 2);
        $this->fund(5);
        $template = TournamentTemplate::create([
            'tournament_id' => $this->tournament->id, 'type' => 'flyer', 'name' => 'Flyer',
            'layout_json' => [], 'canvas_width' => 540, 'canvas_height' => 675, 'is_default' => true, 'is_active' => true,
        ]);
        $url = route('admin.tournaments.templates.generate-preview', $this->tournament);

        $this->postJson($url, ['template_id' => $template->id, 'live' => 1])->assertOk()->assertJsonPath('download_url', null);
        $this->assertSame(5, $this->balance(), 'Live preview is free');

        $this->postJson($url, ['template_id' => $template->id])->assertOk()->assertJsonPath('success', true);
        $this->assertSame(3, $this->balance(), 'Generate is one poster');

        $this->postJson($url, ['template_id' => $template->id])->assertOk();
        $this->assertSame(1, $this->balance());

        // 1 left, 2 needed: refused before rendering, with the shortfall.
        $this->postJson($url, ['template_id' => $template->id])->assertStatus(402)->assertJsonPath('required', 2);
        $this->assertSame(1, $this->balance());
    }

    #[Test]
    public function a_bulk_run_that_cannot_be_paid_for_does_not_start(): void
    {
        $this->price(CreditFeature::POSTER, 2);
        $this->fund(3);
        $a = $this->makeTeam($this->org, 'A', $this->tournament);
        $b = $this->makeTeam($this->org, 'B', $this->tournament);
        foreach (range(1, 3) as $i) {
            Matches::create(['tournament_id' => $this->tournament->id, 'name' => "M{$i}", 'slug' => "m{$i}-" . uniqid(), 'team_a_id' => $a->id, 'team_b_id' => $b->id, 'status' => 'upcoming']);
        }

        $this->from(route('admin.tournaments.fixtures.index', $this->tournament))
            ->post(route('admin.tournaments.fixtures.bulk-posters', $this->tournament))
            ->assertSessionHas('error');

        $this->assertSame(3, $this->balance(), 'Nothing charged, nothing half done');
    }

    // ---- pages --------------------------------------------------------------

    #[Test]
    public function the_wallet_and_admin_pages_render_for_the_right_people(): void
    {
        $this->price(CreditFeature::POSTER, 2);
        $this->fund(9);

        $this->get(route('admin.credits.index'))->assertOk()->assertSee('9')->assertSee('Generate a poster');
        // Admins run credits too; organizers only see their own wallet.
        $this->get(route('admin.credits.manage'))->assertOk();
        $organizer = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Organizer');
        $this->actingAs($organizer)->get(route('admin.credits.index'))->assertOk();
        $this->actingAs($organizer)->get(route('admin.credits.manage'))->assertForbidden();

        $super = User::factory()->create()->assignRole('Superadmin');
        $this->actingAs($super)->get(route('admin.credits.manage'))->assertOk()->assertSee('Club')->assertSee('All');
        $this->actingAs($super)->get(route('admin.credits.index', ['org' => $this->org->id]))->assertOk();
    }

    #[Test]
    public function super_admin_renames_credits_without_code(): void
    {
        $super = User::factory()->create()->assignRole('Superadmin');
        $this->actingAs($super)->post(route('admin.credits.settings.update'), [
            'money_per_credit' => 10, 'currency' => 'INR', 'term_singular' => 'Token', 'term_plural' => 'Tokens', 'welcome_credits' => 25,
        ])->assertRedirect();

        $this->assertSame('Tokens', $this->credits->term());
        $this->assertSame('1 Token', $this->credits->format(1));

        // New organizations start on credits with the welcome amount.
        $this->actingAs($super)->post(route('admin.organizations.store'), ['name' => 'Fresh Club'])->assertRedirect();
        $fresh = Organization::where('name', 'Fresh Club')->firstOrFail();
        $this->assertTrue($fresh->usesCredits());
        $this->assertSame(25, $this->credits->balance($fresh));
    }
}
