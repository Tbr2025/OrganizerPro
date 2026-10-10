<?php

declare(strict_types=1);

namespace Tests\Feature\Matches;

use App\Models\ActualTeam;
use App\Models\ActualTeamUser;
use App\Models\Matches;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\RolesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * "+ Add player" on the scoring screen: a walk-in player by name and phone
 * becomes selectable as batter/bowler without leaving the match.
 */
class QuickAddPlayerTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private Tournament $tournament;
    private ActualTeam $teamA;
    private ActualTeam $teamB;
    private Matches $match;
    private User $scorer;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionService::class)->createPermissions();
        app(RolesService::class)->createPredefinedRoles();

        $org = $this->makeOrganization();
        $this->tournament = $this->makeTournament($org, 'open');
        $this->teamA = $this->makeTeam($org, 'Lions', $this->tournament);
        $this->teamB = $this->makeTeam($org, 'Tigers', $this->tournament);
        $this->match = Matches::create([
            'tournament_id' => $this->tournament->id,
            'name' => 'Final', 'slug' => 'final-' . uniqid(),
            'team_a_id' => $this->teamA->id, 'team_b_id' => $this->teamB->id,
            'status' => 'live', 'overs' => 20,
        ]);

        $this->scorer = User::factory()->create(['organization_id' => $org->id])->assignRole('Scorer');
    }

    private function add(array $payload = [])
    {
        return $this->actingAs($this->scorer)->postJson(route('admin.matches.quick-player', $this->match), array_merge([
            'actual_team_id' => $this->teamA->id,
            'name' => 'Walk In',
            'mobile_country_code' => '+91',
            'mobile_national_number' => '98765 43210',
        ], $payload));
    }

    #[Test]
    public function a_scorer_adds_a_player_by_name_and_phone(): void
    {
        $response = $this->add()->assertOk()->assertJsonPath('status', 'added');

        $squadRow = ActualTeamUser::findOrFail($response->json('option.id'));
        $this->assertSame($this->teamA->id, (int) $squadRow->actual_team_id);

        $player = $squadRow->player;
        $this->assertSame('Walk In', $player->name);
        $this->assertSame('919876543210', $player->mobile_number_full);
        $this->assertSame('scoring', $player->created_via);
        $this->assertSame($this->match->id, (int) $player->source_match_id);
        $this->assertSame($this->scorer->id, (int) $player->created_by);
        $this->assertNull($player->email);

        // On the tournament squad list for that team.
        $this->assertDatabaseHas('player_actual_team_tournament', [
            'player_id' => $player->id, 'tournament_id' => $this->tournament->id, 'actual_team_id' => $this->teamA->id,
        ]);
    }

    #[Test]
    public function a_matching_phone_offers_the_existing_player_instead_of_a_duplicate(): void
    {
        $this->add();

        $this->add(['name' => 'Someone Else', 'actual_team_id' => $this->teamB->id, 'mobile_national_number' => '09876543210'])
            ->assertOk()
            ->assertJsonPath('status', 'match')
            ->assertJsonPath('candidates.0.name', 'Walk In')
            ->assertJsonMissingPath('candidates.0.mobile_number_full');

        $this->assertSame(1, Player::withoutOrganizationScope()->count());
    }

    #[Test]
    public function the_scorer_can_pick_the_existing_player_or_insist_on_a_new_one(): void
    {
        $first = Player::withoutOrganizationScope()->find(
            ActualTeamUser::find($this->add()->json('option.id'))->player->id
        );

        $this->add(['actual_team_id' => $this->teamB->id, 'use_player_id' => $first->id])
            ->assertJsonPath('status', 'added');
        $this->assertSame(1, Player::withoutOrganizationScope()->count());
        $this->assertTrue(ActualTeamUser::where('actual_team_id', $this->teamB->id)->where('user_id', $first->user_id)->exists());

        $this->add(['name' => 'Brother', 'force_new' => true])->assertJsonPath('status', 'added');
        $this->assertSame(2, Player::withoutOrganizationScope()->count());
    }

    #[Test]
    public function a_team_not_in_the_match_is_refused(): void
    {
        $other = $this->makeTeam($this->tournament->organization, 'Bystanders', $this->tournament);

        $this->add(['actual_team_id' => $other->id])->assertStatus(422);
        $this->assertSame(0, Player::withoutOrganizationScope()->count());
    }

    #[Test]
    public function a_scorer_from_another_organization_is_refused(): void
    {
        $outsider = User::factory()->create(['organization_id' => $this->makeOrganization('Other')->id])->assignRole('Scorer');

        $this->actingAs($outsider)->postJson(route('admin.matches.quick-player', $this->match), [
            'actual_team_id' => $this->teamA->id, 'name' => 'X Y',
            'mobile_country_code' => '+91', 'mobile_national_number' => '9000000000',
        ])->assertForbidden();
    }

    #[Test]
    public function it_needs_the_quick_add_permission(): void
    {
        $this->scorer->syncRoles(['Viewer']);

        $this->add()->assertForbidden();
    }

    #[Test]
    public function the_scoring_page_renders_the_add_buttons_and_modal(): void
    {
        $html = $this->actingAs($this->scorer)
            ->get(route('admin.matches.show', $this->match))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="quickPlayerModal"', $html);
        $this->assertStringContainsString("openQuickPlayer('batting', 'batsmanSelect')", $html);
        $this->assertStringContainsString("openQuickPlayer('bowling', 'bowlerSelect')", $html);
        $this->assertStringContainsString("openQuickPlayer('bowling', 'fielderSelect')", $html);
        $this->assertStringContainsString('function submitQuickPlayer', $html);
    }

    #[Test]
    public function the_buttons_are_hidden_without_the_permission(): void
    {
        $viewer = User::factory()->create(['organization_id' => $this->tournament->organization_id])->assignRole('Admin');
        $viewer->roles->first()->revokePermissionTo('scoring.quick_add_player');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $html = $this->actingAs($viewer->fresh())->get(route('admin.matches.show', $this->match))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="quickPlayerModal"', $html);
        $this->assertStringNotContainsString('openQuickPlayer(', $html);
    }
}
