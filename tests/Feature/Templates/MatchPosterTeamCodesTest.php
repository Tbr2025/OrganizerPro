<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Http\Controllers\Backend\MatchesController;
use App\Models\ActualTeam;
use App\Models\Matches;
use App\Models\Organization;
use App\Models\Tournament;
use App\Services\Poster\EnhancedMatchPosterService;
use App\Services\Poster\TemplatePresetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A match poster fills teams, date and venue from the match. A team with no
 * short name gets a real code ("MM"), never its full name, which overflowed
 * the code slot and ran over the VS.
 */
class MatchPosterTeamCodesTest extends TestCase
{
    use RefreshDatabase;

    private function match(array $teamA = [], array $teamB = []): Matches
    {
        $org = Organization::create(['name' => 'Club']);
        $t = Tournament::create(['name' => 'Cup', 'slug' => 'cup-' . uniqid(), 'start_date' => now(), 'organization_id' => $org->id]);
        $a = ActualTeam::create(['name' => 'Mysore Maharajas', 'organization_id' => $org->id, 'tournament_id' => $t->id] + $teamA);
        $b = ActualTeam::create(['name' => 'Trivandrum Spartans', 'organization_id' => $org->id, 'tournament_id' => $t->id] + $teamB);

        return Matches::create([
            'tournament_id' => $t->id, 'name' => 'M34', 'slug' => 'm34-' . uniqid(), 'team_a_id' => $a->id, 'team_b_id' => $b->id,
            'status' => 'upcoming', 'overs' => 20, 'match_date' => '2026-10-09', 'start_time' => '17:30:00', 'venue' => 'DCS Arena 2',
        ]);
    }

    private function invokeBuilder(object $o, string $method, ...$args): array
    {
        $m = new \ReflectionMethod($o, $method);

        return $m->invoke($o, ...$args);
    }

    #[Test]
    public function both_poster_builders_fill_teams_date_venue_and_a_real_code(): void
    {
        $match = $this->match(['short_name' => ''], ['short_name' => null]);

        foreach ([
            $this->invokeBuilder(app(MatchesController::class), 'prepareMatchDataForTemplate', $match),
            $this->invokeBuilder(new EnhancedMatchPosterService(), 'prepareMatchData', $match),
        ] as $data) {
            $this->assertSame('Mysore Maharajas', $data['team_a_name']);
            $this->assertSame('Trivandrum Spartans', $data['team_b_name']);
            $this->assertSame('MM', $data['team_a_short_name']);
            $this->assertSame('TS', $data['team_b_short_name']);
            $this->assertSame('MM', $data['team_a_code']);
            $this->assertStringContainsString('DCS Arena 2', (string) ($data['venue'] ?? $data['ground_name'] ?? ''));
        }
    }

    #[Test]
    public function a_saved_short_name_is_kept(): void
    {
        $match = $this->match(['short_name' => 'MMS'], ['short_name' => 'TVS']);
        $data = $this->invokeBuilder(new EnhancedMatchPosterService(), 'prepareMatchData', $match);

        $this->assertSame('MMS', $data['team_a_short_name']);
        $this->assertSame('TVS', $data['team_b_code']);
    }

    #[Test]
    public function the_captains_design_uses_codes_that_shrink_to_fit(): void
    {
        $layout = app(TemplatePresetService::class)->find('match_captains')['layout'];
        $codes = collect($layout)->whereIn('placeholder', ['team_a_code', 'team_b_code']);

        $this->assertCount(2, $codes);
        $this->assertTrue($codes->every(fn ($e) => ($e['autoSize'] ?? false) === true));
    }
}
