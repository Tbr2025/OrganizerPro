<?php

namespace App\Services\Poster;

use App\Models\Tournament;
use App\Models\TournamentTemplate;

/**
 * Ready-made poster designs, as layouts rather than as pictures.
 *
 * A tournament used to start every template from an empty canvas, which means the only way to
 * get a good poster was to draw one — so most tournaments have a square with a name on it. A
 * preset is the same thing the drag editor saves (`layout_json`), authored here instead of by
 * hand, so applying one produces an ordinary template the organizer can then nudge, recolour or
 * throw elements out of. Nothing about an applied preset is special or locked.
 *
 * Coordinates follow the editor's own convention, which is worth stating because getting it
 * wrong silently moves every element: `x` and `y` are PERCENTAGES of the canvas and address the
 * element's CENTRE on both axes, while `width`, `height` and `fontSize` are pixels at the
 * preset's declared canvas size. TemplateRenderService reads it that way for text, shapes and
 * images alike.
 */
class TemplatePresetService
{
    /**
     * Every preset, keyed by the slug that identifies it in a request.
     *
     * @return array<string, array{key:string,type:string,name:string,description:string,canvas_width:int,canvas_height:int,accent:string,layout:list<array<string,mixed>>}>
     */
    public function all(): array
    {
        $presets = [
            $this->stumpsScoreUpdate(),
            $this->playingXi(),
        ];

        return collect($presets)->keyBy('key')->all();
    }

    /** The presets that belong to one template type. */
    public function forType(string $type): array
    {
        return collect($this->all())->where('type', $type)->values()->all();
    }

    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Turn a preset into a real template row for this tournament.
     *
     * Deliberately never `is_default`: applying a design must not silently change which template
     * the automatic emails and summary jobs already send. The organizer promotes it themselves.
     */
    public function apply(Tournament $tournament, string $key): TournamentTemplate
    {
        $preset = $this->find($key);

        if (! $preset) {
            throw new \InvalidArgumentException("Unknown template preset [{$key}].");
        }

        return $tournament->templates()->create([
            'type' => $preset['type'],
            'name' => $this->uniqueName($tournament, $preset['name']),
            'layout_json' => $preset['layout'],
            'canvas_width' => $preset['canvas_width'],
            'canvas_height' => $preset['canvas_height'],
            'placeholders' => TournamentTemplate::getDefaultPlaceholders($preset['type']),
            'is_default' => false,
            'is_active' => true,
        ]);
    }

    /**
     * "Playing XI", then "Playing XI (2)" — applying the same preset twice is a normal thing to
     * do (one to keep, one to experiment on) and two rows with one name is confusing in a picker.
     */
    protected function uniqueName(Tournament $tournament, string $base): string
    {
        $taken = $tournament->templates()->pluck('name')->all();

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        $n = 2;
        while (in_array("{$base} ({$n})", $taken, true)) {
            $n++;
        }

        return "{$base} ({$n})";
    }

    // ---------------------------------------------------------------------
    // The designs.
    // ---------------------------------------------------------------------

