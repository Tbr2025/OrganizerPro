<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentAward;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\RolesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The seeded roles against the routes that matter, as the server enforces them.
 *
 * Roles come from the real PermissionService/RolesService definitions, not
 * hand-made ones, so a permission dropped from a role in code shows up here.
 */
class RoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionService::class)->createPermissions();
        app(RolesService::class)->createPredefinedRoles();

        $this->org = Organization::create(['name' => 'Org A']);
        $this->tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-' . uniqid(),
            'start_date' => now()->addWeek(), 'organization_id' => $this->org->id, 'type' => 'auction',
        ]);
    }

    private function userWithRole(string $role, ?Organization $org = null): User
    {
        $user = User::factory()->create(['organization_id' => ($org ?? $this->org)->id]);
        $user->assignRole($role);

        // An Organizer only reaches tournaments they are assigned to.
        if (in_array($role, ['Organizer', 'Auction Organizer'], true)) {
            $this->tournament->organizers()->attach($user->id);
        }

        return $user;
    }

    public static function generatePosterAccess(): array
    {
        return [
            'Superadmin' => ['Superadmin', 200],
            'Admin' => ['Admin', 200],
            'Organizer' => ['Organizer', 200],
            'Auction Organizer' => ['Auction Organizer', 200],
            'Scorer' => ['Scorer', 403],
            'Viewer' => ['Viewer', 403],
        ];
    }

    #[Test]
    #[DataProvider('generatePosterAccess')]
    public function poster_generation_follows_template_generate(string $role, int $status): void
    {
        $this->actingAs($this->userWithRole($role))
            ->get(route('admin.tournaments.templates.generate', $this->tournament))
            ->assertStatus($status);
    }

    public static function templateBuildAccess(): array
    {
        return [
            'Superadmin' => ['Superadmin', 200],
            // Templates are designed by Super Admin only; organization users make posters.
            'Admin' => ['Admin', 403],
            'Organizer' => ['Organizer', 403],
            'Auction Organizer' => ['Auction Organizer', 403],
            'Team Manager' => ['Team Manager', 302], // sent to their own dashboard
            'Scorer' => ['Scorer', 403],
        ];
    }

    #[Test]
    #[DataProvider('templateBuildAccess')]
    public function building_templates_follows_template_manage(string $role, int $status): void
    {
        $this->actingAs($this->userWithRole($role))
            ->get(route('admin.tournaments.templates.create', $this->tournament))
            ->assertStatus($status);
    }

    public static function registrationAccess(): array
    {
        return [
            'Admin' => ['Admin', 200],
            'Organizer' => ['Organizer', 200],
            'Auction Organizer' => ['Auction Organizer', 200],
            'Scorer' => ['Scorer', 403],
            'Auctioneer' => ['Auctioneer', 200], // holds tournament.view
        ];
    }

    #[Test]
    #[DataProvider('registrationAccess')]
    public function registrations_list_access(string $role, int $status): void
    {
        $this->actingAs($this->userWithRole($role))
            ->get(route('admin.tournaments.registrations.index', $this->tournament))
            ->assertStatus($status);
    }

    #[Test]
    public function an_organizer_cannot_open_a_tournament_they_are_not_assigned_to(): void
    {
        $organizer = $this->userWithRole('Organizer');
        $other = Tournament::create([
            'name' => 'Other', 'slug' => 'other-' . uniqid(),
            'start_date' => now()->addWeek(), 'organization_id' => $this->org->id,
        ]);

        $this->actingAs($organizer)
            ->get(route('admin.tournaments.registrations.index', $other))
            ->assertForbidden();
    }

    #[Test]
    public function a_non_superadmin_cannot_create_a_tournament_in_another_organization(): void
    {
        $orgB = Organization::create(['name' => 'Org B']);

        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('admin.tournaments.store'), [
                'organization_id' => $orgB->id,
                'name' => 'Sneaky Cup',
                'start_date' => now()->addDay()->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tournaments', ['name' => 'Sneaky Cup']);
    }

    #[Test]
    public function an_organizer_keeps_sight_of_the_tournament_they_create(): void
    {
        $organizer = $this->userWithRole('Organizer');

        $this->actingAs($organizer)
            ->post(route('admin.tournaments.store'), [
                'organization_id' => $this->org->id,
                'name' => 'My Own Cup',
                'start_date' => now()->addDay()->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertRedirect();

        $created = Tournament::where('name', 'My Own Cup')->firstOrFail();
        $this->assertTrue(Tournament::forUser($organizer)->whereKey($created->id)->exists());
    }

    #[Test]
    public function an_award_cannot_be_deleted_through_another_tournament(): void
    {
        $other = Tournament::create([
            'name' => 'Other', 'slug' => 'other-' . uniqid(),
            'start_date' => now()->addWeek(), 'organization_id' => $this->org->id,
        ]);
        $award = TournamentAward::create(['tournament_id' => $other->id, 'name' => 'Player of the Match']);

        $this->actingAs($this->userWithRole('Admin'))
            ->delete(route('admin.tournaments.awards.destroy', [$this->tournament, $award]))
            ->assertNotFound();

        $this->assertNotNull($award->fresh());
    }

    #[Test]
    public function calendar_changes_need_tournament_edit(): void
    {
        $this->actingAs($this->userWithRole('Viewer'))
            ->post(route('admin.tournaments.calendar.clear-slots', $this->tournament))
            ->assertForbidden();
    }

    #[Test]
    public function bulk_delete_never_removes_a_superadmin(): void
    {
        $superadmin = $this->userWithRole('Superadmin');
        $target = $this->userWithRole('Superadmin');

        $this->actingAs($superadmin)
            ->delete(route('admin.users.bulk-delete'), ['ids' => [$target->id]]);

        $this->assertNotNull($target->fresh());
    }
}
