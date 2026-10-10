<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\ActualTeam;
use App\Models\ActualTeamUser;
use App\Models\Matches;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\RolesService;
use Illuminate\Support\Str;

/**
 * A one-over match between two squads of eleven (Lions = team A, Tigers =
 * team B) and a Scorer, plus helpers to toss and score through the real
 * endpoints. Shared by the scoring and LED wall tests.
 */
trait CreatesScoredMatch
{
    use CreatesAuctionScenario;

    protected Tournament $tournament;
    protected ActualTeam $lions;   // team A
    protected ActualTeam $tigers;  // team B
    protected Matches $match;
    protected User $scorer;
    /** @var array<string, int[]> squad row ids by team */
    protected array $squad = [];

    protected function setUpScoredMatch(): void
    {

        app(PermissionService::class)->createPermissions();
        app(RolesService::class)->createPredefinedRoles();

        $org = $this->makeOrganization();
        $this->tournament = $this->makeTournament($org, 'open');
        $this->lions = $this->makeTeam($org, 'Lions', $this->tournament);
        $this->tigers = $this->makeTeam($org, 'Tigers', $this->tournament);

        foreach (['lions' => $this->lions, 'tigers' => $this->tigers] as $key => $team) {
            foreach (range(1, 11) as $n) {
                $user = User::factory()->create(['organization_id' => $org->id]);
                Player::withoutOrganizationScope()->create([
                    'organization_id' => $org->id, 'user_id' => $user->id,
                    'name' => ucfirst($key) . " {$n}", 'status' => 'approved',
                ]);
                $this->squad[$key][$n] = ActualTeamUser::create([
                    'actual_team_id' => $team->id, 'user_id' => $user->id, 'role' => 'Player',
                ])->id;
            }
        }

        $this->match = Matches::create([
            'tournament_id' => $this->tournament->id,
            'name' => 'Final', 'slug' => 'final-' . uniqid(),
            'team_a_id' => $this->lions->id, 'team_b_id' => $this->tigers->id,
            'status' => 'upcoming', 'overs' => 1,
        ]);

        $this->scorer = User::factory()->create(['organization_id' => $org->id])->assignRole('Scorer');
    }

    protected function toss(ActualTeam $winner, string $decision): void
    {
        $this->actingAs($this->scorer)
            ->postJson(route('admin.matches.saveToss', $this->match), [
                'toss_winner_team_id' => $winner->id, 'toss_decision' => $decision,
            ])->assertOk();
    }

    /** Score one delivery; $bat/$bowl are squad keys like ['tigers', 1]. */
    protected function ball(array $striker, array $nonStriker, array $bowler, array $extra = [])
    {
        return $this->actingAs($this->scorer)->postJson(
            route('admin.balls.ajaxStore', $this->match),
            array_merge([
                'batsman_id' => $this->squad[$striker[0]][$striker[1]],
                'non_striker_id' => $this->squad[$nonStriker[0]][$nonStriker[1]],
                'bowler_id' => $this->squad[$bowler[0]][$bowler[1]],
                'runs' => 0,
                'event_uuid' => (string) Str::uuid(),
            ], $extra)
        );
    }

}