    /**
     * The innings/stumps score card: a photo, then a pale panel carrying both totals.
     *
     * Deliberately a match_summary and not a new type — every value on it already exists as a
     * match_summary placeholder, so this is a layout and nothing else. The panel is a real shape
     * rather than a baked-in image because the photo behind it is whatever the organizer uploads,
     * and dark text on an unknown photo is unreadable exactly when it matters.
     *
     * One honest difference from the reference: it shows ONE performer per side rather than two,
     * because `best_batsman_*` and `best_bowler_*` are the only named performers this schema
     * exposes. A second pair would have to invent data. Swap either line for a scorecardTable
     * element in the editor if you want the real top order.
     */
    protected function stumpsScoreUpdate(): array
    {
        $ink = '#14306b';
        $accent = '#ff4d00';
        $muted = '#7b8794';

        return [
            'key' => 'stumps_score_update',
            'type' => TournamentTemplate::TYPE_MATCH_SUMMARY,
            'name' => 'Stumps Score Update',
            'description' => 'Photo above, pale score panel below: both totals with overs, one performer a side, and the result line. Upload a match photo as the background.',
            'canvas_width' => 1080,
            'canvas_height' => 1080,
            'accent' => $accent,
            'layout' => [
                // The panel that makes the photo safe to put text on.
                $this->shape([
                    'layerName' => 'Score panel',
                    'x' => 50, 'y' => 80,
                    'width' => 1080, 'height' => 440,
                    'fill' => 'rgba(255,255,255,0.94)',
                    'zIndex' => 0,
                ]),

                // "DAY 3 - STUMPS" — whatever stage the organizer set on the match.
                $this->text([
                    'placeholder' => 'match_stage',
                    'layerName' => 'Stage',
                    'text' => 'DAY 3 - STUMPS',
                    'x' => 50, 'y' => 62.5,
                    'width' => 760, 'fontSize' => 30,
                    'color' => $accent, 'fontWeight' => '700',
                    'textAlign' => 'center', 'textTransform' => 'uppercase',
                    'zIndex' => 1,
                ]),

                // ---- side that batted first ----
                $this->image([
                    'placeholder' => 'team_a_logo',
                    'layerName' => 'Team A logo',
                    'x' => 11, 'y' => 69.5, 'width' => 82, 'height' => 82,
                    'zIndex' => 2,
                ]),
                $this->text([
                    'placeholder' => 'team_a_score_wickets',
                    'layerName' => 'Team A score',
                    'text' => '503/9',
                    'x' => 31.5, 'y' => 69,
                    'width' => 300, 'fontSize' => 78,
                    'color' => $ink, 'fontWeight' => '800',
                    'textAlign' => 'left',
                    'zIndex' => 3,
                ]),
                $this->text([
                    'placeholder' => 'team_a_overs',
                    'layerName' => 'Team A overs',
                    'text' => '138.0',
                    'x' => 20, 'y' => 75,
                    'width' => 100, 'fontSize' => 20,
                    'color' => $muted, 'fontWeight' => '600',
                    'textAlign' => 'left',
                    'zIndex' => 4,
                ]),
                $this->text([
                    'layerName' => 'Team A overs label',
                    // Disappears when there is no overs figure to label — an unplayed match.
                    'dependsOn' => 'team_a_overs',
                    'text' => 'OVERS',
                    'x' => 28.5, 'y' => 75,
                    'width' => 100, 'fontSize' => 20,
                    'color' => $muted, 'fontWeight' => '600',
                    'textAlign' => 'left',
                    'zIndex' => 5,
                ]),
                $this->text([
                    'placeholder' => 'best_batsman_name',
                    'layerName' => 'Top batter',
                    'text' => 'S. DINUSHA',
                    'x' => 25, 'y' => 80,
                    'width' => 300, 'fontSize' => 20,
                    'color' => $ink, 'fontWeight' => '700',
                    'textAlign' => 'left', 'textTransform' => 'uppercase',
                    'zIndex' => 6,
                ]),
                $this->text([
                    'placeholder' => 'best_batsman_batting_figures',
                    'layerName' => 'Top batter figures',
                    'text' => '85*(137)',
                    'x' => 25, 'y' => 83.6,
                    'width' => 300, 'fontSize' => 20,
                    'color' => $accent, 'fontWeight' => '700',
                    'textAlign' => 'left',
                    'zIndex' => 7,
                ]),

                // ---- side that batted second ----
                $this->image([
                    'placeholder' => 'team_b_logo',
                    'layerName' => 'Team B logo',
                    'x' => 61, 'y' => 69.5, 'width' => 82, 'height' => 82,
                    'zIndex' => 8,
                ]),
                $this->text([
                    'placeholder' => 'team_b_score_wickets',
                    'layerName' => 'Team B score',
                    'text' => '265/8',
                    'x' => 81.5, 'y' => 69,
                    'width' => 300, 'fontSize' => 78,
                    'color' => $ink, 'fontWeight' => '800',
                    'textAlign' => 'left',
                    'zIndex' => 9,
                ]),
                $this->text([
                    'placeholder' => 'team_b_overs',
                    'layerName' => 'Team B overs',
                    'text' => '83.4',
                    'x' => 70, 'y' => 75,
                    'width' => 100, 'fontSize' => 20,
                    'color' => $muted, 'fontWeight' => '600',
                    'textAlign' => 'left',
                    'zIndex' => 10,
                ]),
                $this->text([
                    'layerName' => 'Team B overs label',
                    // Disappears when there is no overs figure to label — an unplayed match.
                    'dependsOn' => 'team_b_overs',
                    'text' => 'OVERS',
                    'x' => 78.5, 'y' => 75,
                    'width' => 100, 'fontSize' => 20,
                    'color' => $muted, 'fontWeight' => '600',
                    'textAlign' => 'left',
                    'zIndex' => 11,
                ]),
                $this->text([
                    'placeholder' => 'best_bowler_name',
                    'layerName' => 'Top bowler',
                    'text' => 'P. KRISHNA',
                    'x' => 75, 'y' => 80,
                    'width' => 300, 'fontSize' => 20,
                    'color' => $ink, 'fontWeight' => '700',
                    'textAlign' => 'left', 'textTransform' => 'uppercase',
                    'zIndex' => 12,
                ]),
                $this->text([
                    'placeholder' => 'best_bowler_bowling_figures',
                    'layerName' => 'Top bowler figures',
                    'text' => '3/47',
                    'x' => 75, 'y' => 83.6,
                    'width' => 300, 'fontSize' => 20,
                    'color' => $accent, 'fontWeight' => '700',
                    'textAlign' => 'left',
                    'zIndex' => 13,
                ]),

                // The line that says what the scores mean.
                $this->text([
                    'placeholder' => 'result_summary',
                    'layerName' => 'Result line',
                    'text' => 'SRI LANKA TRAIL BY 238 RUNS',
                    'x' => 50, 'y' => 88.5,
                    'width' => 940, 'fontSize' => 30,
                    'color' => $ink, 'fontWeight' => '700',
                    'textAlign' => 'center', 'textTransform' => 'uppercase',
                    'zIndex' => 14,
                ]),

                // Footer, the two corners a broadcast card always fills.
                $this->text([
                    'placeholder' => 'tournament_name',
                    'layerName' => 'Footer left',
                    'text' => 'TOURNAMENT',
                    'x' => 24, 'y' => 94,
                    'width' => 500, 'fontSize' => 18,
                    'color' => $ink, 'fontWeight' => '700',
                    'textAlign' => 'left', 'textTransform' => 'uppercase',
                    'zIndex' => 15,
                ]),
                $this->text([
                    'placeholder' => 'venue',
                    'layerName' => 'Footer right',
                    'text' => 'VENUE',
                    'x' => 76, 'y' => 94,
                    'width' => 500, 'fontSize' => 18,
                    'color' => $accent, 'fontWeight' => '700',
                    'textAlign' => 'right', 'textTransform' => 'uppercase',
                    'zIndex' => 16,
                ]),
            ],
        ];
    }

