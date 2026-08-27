<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\Matches;
use App\Models\MatchResult;
use App\Models\TournamentTemplate;
use App\Services\Poster\TemplatePresetService;
use App\Services\Poster\TemplateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The two ready-made poster designs, and the line-up region one of them is built on.
 *
 * Every tournament used to start each template from a blank canvas, so the realistic outcome
 * was a square with a name on it. A preset is an authored `layout_json` — the same thing the
 * drag editor saves — which means the interesting failures are not visual: a preset that names
 * a placeholder the type does not offer renders blank forever, and a region the editor cannot
 * serialize is destroyed the first time somebody opens it and hits save.
 */
class PosterPresetTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function every_preset_only_asks_for_placeholders_its_own_type_offers(): void
    {
        $presets = (new TemplatePresetService())->all();

        $this->assertNotEmpty($presets);

        foreach ($presets as $key => $preset) {
            $offered = TournamentTemplate::getDefaultPlaceholders($preset['type']);
            $this->assertNotEmpty($offered, "Type {$preset['type']} offers no placeholders at all.");

            foreach ($preset['layout'] as $element) {
                $placeholder = $element['placeholder'] ?? null;
                if ($placeholder === null) {
                    continue; // static text and decoration answer to nobody
                }

                $this->assertContains(
                    $placeholder,
                    $offered,
                    "Preset [{$key}] places {$placeholder}, which {$preset['type']} never fills — it would render blank on every poster."
                );
            }
        }
    }

    #[Test]
    public function a_preset_lands_as_an_ordinary_template_and_never_steals_the_default(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        // Something already IS the default, and applying a design must not change what the
        // automatic emails and summary jobs send.
        $incumbent = $tournament->templates()->create([
            'type' => TournamentTemplate::TYPE_MATCH_SUMMARY,
            'name' => 'Incumbent',
            'is_default' => true,
            'is_active' => true,
        ]);

        $service = new TemplatePresetService();
        $applied = $service->apply($tournament, 'stumps_score_update');

        $this->assertSame(TournamentTemplate::TYPE_MATCH_SUMMARY, $applied->type);
        $this->assertFalse($applied->is_default, 'Applying a design must not promote it over the existing default.');
        $this->assertTrue($applied->is_active);
        $this->assertTrue($incumbent->fresh()->is_default, 'The existing default should be untouched.');
        $this->assertSame(1080, $applied->canvas_width);
        $this->assertNotEmpty($applied->layout_json);

        // Applying the same design twice is normal — one to keep, one to experiment on — and
        // two rows sharing a name is unreadable in the picker.
        $second = $service->apply($tournament, 'stumps_score_update');
        $this->assertNotSame($applied->name, $second->name);
    }

    #[Test]
    public function the_eleven_is_one_region_the_editor_can_save_again(): void
    {
        $preset = (new TemplatePresetService())->find('playing_xi_classic');
        $this->assertNotNull($preset);

        $lineups = array_values(array_filter(
            $preset['layout'],
            fn ($el) => ($el['type'] ?? '') === 'lineupArea'
        ));

        $this->assertCount(1, $lineups, 'The XI should be a single region, not eleven text elements.');
        $this->assertSame('lineup_area', $lineups[0]['placeholder']);
        $this->assertNotEmpty($lineups[0]['lineupConfig'], 'Without a config the renderer falls back to its own defaults silently.');

        /*
         * The editor serializes by elementType and drops anything it does not recognise back to
         * a bare element — losing the type, the size and the config. That makes "can the editor
         * write this type back out" a correctness question, not a cosmetic one.
         */
        $editor = file_get_contents(resource_path('views/backend/pages/tournaments/templates/editor.blade.php'));
        $this->assertStringContainsString("obj.elementType === 'lineupArea'", $editor, 'The editor cannot serialize a lineup region, so saving would destroy it.');
        $this->assertStringContainsString("item.type === 'lineupArea'", $editor, 'The editor cannot load a lineup region back onto the canvas.');
    }

    #[Test]
    public function a_featured_player_can_be_named_by_hand_and_the_design_prints_it(): void
    {
        Storage::fake('public');

        $preset = (new TemplatePresetService())->find('playing_xi_classic');

        /*
         * `featured_player_name` was offered as a placeholder while no element in the design
         * drew it, so typing a name for an uploaded photo did nothing at all. A placeholder the
         * type advertises but no layout prints is indistinguishable from a broken field.
         */
        $caption = collect($preset['layout'])->firstWhere('placeholder', 'featured_player_name');
        $this->assertNotNull($caption, 'The design must actually print the featured player name.');
        $this->assertSame('text', $caption['type']);

        // And it must be a real placeholder element, so skipBlanks drops it when nobody is named
        // rather than printing the literal fallback text on every poster.
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');
        $template = (new TemplatePresetService())->apply($tournament, 'playing_xi_classic');

        $render = new TemplateRenderService();
        $named = $render->renderTemplate($template, [
            'featured_player_name' => 'A Guest Player',
            'lineup_area' => [['name' => 'A Guest Player', 'badge' => 'DEBUT']],
        ], true, true);
        $unnamed = $render->renderTemplate($template, [
            'lineup_area' => [['name' => 'A Guest Player', 'badge' => 'DEBUT']],
        ], true, true);

        $this->assertNotSame(
            Storage::disk('public')->get($named),
            Storage::disk('public')->get($unnamed),
            'Naming the featured player changed nothing on the poster.'
        );
    }

    #[Test]
    public function a_lineup_draws_its_names_and_shrugs_off_a_missing_or_broken_xi(): void
    {
        Storage::fake('public');

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $template = (new TemplatePresetService())->apply($tournament, 'playing_xi_classic');

        $render = new TemplateRenderService();

        // The ordinary case: real names, one with a chip.
        $path = $render->renderTemplate($template, [
            'lineup_area' => [
                ['name' => 'Imtiaz Khan', 'badge' => 'WK'],
                ['name' => 'Yusuf Quilon', 'badge' => ''],
                ['name' => 'A Player With A Very Long Name Indeed', 'badge' => 'DEBUT'],
            ],
        ], true);
        $this->assertTrue(Storage::disk('public')->exists($path));

        /*
         * And the cases a poster page can actually produce: nobody picked yet, the payload
         * arrived as a JSON string rather than an array, and a row with no name in it. Each of
         * these used to be a 500 on a page whose only job is to draw a picture.
         */
        foreach ([[], '', 'not json at all', '[]', [['badge' => 'C']], null] as $i => $broken) {
            $out = $render->renderTemplate($template, ['lineup_area' => $broken], true);
            $this->assertTrue(
                Storage::disk('public')->exists($out),
                "A lineup region should still render with input #{$i}."
            );
        }
    }

    #[Test]
    public function the_remove_background_choice_is_honoured_and_scoped_to_the_uploaded_image(): void
    {
        Storage::fake('public');

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');
        $template = (new TemplatePresetService())->apply($tournament, 'playing_xi_classic');

        // A photo with a flat, obviously-removable backdrop, so the two renders must differ.
        $img = imagecreatetruecolor(300, 400);
        imagefilledrectangle($img, 0, 0, 300, 400, imagecolorallocate($img, 40, 170, 90));
        imagefilledellipse($img, 150, 120, 110, 130, imagecolorallocate($img, 225, 190, 160));
        ob_start();
        imagepng($img);
        Storage::disk('public')->put('temp_previews/subject.png', ob_get_clean());

        $data = [
            'lineup_area' => [['name' => 'A Player', 'badge' => 'C']],
            'featured_player_image' => 'temp_previews/subject.png',
        ];

        $kept = (new TemplateRenderService())
            ->overrideBackgroundRemoval('featured_player_image', false)
            ->renderTemplate($template, $data, true);

        $removed = (new TemplateRenderService())
            ->overrideBackgroundRemoval('featured_player_image', true)
            ->renderTemplate($template, $data, true);

        /*
         * The generate page posted "Remove Background" long before anything read it, so the box
         * could be ticked or not and the poster came out identical. Comparing the two renders is
         * the assertion that actually catches that regression coming back.
         */
        $this->assertNotSame(
            Storage::disk('public')->get($kept),
            Storage::disk('public')->get($removed),
            'Removing the background produced a byte-identical poster, so the choice is being ignored.'
        );

        /*
         * And the choice must not leak. A single global flag would have started cutting the
         * background out of team crests too — which for a round logo on a pale panel means
         * eating the logo.
         */
        $service = new TemplateRenderService();
        $service->overrideBackgroundRemoval('featured_player_image', true);
        $method = new \ReflectionMethod($service, 'shouldRemoveBackground');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, 'featured_player_image'));
        $this->assertFalse($method->invoke($service, 'team_a_logo'), 'A crest must never be background-removed.');
        $this->assertFalse($method->invoke($service, 'tournament_logo'));
    }

    #[Test]
    public function a_label_tied_to_a_blank_value_does_not_print_on_its_own(): void
    {
        Storage::fake('public');

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');
        $template = (new TemplatePresetService())->apply($tournament, 'stumps_score_update');

        $overs = collect($template->layout_json)
            ->firstWhere('layerName', 'Team A overs label');

        $this->assertNotNull($overs);
        $this->assertSame(
            'team_a_overs',
            $overs['dependsOn'] ?? null,
            'The static "OVERS" label must be tied to the figure it labels, or an unplayed match prints a bare OVERS next to nothing.'
        );

        // skipBlanks on (real data, no result) must drop it; the editor preview keeps it.
        $render = new TemplateRenderService();
        $withResult = $render->renderTemplate($template, ['team_a_overs' => '20.0'], true, true);
        $withoutResult = $render->renderTemplate($template, [], true, true);

        $this->assertNotSame(
            Storage::disk('public')->get($withResult),
            Storage::disk('public')->get($withoutResult)
        );
    }

    #[Test]
    public function any_field_can_be_retyped_or_hidden_except_the_ones_read_off_the_match(): void
    {
        Storage::fake('public');

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');
        $template = (new TemplatePresetService())->apply($tournament, 'stumps_score_update');

        $layout = $template->layout_json;
        $indexOf = fn (string $needle) => collect($layout)
            ->search(fn ($el) => ($el['placeholder'] ?? null) === $needle || ($el['layerName'] ?? null) === $needle);

        $venue = $indexOf('venue');
        $score = $indexOf('team_a_score_wickets');
        $label = $indexOf('Team A overs label');   // static text, no placeholder at all

        $this->assertNotFalse($venue);
        $this->assertNotFalse($score);
        $this->assertNotFalse($label);

        /*
         * A score is a fact off the match record. The Fields panel offers it as hideable but not
         * typeable, and the server refuses it regardless of what the browser posts — the UI being
         * read-only is a courtesy, not the guarantee.
         */
        $this->assertTrue(TournamentTemplate::isLockedPlaceholder($layout[$score]['placeholder']));
        $this->assertFalse(TournamentTemplate::isLockedPlaceholder('venue'));
        $this->assertFalse(TournamentTemplate::isLockedPlaceholder(null));

        $user = $this->makeAuctionOperator($org);
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']));

        $response = $this->actingAs($user)->post(
            route('admin.tournaments.templates.generate-preview', $tournament),
            [
                'template_id' => $template->id,
                'venue' => 'Original Ground',
                'team_a_score_wickets' => '10/1',
                'element_overrides' => [
                    $venue => ['value' => 'SPORTS ACADEMY GROUND'],
                    $label => ['value' => 'OVERS BOWLED'],
                    $score => ['value' => '999/0'],
                ],
            ]
        );

        $response->assertOk();
        $this->assertNotEmpty($response->json('image'));

        // The stored template must be untouched — this is one poster, not a template edit.
        $this->assertSame(
            $layout,
            $template->fresh()->layout_json,
            'A generate-time override leaked into the saved template.'
        );
    }

    #[Test]
    public function deleting_a_template_leaves_a_background_another_one_still_uses(): void
    {
        Storage::fake('public');

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');

        Storage::disk('public')->put('tournament_templates/shared.jpg', 'image-bytes');

        $keep = $tournament->templates()->create([
            'type' => TournamentTemplate::TYPE_MATCH_SUMMARY,
            'name' => 'Keep', 'background_image' => 'tournament_templates/shared.jpg', 'is_active' => true,
        ]);
        $drop = $tournament->templates()->create([
            'type' => TournamentTemplate::TYPE_PLAYING_XI,
            'name' => 'Drop', 'background_image' => 'tournament_templates/shared.jpg', 'is_active' => true,
        ]);

        $user = $this->makeAuctionOperator($org);
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']));

        $this->actingAs($user)->delete(route('admin.tournaments.templates.destroy', [$tournament, $drop]));

        /*
         * Two templates can point at one image — deliberately, when the same photo backs a whole
         * set of designs. Deleting one used to take the picture with it, leaving the other
         * rendering on a blank canvas and saying nothing about why.
         */
        $this->assertTrue(
            Storage::disk('public')->exists('tournament_templates/shared.jpg'),
            'Deleting one template destroyed a background another template still references.'
        );
        $this->assertNotNull($keep->fresh());

        // The last template using it still cleans up, so nothing is orphaned forever.
        $this->actingAs($user)->delete(route('admin.tournaments.templates.destroy', [$tournament, $keep]));
        $this->assertFalse(Storage::disk('public')->exists('tournament_templates/shared.jpg'));
    }

    #[Test]
    public function a_cricheroes_match_puts_its_heroes_on_the_summary_poster(): void
    {
        Storage::fake('public');

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');
        $teamA = $this->makeTeam($org, 'Kashmir Aces', $tournament);
        $teamB = $this->makeTeam($org, 'Foilers XI', $tournament);

        $match = Matches::create([
            'tournament_id' => $tournament->id,
            'name' => 'Kashmir Aces vs Foilers XI',
            'slug' => 'kashmir-aces-vs-foilers-xi-' . uniqid(),
            'team_a_id' => $teamA->id,
            'team_b_id' => $teamB->id,
            'status' => 'completed',
        ]);

        /*
         * The shape CricHeroes-imported matches actually carry: innings wrapped under an
         * `innings` key, alongside `cricheroes_heroes`. Older rows are a bare innings array,
         * which the reader already normalises.
         */
        MatchResult::create([
            'match_id' => $match->id,
            'team_a_score' => 178, 'team_a_wickets' => 8, 'team_a_overs' => 20.0,
            'team_b_score' => 179, 'team_b_wickets' => 5, 'team_b_overs' => 18.4,
            'result_summary' => 'Foilers XI won by 5 wickets',
            'scorecard_data' => [
                'innings' => [
                    ['batting' => [['name' => 'Imtiaz Khan', 'runs' => 72, 'balls' => 35, 'fours' => 11, 'sixes' => 3]], 'bowling' => []],
                    ['batting' => [['name' => 'Someone Else', 'runs' => 10, 'balls' => 9, 'fours' => 1, 'sixes' => 0]], 'bowling' => []],
                ],
                'cricheroes_heroes' => [
                    'best_batter' => ['name' => 'Imtiaz Khan', 'runs' => 72, 'balls' => 35, 'fours' => 11, 'sixes' => 3],
                    'best_bowler' => ['name' => 'Yusuf Quilon', 'runs' => 31, 'overs' => '4', 'wickets' => 3],
                    'player_of_the_match' => null,
                ],
            ],
        ]);

        $template = (new TemplatePresetService())->apply($tournament, 'stumps_score_update');

        // The generate-preview route is gated to Superadmin|Admin.
        $user = $this->makeAuctionOperator($org);
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']));

        $response = $this->actingAs($user)->post(
            route('admin.tournaments.templates.generate-preview', $tournament),
            ['template_id' => $template->id, 'match_id' => $match->id]
        );

        $response->assertOk();
        $withHeroes = $response->json('image');
        $this->assertNotEmpty($withHeroes, 'The summary poster should render.');

        /*
         * And the heroes have to actually reach the picture. The performer lines are the point
         * of the fallback — before it they came out blank on every imported match while the
         * data sat one key away in the same column — so strip the heroes and the same poster
         * must come out different. Comparing the render is the only assertion available here:
         * the controller builds its data array locally and hands it to a service it news up
         * itself, so there is nothing to inspect in between.
         */
        $result = MatchResult::where('match_id', $match->id)->first();
        $scorecard = $result->scorecard_data;
        unset($scorecard['cricheroes_heroes']);
        $result->update(['scorecard_data' => $scorecard]);

        $withoutHeroes = $this->actingAs($user)->post(
            route('admin.tournaments.templates.generate-preview', $tournament),
            ['template_id' => $template->id, 'match_id' => $match->id]
        )->json('image');

        $this->assertNotEmpty($withoutHeroes);
        $this->assertNotSame(
            $withHeroes,
            $withoutHeroes,
            'The poster is identical with and without cricheroes_heroes, so the performer lines are not being filled from them.'
        );
    }

}
