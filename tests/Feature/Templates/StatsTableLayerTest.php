<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\MatchResult;
use App\Models\Matches;
use App\Models\TournamentTemplate;
use App\Services\Poster\MatchStatsTableData;
use App\Services\Poster\TemplateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The stats tables a match-summary poster can draw as a layer.
 *
 * A table is a layout element like any other, so the failures that matter are structural: a
 * source the data builder never fills renders "No scorecard data" forever, and a style or
 * column set the editor can save but the renderer cannot read silently draws nothing.
 */
class StatsTableLayerTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function match(): Matches
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');
        $alpha = $this->makeTeam($org, 'Royal Strikers', $tournament);
        $beta = $this->makeTeam($org, 'Thunder Kings', $tournament);

        $match = Matches::create([
            'tournament_id' => $tournament->id,
            'name' => 'Royals vs Thunder',
            'slug' => 'royals-v-thunder-' . uniqid(),
            'team_a_id' => $alpha->id,
            'team_b_id' => $beta->id,
            'status' => 'completed',
            'stage' => 'league',
        ]);

        MatchResult::create([
            'match_id' => $match->id,
            'team_a_batting_first' => true,
            'scorecard_data' => [
                'innings' => [
                    [
                        'team_name' => 'Royal Strikers',
                        'total_runs' => 185, 'total_wickets' => 4, 'overs_played' => '20.0', 'total_extras' => 9,
                        'batting' => [
                            ['name' => 'Aaron Blake (c)', 'runs' => 88, 'balls' => 44, 'fours' => 9, 'sixes' => 4, 'how_out' => 'c Ali b Nair'],
                            ['name' => 'Dev Menon', 'runs' => 41, 'balls' => 30, 'fours' => 3, 'sixes' => 1, 'how_out' => 'run out (Ali)'],
                            ['name' => 'Sam Iyer', 'runs' => 12, 'balls' => 11, 'fours' => 1, 'sixes' => 0, 'how_out' => ''],
                        ],
                        'bowling' => [
                            ['name' => 'Rohan Nair', 'overs' => '4', 'runs' => 28, 'wickets' => 2, 'maidens' => 0, 'economy' => '7.00'],
                        ],
                        'fall_of_wickets' => [
                            ['over' => '6.3', 'runs' => 82, 'wicket' => 1, 'player_name' => 'Dev Menon'],
                        ],
                    ],
                    [
                        'team_name' => 'Thunder Kings',
                        'total_runs' => 172, 'total_wickets' => 8, 'overs_played' => '20.0', 'total_extras' => 13,
                        'batting' => [
                            ['name' => 'Imran Ali', 'runs' => 64, 'balls' => 40, 'fours' => 5, 'sixes' => 3, 'how_out' => 'b Blake'],
                        ],
                        'bowling' => [
                            ['name' => 'Aaron Blake', 'overs' => '3.4', 'runs' => 19, 'wickets' => 4, 'maidens' => 1, 'economy' => '5.18'],
                        ],
                    ],
                ],
            ],
        ]);

        return $match->fresh(['result', 'teamA', 'teamB']);
    }

    #[Test]
    public function the_builder_fills_every_source_the_editor_offers(): void
    {
        $data = MatchStatsTableData::build($this->match(), ['a' => 'RS', 'b' => 'TK']);

        foreach (MatchStatsTableData::KEYS as $key) {
            if ($key === 'fall_of_wickets_b') {
                continue; // this card only records them for the first innings
            }
            $this->assertArrayHasKey($key, $data, "Source {$key} is offered but never filled.");
            $this->assertNotEmpty($data[$key], "Source {$key} came back empty.");
        }
    }

    #[Test]
    public function team_a_is_the_side_that_batted_first_and_bowling_belongs_to_the_other(): void
    {
        $data = MatchStatsTableData::build($this->match(), ['a' => 'RS', 'b' => 'TK']);

        $this->assertSame('Aaron Blake', $data['batting_table_a'][0]['name'], 'The role suffix must be stripped.');
        $this->assertSame(88, $data['batting_table_a'][0]['runs']);

        // Rohan Nair bowled in the FIRST innings, so he is Team B's bowler.
        $this->assertSame('Rohan Nair', $data['bowling_table_b'][0]['name']);
        $this->assertSame('Aaron Blake', $data['bowling_table_a'][0]['name']);
    }

    #[Test]
    public function the_match_wide_tables_rank_both_sides_together(): void
    {
        $data = MatchStatsTableData::build($this->match(), ['a' => 'RS', 'b' => 'TK']);

        $this->assertSame('Aaron Blake', $data['top_batting'][0]['name'], '88 leads the match.');
        $this->assertSame('RS', $data['top_batting'][0]['team']);
        $this->assertSame('Imran Ali', $data['top_batting'][1]['name']);
        $this->assertSame('TK', $data['top_batting'][1]['team']);

        $this->assertSame('Aaron Blake', $data['top_bowling'][0]['name'], '4 wickets beats 2.');
        $this->assertSame(4, $data['top_bowling'][0]['wickets']);
    }

    #[Test]
    public function the_innings_summary_carries_score_overs_and_run_rate(): void
    {
        $data = MatchStatsTableData::build($this->match(), ['a' => 'RS', 'b' => 'TK']);

        $this->assertSame('RS', $data['match_summary_table'][0]['team']);
        $this->assertSame('185/4', $data['match_summary_table'][0]['score']);
        $this->assertSame('9.25', $data['match_summary_table'][0]['run_rate']);
        $this->assertSame('172/8', $data['match_summary_table'][1]['score']);
    }

    private function template(array $config, string $source): TournamentTemplate
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');

        return $tournament->templates()->create([
            'name' => 'Summary',
            'type' => TournamentTemplate::TYPE_MATCH_SUMMARY,
            'canvas_width' => 1080,
            'canvas_height' => 1080,
            'placeholders' => TournamentTemplate::getDefaultPlaceholders(TournamentTemplate::TYPE_MATCH_SUMMARY),
            'layout_json' => [[
                'type' => 'scorecardTable',
                'placeholder' => $source,
                'x' => 50, 'y' => 50, 'width' => 800, 'height' => 300, 'zIndex' => 1,
                'scorecardConfig' => array_merge(['source' => $source, 'maxRows' => 3], $config),
            ]],
        ]);
    }

    #[Test]
    public function every_style_draws_something_and_they_do_not_all_look_alike(): void
    {
        Storage::fake('public');
        $render = new TemplateRenderService();
        $data = MatchStatsTableData::build($this->match(), ['a' => 'RS', 'b' => 'TK']);

        $renders = [];
        foreach (['classic', 'minimal', 'striped', 'card', 'outline', 'gradient', 'glass'] as $style) {
            $template = $this->template(['style' => $style], 'top_batting');
            $path = $render->renderTemplate($template, $data, true, true);
            $renders[$style] = Storage::disk('public')->get($path);
            $this->assertNotEmpty($renders[$style], "Style {$style} produced nothing.");
        }

        $this->assertCount(
            count($renders),
            array_unique(array_map('md5', $renders)),
            'Two styles rendered byte-identical posters — one of them is not being applied.'
        );
    }

    #[Test]
    public function a_style_still_owns_its_border_radius_and_opacity_once_a_template_is_saved(): void
    {
        Storage::fake('public');
        $render = new TemplateRenderService();
        $data = MatchStatsTableData::build($this->match(), ['a' => 'RS', 'b' => 'TK']);

        /*
         * The full config the editor writes on save.
         *
         * cornerRadius, borderWidth and panelOpacity are the three properties a STYLE owns, and
         * both renderers read them as `config.x ?? style.x`. The editor used to save a neutral
         * 0 / 0 / 100 for them, which satisfied the `??` and killed the style for good — outline
         * lost its border, card and glass their rounding, glass its translucency. It only showed
         * up on saved templates, so a test passing a bare ['style' => x] never saw it.
         */
        $saved = [
            'maxRows' => 3, 'title' => '', 'titleAlign' => 'left',
            'showTitle' => true, 'showColumnHeaders' => true,
            'uppercaseNames' => false, 'transparentBg' => false,
            'columns' => ['_rank', 'name', 'team', 'runs', 'balls', 'strike_rate'],
            'headerBg' => '#1e40af', 'headerBg2' => '#3b82f6', 'headerText' => '#ffffff',
            'rowBg' => '#1e293b', 'altRowBg' => '#334155',
            'textColor' => '#ffffff', 'mutedColor' => '#94a3b8', 'accentColor' => '#FFD700',
            'panelBg' => '#0f172a', 'borderColor' => '#FFD700',
            'fontSize' => 14, 'rowHeight' => 40, 'headerHeight' => 34, 'padding' => 12,
            'fontFamily' => 'Montserrat',
        ];

        $renders = [];
        foreach (array_keys(TemplateRenderService::statsTableDefinitions()['styles']) as $style) {
            $template = $this->template($saved + ['style' => $style], 'top_batting');
            $renders[$style] = Storage::disk('public')->get($render->renderTemplate($template, $data, true, true));
        }

        $this->assertCount(
            count($renders),
            array_unique(array_map('md5', $renders)),
            'Two styles rendered identically from a saved config — a style-owned property is being masked.'
        );

        // And an explicit override still beats the style, which is the whole point of the field.
        $plain = $render->renderTemplate($this->template($saved + ['style' => 'outline'], 'top_batting'), $data, true, true);
        $thick = $render->renderTemplate($this->template($saved + ['style' => 'outline', 'borderWidth' => 9], 'top_batting'), $data, true, true);

        $this->assertNotSame(
            Storage::disk('public')->get($plain),
            Storage::disk('public')->get($thick),
            'An explicit borderWidth was ignored.'
        );
    }

    #[Test]
    public function the_editor_never_saves_the_three_properties_a_style_owns(): void
    {
        $editor = file_get_contents(resource_path('views/backend/pages/tournaments/templates/editor.blade.php'));

        $start = strpos($editor, 'statsDefaultConfig(source) {');
        $this->assertNotFalse($start);
        $defaults = substr($editor, $start, 1400);

        foreach (['cornerRadius', 'borderWidth', 'panelOpacity'] as $owned) {
            $this->assertStringNotContainsString(
                $owned . ':',
                $defaults,
                "statsDefaultConfig() writes {$owned}, which masks the style's own value on every saved template."
            );
        }
    }

    #[Test]
    public function choosing_columns_changes_what_is_drawn(): void
    {
        Storage::fake('public');
        $render = new TemplateRenderService();
        $data = MatchStatsTableData::build($this->match(), ['a' => 'RS', 'b' => 'TK']);

        $wide = $render->renderTemplate(
            $this->template(['columns' => ['_rank', 'name', 'team', 'runs', 'balls', 'strike_rate']], 'top_batting'),
            $data, true, true
        );
        $narrow = $render->renderTemplate(
            $this->template(['columns' => ['name', 'runs']], 'top_batting'),
            $data, true, true
        );

        $this->assertNotSame(
            Storage::disk('public')->get($wide),
            Storage::disk('public')->get($narrow),
            'Turning columns off changed nothing on the poster.'
        );
    }

    #[Test]
    public function a_template_saved_before_sources_existed_still_draws_the_same_table(): void
    {
        Storage::fake('public');
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');

        // The old shape: no `source`, only scorecardType + team.
        $legacy = $tournament->templates()->create([
            'name' => 'Legacy',
            'type' => TournamentTemplate::TYPE_MATCH_SUMMARY,
            'canvas_width' => 1080, 'canvas_height' => 1080,
            'placeholders' => TournamentTemplate::getDefaultPlaceholders(TournamentTemplate::TYPE_MATCH_SUMMARY),
            'layout_json' => [[
                'type' => 'scorecardTable',
                'placeholder' => 'bowling_table_a',
                'x' => 50, 'y' => 50, 'width' => 800, 'height' => 300, 'zIndex' => 1,
                'scorecardConfig' => ['scorecardType' => 'bowling', 'team' => 'a', 'maxRows' => 3],
            ]],
        ]);

        $data = MatchStatsTableData::build($this->match(), ['a' => 'RS', 'b' => 'TK']);
        $path = (new TemplateRenderService())->renderTemplate($legacy, $data, true, true);

        $this->assertTrue(Storage::disk('public')->exists($path));

        // The same table asked for the new way has to come out identical.
        $modern = $this->template(['scorecardType' => null, 'team' => null], 'bowling_table_a');
        $modernPath = (new TemplateRenderService())->renderTemplate($modern, $data, true, true);

        $this->assertSame(
            Storage::disk('public')->get($path),
            Storage::disk('public')->get($modernPath),
            'A legacy scorecardTable no longer renders what it used to.'
        );
    }

    #[Test]
    public function the_editor_can_add_load_and_save_every_source(): void
    {
        $editor = file_get_contents(resource_path('views/backend/pages/tournaments/templates/editor.blade.php'));

        // Serializing by elementType and loading by item.type are two separate branches, and a
        // table that only has one of them is destroyed the first time somebody opens the
        // template and hits save.
        $this->assertStringContainsString("item.type === 'scorecardTable'", $editor, 'The editor cannot load a stats table back onto the canvas.');
        $this->assertStringContainsString("obj.elementType === 'scorecardTable'", $editor, 'The editor cannot serialize a stats table.');
        $this->assertStringContainsString('buildStatsTableGroup', $editor);

        foreach (MatchStatsTableData::KEYS as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $editor,
                "The editor never offers {$key}, so nothing can ever draw it."
            );
        }
    }

    #[Test]
    public function the_editor_and_the_renderer_share_one_set_of_column_definitions(): void
    {
        $defs = TemplateRenderService::statsTableDefinitions();

        $this->assertArrayHasKey('columns', $defs);
        $this->assertArrayHasKey('defaultColumns', $defs);
        $this->assertArrayHasKey('styles', $defs);

        // Every default column set has to name columns that actually exist for its kind,
        // otherwise the table silently falls back and the design is not what was chosen.
        foreach ($defs['defaultColumns'] as $source => $columns) {
            $kind = match (true) {
                $source === 'match_summary_table' => 'summary',
                str_starts_with($source, 'fall_of_wickets') => 'fow',
                $source === 'top_bowling', str_starts_with($source, 'bowling_table') => 'bowling',
                default => 'batting',
            };

            foreach ($columns as $column) {
                $this->assertArrayHasKey(
                    $column,
                    $defs['columns'][$kind],
                    "Source {$source} defaults to column {$column}, which {$kind} tables do not have."
                );
            }
        }
    }

    #[Test]
    public function a_source_with_no_data_says_so_instead_of_failing(): void
    {
        Storage::fake('public');

        $path = (new TemplateRenderService())->renderTemplate(
            $this->template([], 'fall_of_wickets_b'),
            ['team_a_name' => 'RS', 'team_b_name' => 'TK'],
            true,
            true
        );

        $this->assertTrue(Storage::disk('public')->exists($path));
    }
}