    /**
     * The line-up poster: wordmark, both crests, eleven names, one cut-out player.
     *
     * The eleven are a `lineupArea` element, not eleven text elements, so the list stays one
     * thing to move and re-colour and does not need re-drawing when a side names ten or twelve.
     */
    protected function playingXi(): array
    {
        $ink = '#14306b';
        $accent = '#ff4d00';

        return [
            'key' => 'playing_xi_classic',
            'type' => TournamentTemplate::TYPE_PLAYING_XI,
            'name' => 'Playing XI',
            'description' => 'Big "Playing XI" wordmark, both crests, the eleven names down the left with C / VC / WK / DEBUT chips, and a cut-out player on the right.',
            'canvas_width' => 1080,
            'canvas_height' => 1080,
            'accent' => $accent,
            'layout' => [
                /*
                 * A pale wash, not an opaque fill: zIndex -1 puts it under every element but
                 * still OVER the uploaded background image, so at 0.88 alpha a stadium photo
                 * reads through it as the faded backdrop the design wants, and a template with
                 * no photo yet is still light enough to read dark type on.
                 */
                $this->shape([
                    'layerName' => 'Page wash',
                    'x' => 50, 'y' => 50,
                    'width' => 1080, 'height' => 1080,
                    'fill' => 'rgba(244,246,250,0.88)',
                    'zIndex' => -1,
                ]),

                // The cut-out sits under the type so a wide photo cannot cover the names.
                $this->image([
                    'placeholder' => 'featured_player_image',
                    'layerName' => 'Featured player',
                    'x' => 77, 'y' => 61, 'width' => 480, 'height' => 720,
                    'zIndex' => 0,
                ]),

                $this->text([
                    'layerName' => 'Wordmark "Playing"',
                    'text' => 'Playing',
                    'x' => 26, 'y' => 16,
                    'width' => 420, 'fontSize' => 84,
                    'color' => $ink, 'fontWeight' => '800',
                    'textAlign' => 'left',
                    'zIndex' => 1,
                ]),
                $this->text([
                    'layerName' => 'Wordmark "XI"',
                    'text' => 'XI',
                    'x' => 63, 'y' => 13.5,
                    'width' => 320, 'fontSize' => 190,
                    'color' => $accent, 'fontWeight' => '800',
                    'textAlign' => 'left',
                    'zIndex' => 2,
                ]),

                // Crests, with the "v" that makes them a fixture rather than two logos.
                $this->image([
                    'placeholder' => 'lineup_team_logo',
                    'layerName' => 'This side',
                    'x' => 10, 'y' => 28, 'width' => 88, 'height' => 88,
                    'zIndex' => 3,
                ]),
                $this->text([
                    'layerName' => 'v',
                    'text' => 'v',
                    'x' => 17.5, 'y' => 28,
                    'width' => 60, 'fontSize' => 34,
                    'color' => $ink, 'fontWeight' => '600',
                    'textAlign' => 'center',
                    'zIndex' => 4,
                ]),
                $this->image([
                    'placeholder' => 'opponent_team_logo',
                    'layerName' => 'Opponent',
                    'x' => 25, 'y' => 28, 'width' => 88, 'height' => 88,
                    'zIndex' => 5,
                ]),

                // The eleven.
                array_merge($this->image([]), [
                    'type' => 'lineupArea',
                    'placeholder' => 'lineup_area',
                    'layerName' => 'The XI',
                    'x' => 30, 'y' => 64,
                    'width' => 520, 'height' => 560,
                    'zIndex' => 6,
                    'lineupConfig' => [
                        'maxRows' => 11,
                        'fontSize' => 34,
                        'rowHeight' => 50,
                        'textColor' => $ink,
                        'badgeBg' => $accent,
                        'badgeTextColor' => '#ffffff',
                        'numberColor' => $accent,
                        'showNumbers' => false,
                        'uppercase' => false,
                        'textAlign' => 'left',
                        'columns' => 1,
                        'fontFamily' => 'Montserrat',
                        'fontWeight' => '700',
                    ],
                ]),

                /*
                 * The cut-out's name, captioned across the bottom of the photo.
                 *
                 * Without an element for it, `featured_player_name` was a placeholder the type
                 * offered and no design ever printed — so naming an uploaded photo did nothing.
                 * It is a placeholder element, so skipBlanks drops it when nobody is featured.
                 */
                $this->text([
                    'placeholder' => 'featured_player_name',
                    'layerName' => 'Featured player name',
                    'text' => 'PLAYER NAME',
                    'x' => 77, 'y' => 88,
                    'width' => 470, 'fontSize' => 38,
                    'color' => $ink, 'fontWeight' => '800',
                    'textAlign' => 'center', 'textTransform' => 'uppercase',
                    'zIndex' => 7,
                ]),

                // Footer.
                $this->text([
                    'placeholder' => 'tournament_name',
                    'layerName' => 'Footer left',
                    'text' => 'TOURNAMENT',
                    'x' => 24, 'y' => 94,
                    'width' => 500, 'fontSize' => 18,
                    'color' => $ink, 'fontWeight' => '700',
                    'textAlign' => 'left', 'textTransform' => 'uppercase',
                    'zIndex' => 8,
                ]),
                $this->text([
                    'placeholder' => 'match_stage',
                    'layerName' => 'Footer right',
                    'text' => 'MATCH',
                    'x' => 76, 'y' => 94,
                    'width' => 500, 'fontSize' => 18,
                    'color' => $accent, 'fontWeight' => '700',
                    'textAlign' => 'right', 'textTransform' => 'uppercase',
                    'zIndex' => 9,
                ]),
            ],
        ];
    }

