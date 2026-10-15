<?php

declare(strict_types=1);

namespace Tests\Feature\Credits;

use App\Models\Ball;
use App\Models\Credits\CreditFeature;
use App\Models\Credits\CreditFeatureUnlock;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Credits\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesScoredMatch;
use Tests\TestCase;

/**
 * Credits are the only billing model: creating a tournament or a season pays the
 * tournament fee, live scoring is unlocked once per tournament (viewing a match
 * stays free), and the old package limits no longer decide anything.
 */
class CreditModulesTest extends TestCase
{
    use CreatesScoredMatch;
    use RefreshDatabase;

    private CreditService $credits;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScoredMatch();
        $this->credits = app(CreditService::class);
        $this->org = $this->tournament->organization;
    }

    private function price(string $key, int $cost): void
    {
        CreditFeature::where('key', $key)->update(['cost' => $cost, 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['organization_id' => $this->org->id])->assignRole('Admin');
    }

    private function newTournament(array $extra = []): array
    {
        return array_merge([
            'organization_id' => $this->org->id, 'name' => 'New Cup ' . uniqid(),
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeeks(2)->toDateString(),
            'status' => 'registration', 'type' => 'open',
        ], $extra);
    }

    #[Test]
    public function creating_a_tournament_shows_and_charges_the_fee(): void
    {
        $this->price(CreditFeature::TOURNAMENT_PUBLISH, 100);
        $admin = $this->admin();

        // Not enough: the page says so, the button is off, and a post creates nothing.
        $this->actingAs($admin)->get(route('admin.tournaments.create'))->assertOk()
            ->assertSee('(100 Credits)')->assertSee('Not enough Credits');
        $before = Tournament::count();
        $this->post(route('admin.tournaments.store'), $this->newTournament())->assertSessionHas('error');
        $this->assertSame($before, Tournament::count());

        // Enough: created, charged once, and published straight away.
        $this->credits->grant($this->org, 150, 'promo', 'test');
        $this->post(route('admin.tournaments.store'), $this->newTournament(['name' => 'Paid Cup']))->assertSessionHas('success');
        $t = Tournament::where('name', 'Paid Cup')->firstOrFail();
        $this->assertSame('registration', $t->status);
        $this->assertSame(50, $this->credits->balance($this->org->fresh()));
        $this->assertTrue($this->credits->hasUnlock($this->org, CreditFeature::TOURNAMENT_PUBLISH, $t));
    }

    #[Test]
    public function a_new_season_pays_the_tournament_fee(): void
    {
        $this->price(CreditFeature::TOURNAMENT_PUBLISH, 40);
        $admin = $this->admin();
        $season = ['name' => 'Season Two', 'start_date' => now()->addMonth()->toDateString(), 'end_date' => now()->addMonths(2)->toDateString()];

        $this->actingAs($admin)->get(route('admin.tournaments.dashboard', $this->tournament))->assertOk()->assertSee('(40 Credits)');
        $this->post(route('admin.tournaments.seasons.store', $this->tournament), $season)->assertSessionHas('error');
        $this->assertFalse(Tournament::where('name', 'Season Two')->exists());

        $this->credits->grant($this->org, 40, 'promo', 'test');
        $this->post(route('admin.tournaments.seasons.store', $this->tournament), $season)->assertRedirect();
        $this->assertTrue(Tournament::where('name', 'Season Two')->exists());
        $this->assertSame(0, $this->credits->balance($this->org->fresh()));
    }

    #[Test]
    public function super_admin_creates_free_and_never_unlocks_for_the_client(): void
    {
        $this->price(CreditFeature::TOURNAMENT_PUBLISH, 100);
        $this->price(CreditFeature::SCORING, 30);
        $super = User::factory()->create(['organization_id' => null])->assignRole('Superadmin');

        $this->actingAs($super)->post(route('admin.tournaments.store'), $this->newTournament(['name' => 'Support Cup']))->assertSessionHas('success');
        $this->assertTrue(Tournament::where('name', 'Support Cup')->exists());

        // Super Admin can score, but that hands the client nothing.
        $this->get(route('admin.matches.show', $this->match))->assertOk()->assertDontSee('Unlock live scoring');
        $this->assertSame(0, CreditFeatureUnlock::count());
        $this->assertSame(0, $this->credits->balance($this->org->fresh()));
    }

    #[Test]
    public function viewing_a_match_is_free_and_live_scoring_waits_for_its_unlock(): void
    {
        $this->price(CreditFeature::SCORING, 30);

        // The match opens, with the unlock popup; scoring calls are refused with 402.
        $this->actingAs($this->scorer)->get(route('admin.matches.show', $this->match))->assertOk()
            ->assertSee('Unlock live scoring')->assertSee('Ask your organization admin');
        $this->postJson(route('admin.matches.saveToss', $this->match), [
            'toss_winner_team_id' => $this->lions->id, 'toss_decision' => 'bat',
        ])->assertStatus(402)->assertJsonStructure(['message', 'unlock_url']);
        $this->actingAs($this->admin())->post(route('admin.matches.goLive', $this->match))->assertRedirectContains('/credits/unlock');

        // A viewer without scoring rights sees no popup.
        $viewer = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Team Manager');
        if ($viewer->can('match.view')) {
            $this->actingAs($viewer)->get(route('admin.matches.show', $this->match))->assertOk()->assertDontSee('Unlock live scoring');
        }

        // Paid once: the popup is gone and scoring works.
        $this->credits->grant($this->org, 30, 'promo', 'test');
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.matches.show', $this->match))->assertOk()->assertSee('Unlock (30 Credits)');
        $this->post(route('admin.credits.unlock.store'), ['feature' => 'scoring', 'tournament' => $this->tournament->id, 'return' => route('admin.matches.show', $this->match)])
            ->assertRedirect(route('admin.matches.show', $this->match));
        $this->assertSame(0, $this->credits->balance($this->org->fresh()));

        $this->actingAs($this->scorer)->get(route('admin.matches.show', $this->match))->assertOk()->assertDontSee('Unlock live scoring');
        $this->toss($this->lions, 'bat');
        $this->ball(['lions', 1], ['lions', 2], ['tigers', 1], ['runs' => 4])->assertOk();
        $this->assertSame(1, Ball::count());
    }

    #[Test]
    public function package_limits_no_longer_decide_anything(): void
    {
        $this->org->update(['billing_mode' => 'package', 'max_tournaments' => 1, 'auction_enabled' => false, 'auction_modes' => ['open']]);
        $org = $this->org->fresh();

        $this->assertTrue($org->usesCredits());
        $this->assertFalse($org->hasReachedTournamentLimit());
        $this->assertTrue($org->isAuctionEnabled());
        $this->assertTrue($org->isAuctionModeAllowed('closed'));

        // A free tournament fee: a capped package organization still creates.
        $this->actingAs($this->admin())->post(route('admin.tournaments.store'), $this->newTournament(['name' => 'Over The Cap']))->assertSessionHas('success');
        $this->assertTrue(Tournament::where('name', 'Over The Cap')->exists());

        // The organization page shows credits, not packages.
        $super = User::factory()->create(['organization_id' => null])->assignRole('Superadmin');
        $this->actingAs($super)->get(route('admin.organizations.edit', $org))->assertOk()
            ->assertDontSee('Package & Limits')->assertDontSee('Max Tournaments')->assertSee('Open wallet');
        $this->put(route('admin.organizations.update', $org), ['name' => 'Renamed', 'max_tournaments' => 3])->assertRedirect();
        $this->assertSame('Renamed', $org->fresh()->name);
        $this->assertSame(1, $org->fresh()->max_tournaments);
    }
}
