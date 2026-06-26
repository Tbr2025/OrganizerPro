<?php

namespace Tests\Feature\Matches;

use App\Models\ActualTeam;
use App\Models\Matches;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the Result + Summary page consolidation into the single "Manage Match"
 * page: the old result URL redirects onto the Result tab, and the summary URL
 * renders the unified `manage` view.
 */
class ManageMatchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Superadmin', 'web'));

        return $user;
    }

    private function makeMatch(): Matches
    {
        $org = Organization::create(['name' => 'Test Org']);
        $tournament = Tournament::create([
            'name' => 'Test Cup',
            'slug' => 'test-cup',
            'start_date' => '2026-01-01',
            'organization_id' => $org->id,
        ]);
        $teamA = ActualTeam::create(['name' => 'Alpha', 'organization_id' => $org->id]);
        $teamB = ActualTeam::create(['name' => 'Beta', 'organization_id' => $org->id]);

        return Matches::create([
            'name' => 'Alpha vs Beta',
            'slug' => 'alpha-vs-beta-test',
            'tournament_id' => $tournament->id,
            'team_a_id' => $teamA->id,
            'team_b_id' => $teamB->id,
        ]);
    }

    #[Test]
    public function old_result_url_redirects_to_the_summary_page_result_tab(): void
    {
        $match = $this->makeMatch();

        $this->actingAs($this->admin())
            ->get(route('admin.matches.result.edit', $match))
            ->assertRedirect(route('admin.matches.summary.edit', ['match' => $match, 'tab' => 'result']));
    }

    #[Test]
    public function summary_url_renders_the_unified_manage_view_with_both_tabs(): void
    {
        $match = $this->makeMatch();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.matches.summary.edit', $match));

        $response->assertOk();
        $response->assertViewIs('backend.pages.matches.manage');
        $response->assertSee('View Public Page');
    }
}