    // ---------------------------------------------------------------------
    // Element builders. These exist so a layout below reads as a layout and
    // not as forty repetitions of the same fifteen default keys.
    // ---------------------------------------------------------------------

    protected function text(array $attrs): array
    {
        return array_merge([
            'type' => 'text',
            'placeholder' => null,
            'x' => 50,
            'y' => 50,
            'rotation' => 0,
            'opacity' => 100,
            'zIndex' => 0,
            'layerName' => null,
            'hidden' => false,
            'locked' => false,
            'text' => '',
            'fontSize' => 32,
            'autoSize' => false,
            'width' => 400,
            'fontFamily' => 'Montserrat',
            'fontWeight' => '700',
            'fontStyle' => 'normal',
            'underline' => false,
            'linethrough' => false,
            'skewX' => 0,
            'color' => '#14306b',
            'textAlign' => 'left',
            'textTransform' => 'none',
            'shadow' => null,
            'stroke' => null,
            'strokeWidth' => 0,
            'dependsOn' => null,
        ], $attrs, ['baseFontSize' => $attrs['fontSize'] ?? 32]);
    }

    protected function image(array $attrs): array
    {
        return array_merge([
            'type' => 'image',
            'placeholder' => null,
            'x' => 50,
            'y' => 50,
            'rotation' => 0,
            'opacity' => 100,
            'zIndex' => 0,
            'layerName' => null,
            'hidden' => false,
            'locked' => false,
            'width' => 100,
            'height' => 100,
            'borderRadius' => 0,
        ], $attrs);
    }

    protected function shape(array $attrs): array
    {
        return array_merge([
            'type' => 'shape',
            'placeholder' => null,
            'x' => 50,
            'y' => 50,
            'rotation' => 0,
            'opacity' => 100,
            'zIndex' => 0,
            'layerName' => null,
            'hidden' => false,
            'locked' => false,
            'shapeType' => 'rect',
            'iconName' => null,
            'fill' => 'rgba(255,255,255,0.94)',
            'fillOpacity' => 1,
            'stroke' => null,
            'strokeWidth' => 0,
            'width' => 200,
            'height' => 100,
            'scaleX' => 1,
            'scaleY' => 1,
            'rx' => 0,
            'ry' => 0,
            'shadow' => null,
            'borderRadii' => null,
        ], $attrs);
    }
}
