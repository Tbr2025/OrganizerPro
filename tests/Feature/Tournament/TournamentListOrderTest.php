<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Models\Organization;
use App\Models\Tournament;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\RolesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The admin tournament list shows the most recently added or edited first. */
class TournamentListOrderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function recently_added_or_edited_tournaments_come_first(): void
    {
        app(PermissionService::class)->createPermissions();
        app(RolesService::class)->createPredefinedRoles();
        $org = Organization::create(['name' => 'Club']);
        $make = fn (string $name, string $at) => tap(Tournament::create([
            'name' => $name, 'slug' => \Str::slug($name), 'start_date' => now(), 'organization_id' => $org->id, 'status' => 'registration',
        ]), fn ($t) => Tournament::whereKey($t->id)->update(['created_at' => $at, 'updated_at' => $at]));

        $make('Old Cup', '2026-01-01');
        $edited = $make('Edited Cup', '2026-02-01');
        $make('New Cup', '2026-09-01');
        Tournament::whereKey($edited->id)->update(['updated_at' => '2026-10-01']);

        $this->actingAs(User::factory()->create()->assignRole('Superadmin'))
            ->get(route('admin.tournaments.index'))
            ->assertOk()
            ->assertSeeInOrder(['Edited Cup', 'New Cup', 'Old Cup']);
    }
}
