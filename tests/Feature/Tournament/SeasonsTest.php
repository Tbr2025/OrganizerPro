<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Models\ActualTeam;
use App\Models\Matches;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentSetting;
use App\Models\TournamentTemplate;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\RolesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * "Start new season": a new tournament in the same series, copying what was
 * ticked — as rows and files the new season owns.
 */
class SeasonsTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private User $admin;
    private Tournament $s1;
    /** @var ActualTeam[] */
    private array $teams = [];
    private TournamentTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        app(PermissionService::class)->createPermissions();
        app(RolesService::class)->createPredefinedRoles();
    }

    private function seasonOne(string $type = 'open'): void
    {
        $org = $this->makeOrganization('Org');
        $this->admin = User::factory()->create(['organization_id' => $org->id])->assignRole('Admin');

        Storage::disk('public')->put('tournaments/logos/s1.png', 'logo');
        $this->s1 = Tournament::create([
            'name' => 'Premier League', 'slug' => 'premier-league', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01',
            'organization_id' => $org->id, 'type' => $type, 'status' => 'completed', 'logo' => 'tournaments/logos/s1.png',
        ]);

        Storage::disk('public')->put('tournament_templates/bg1.png', 'bg');
        $this->template = TournamentTemplate::create([
            'tournament_id' => $this->s1->id, 'type' => 'welcome_card', 'name' => 'Welcome',
            'background_image' => 'tournament_templates/bg1.png', 'layout_json' => [], 'canvas_width' => 1080, 'canvas_height' => 1350,
            'is_default' => true, 'is_active' => true,
        ]);

        Storage::disk('public')->put('tournaments/settings-logo.png', 'x');
        TournamentSetting::create([
            'tournament_id' => $this->s1->id, 'logo' => 'tournaments/settings-logo.png',
            'primary_color' => '#112233', 'default_welcome_template_id' => $this->template->id,
            'registration_deadline' => '2026-01-10',
        ]);
        $this->s1->awards()->create(['name' => 'Player of the Match', 'slug' => 'potm', 'is_match_level' => true, 'is_active' => true, 'order' => 1]);
        $this->s1->customFields()->create(['form' => 'player', 'label' => 'Shirt name', 'type' => 'text', 'required' => false, 'visible' => true, 'sort_order' => 1]);

        foreach (['Lions', 'Tigers'] as $name) {
            Storage::disk('public')->put("team-logos/{$name}.png", $name);
            $team = ActualTeam::create([
                'name' => $name, 'organization_id' => $org->id, 'tournament_id' => $this->s1->id,
                'team_logo' => "team-logos/{$name}.png", 'invite_code' => strtoupper(substr($name, 0, 4)) . 'CODE1234',
            ]);
            $owner = User::factory()->create(['organization_id' => $org->id]);
            DB::table('actual_team_users')->insert(['actual_team_id' => $team->id, 'user_id' => $owner->id, 'role' => 'Owner', 'created_at' => now(), 'updated_at' => now()]);

            foreach (range(1, 3) as $n) {
                $this->squadPlayer($team, "{$name} {$n}", $org->id);
            }
            $this->teams[$name] = $team;
        }

        // Approved but unsold / not in a squad, and a pending one that must not carry over.
        $this->registration($this->player('Free Agent', $org->id), 'approved');
        $this->registration($this->player('Pending Pete', $org->id), 'pending');

        Matches::create([
            'tournament_id' => $this->s1->id, 'name' => 'Final', 'slug' => 'final-s1',
            'team_a_id' => $this->teams['Lions']->id, 'team_b_id' => $this->teams['Tigers']->id, 'status' => 'completed',
        ]);
    }

    private function player(string $name, int $orgId): Player
    {
        $user = User::factory()->create(['organization_id' => $orgId]);

        return Player::withoutOrganizationScope()->create(['organization_id' => $orgId, 'user_id' => $user->id, 'name' => $name, 'status' => 'approved']);
    }

    private function registration(Player $p, string $status, ?int $teamId = null): void
    {
        TournamentRegistration::create([
            'tournament_id' => $this->s1->id, 'organization_id' => $this->s1->organization_id, 'type' => 'player',
            'player_id' => $p->id, 'status' => $status, 'actual_team_id' => $teamId,
        ]);
    }

    private function squadPlayer(ActualTeam $team, string $name, int $orgId): void
    {
        $p = $this->player($name, $orgId);
        $this->registration($p, 'approved', $team->id);
        DB::table('player_actual_team_tournament')->insert(['player_id' => $p->id, 'actual_team_id' => $team->id, 'tournament_id' => $this->s1->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actual_team_users')->insert(['actual_team_id' => $team->id, 'user_id' => $p->user_id, 'role' => 'Player', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function start(array $copy, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->post(route('admin.tournaments.seasons.store', $this->s1), [
            'name' => 'Premier League — Season 2', 'start_date' => '2027-01-01', 'end_date' => '2027-02-01', 'copy' => $copy,
        ]);
    }

    private function season2(): Tournament
    {
        return Tournament::where('season_number', 2)->firstOrFail();
    }

    #[Test]
    public function an_open_tournament_season_copies_everything_ticked_as_its_own(): void
    {
        $this->seasonOne('open');
        $this->start(['teams', 'squads', 'templates', 'settings'])->assertRedirect();

        $s1 = $this->s1->fresh();
        $s2 = $this->season2();
        $this->assertSame($s1->series_id, $s2->series_id);
        $this->assertSame(1, $s1->season_number);
        $this->assertSame('draft', $s2->status);
        $this->assertSame('open', $s2->type);
        $this->assertSame('Premier League', $s1->series->name);
        $this->assertSame('premier-league-season-2', $s2->slug);

        // Teams: new rows, own files, staff kept, new invite codes.
        $teams = ActualTeam::where('tournament_id', $s2->id)->orderBy('name')->get();
        $this->assertSame(['Lions', 'Tigers'], $teams->pluck('name')->all());
        foreach ($teams as $t) {
            $old = $this->teams[$t->name];
            $this->assertNotSame($old->id, $t->id);
            $this->assertNotSame($old->team_logo, $t->team_logo);
            Storage::disk('public')->assertExists($t->team_logo);
            $this->assertNotSame($old->invite_code, $t->invite_code);
            $this->assertTrue(DB::table('actual_team_users')->where('actual_team_id', $t->id)->where('role', 'Owner')->exists());
            // Squad: 3 players per team, on the squad list and on the team for scoring.
            $this->assertSame(3, DB::table('player_actual_team_tournament')->where('tournament_id', $s2->id)->where('actual_team_id', $t->id)->count());
            $this->assertSame(3, DB::table('actual_team_users')->where('actual_team_id', $t->id)->where('role', 'Player')->count());
        }

        // Registrations: approved squad players + the approved free agent, all approved; pending stays behind.
        $regs = TournamentRegistration::where('tournament_id', $s2->id)->with('player')->get();
        $this->assertCount(7, $regs);
        $this->assertTrue($regs->every(fn ($r) => $r->status === 'approved'));
        $this->assertFalse($regs->contains(fn ($r) => $r->player->name === 'Pending Pete'));
        $this->assertNull($regs->first(fn ($r) => $r->player->name === 'Free Agent')->actual_team_id);

        // Templates and settings: copied with their own files; the default points at the copy.
        $tpl = TournamentTemplate::where('tournament_id', $s2->id)->firstOrFail();
        $this->assertNotSame($this->template->background_image, $tpl->background_image);
        Storage::disk('public')->assertExists($tpl->background_image);
        $settings = TournamentSetting::where('tournament_id', $s2->id)->firstOrFail();
        $this->assertSame($tpl->id, (int) $settings->default_welcome_template_id);
        $this->assertSame('#112233', $settings->primary_color);
        $this->assertNull($settings->registration_deadline);
        $this->assertNotSame('tournaments/settings-logo.png', $settings->logo);
        $this->assertSame(1, $s2->awards()->count());
        $this->assertSame(1, $s2->customFields()->count());

        // Never copied: fixtures.
        $this->assertSame(0, Matches::where('tournament_id', $s2->id)->count());

        // Season 2 replacing its logo cannot blank season 1's.
        Storage::disk('public')->delete($teams->first()->team_logo);
        Storage::disk('public')->assertExists($this->teams['Lions']->team_logo);
    }

    #[Test]
    public function nothing_ticked_creates_just_the_new_season(): void
    {
        $this->seasonOne();
        $this->start([])->assertRedirect();

        $s2 = $this->season2();
        $this->assertSame(0, ActualTeam::where('tournament_id', $s2->id)->count());
        $this->assertSame(0, TournamentRegistration::where('tournament_id', $s2->id)->count());
        $this->assertSame(0, TournamentTemplate::where('tournament_id', $s2->id)->count());
        $this->assertNull(TournamentSetting::where('tournament_id', $s2->id)->first());
    }

    #[Test]
    public function players_without_teams_on_an_open_tournament_bring_their_teams(): void
    {
        $this->seasonOne('open');
        $this->start(['squads'])->assertRedirect();

        $s2 = $this->season2();
        $this->assertSame(2, ActualTeam::where('tournament_id', $s2->id)->count());
        $this->assertSame(6, DB::table('player_actual_team_tournament')->where('tournament_id', $s2->id)->count());
    }

    #[Test]
    public function an_auction_season_starts_with_empty_teams_and_everyone_in_the_pool(): void
    {
        $this->seasonOne('auction');
        $this->start(['teams', 'squads'])->assertRedirect();

        $s2 = $this->season2();
        $teams = ActualTeam::where('tournament_id', $s2->id)->pluck('id');
        $this->assertCount(2, $teams);
        $this->assertSame(0, DB::table('player_actual_team_tournament')->where('tournament_id', $s2->id)->count());
        $this->assertSame(0, DB::table('actual_team_users')->whereIn('actual_team_id', $teams)->where('role', 'Player')->count());
        $this->assertSame(2, DB::table('actual_team_users')->whereIn('actual_team_id', $teams)->where('role', 'Owner')->count(), 'Owners still bid');

        $regs = TournamentRegistration::where('tournament_id', $s2->id)->get();
        $this->assertCount(7, $regs);
        $this->assertTrue($regs->every(fn ($r) => $r->status === 'approved' && $r->actual_team_id === null));
    }

    #[Test]
    public function an_organizer_runs_the_season_they_start_and_cannot_start_one_elsewhere(): void
    {
        $this->seasonOne();
        $organizer = User::factory()->create(['organization_id' => $this->s1->organization_id])->assignRole('Organizer');
        $this->s1->organizers()->attach($organizer->id);

        $this->start(['teams'], $organizer)->assertRedirect();
        $this->assertTrue(Tournament::forUser($organizer)->whereKey($this->season2()->id)->exists());

        $other = Tournament::create(['name' => 'Other Cup', 'slug' => 'other-cup', 'start_date' => '2026-01-01', 'organization_id' => $this->s1->organization_id]);
        $this->actingAs($organizer)->post(route('admin.tournaments.seasons.store', $other), [
            'name' => 'Other Cup 2', 'start_date' => '2027-01-01', 'end_date' => '2027-02-01',
        ])->assertForbidden();
    }

    #[Test]
    public function super_admin_links_and_unlinks_existing_tournaments_as_seasons(): void
    {
        $this->seasonOne();
        $super = User::factory()->create()->assignRole('Superadmin');
        $later = Tournament::create(['name' => 'Premier League 2025', 'slug' => 'pl-2025', 'start_date' => '2025-01-01', 'organization_id' => $this->s1->organization_id]);

        $this->actingAs($super)->post(route('admin.tournaments.seasons.link', $later), ['season_of_id' => $this->s1->id, 'season_number' => 2])->assertRedirect();
        $this->assertSame($this->s1->fresh()->series_id, $later->fresh()->series_id);
        $this->assertSame(2, $later->fresh()->season_number);

        // A taken number is refused.
        $third = Tournament::create(['name' => 'PL 2026', 'slug' => 'pl-2026', 'start_date' => '2026-03-01', 'organization_id' => $this->s1->organization_id]);
        $this->actingAs($super)->post(route('admin.tournaments.seasons.link', $third), ['season_of_id' => $this->s1->id, 'season_number' => 2])
            ->assertSessionHasErrors('season_number');

        // Unlink.
        $this->actingAs($super)->post(route('admin.tournaments.seasons.link', $later), ['season_of_id' => null])->assertRedirect();
        $this->assertNull($later->fresh()->series_id);

        // Only Super Admin.
        $this->actingAs($this->admin)->post(route('admin.tournaments.seasons.link', $third), ['season_of_id' => $this->s1->id])->assertForbidden();
    }

    #[Test]
    public function the_public_page_switches_between_published_seasons_only(): void
    {
        $this->seasonOne();
        $this->start([])->assertRedirect();
        $s2 = $this->season2();

        $this->get(route('public.tournament.show', $this->s1->slug))->assertOk()->assertDontSee('aria-label="Seasons"', false);

        $s2->update(['status' => 'registration']);
        $this->get(route('public.tournament.show', $this->s1->slug))->assertOk()
            ->assertSee('aria-label="Seasons"', false)->assertSee('Season 2');
    }

    #[Test]
    public function the_dashboard_offers_the_popup_with_everything_ticked(): void
    {
        $this->seasonOne();
        $html = $this->actingAs($this->admin)->get(route('admin.tournaments.dashboard', $this->s1))->assertOk()->getContent();

        $this->assertStringContainsString('Start new season', $html);
        foreach (['teams', 'squads', 'templates', 'settings'] as $c) {
            $this->assertMatchesRegularExpression('/name="copy\[\]" value="' . $c . '" checked/', $html);
        }
    }
}
