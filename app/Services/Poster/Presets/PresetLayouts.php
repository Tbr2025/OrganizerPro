<?php

declare(strict_types=1);

namespace App\Services\Poster\Presets;

use App\Models\TournamentTemplate as T;

/**
 * Two ready-made layouts for every poster type, drawn in a theme's colours.
 *
 * Each layout uses only the placeholders its type really fills
 * (TournamentTemplate::getDefaultPlaceholders) — a layout that names one the
 * renderer never supplies would print the sample text on a real poster.
 * Elements bound to data disappear when the data is blank (skipBlanks), so a
 * tournament with no sponsors simply has no sponsor row.
 *
 * Geometry is the editor's: x / y are PERCENTAGES and address the centre;
 * width, height and fontSize are pixels at the declared canvas size.
 */
class PresetLayouts
{
    use BuildsPresetElements;

    private const PORTRAIT = [1080, 1350];
    private const SQUARE = [1080, 1080];
    private const LANDSCAPE = [1600, 910];

    /** @return list<array{key:string,type:string,name:string,description:string,size:array{0:int,1:int},build:callable}> */
    public function all(): array
    {
        return [
            $this->def('welcome_spotlight', T::TYPE_WELCOME_CARD, 'Spotlight', 'Big player cut-out, name banner, team and role.', self::PORTRAIT, fn ($t) => $this->welcomeSpotlight($t, 'WELCOME TO THE SQUAD')),
            $this->def('welcome_split', T::TYPE_WELCOME_CARD, 'New Signing', 'Name and details on the left, the player on the right.', self::PORTRAIT, fn ($t) => $this->welcomeSplit($t, 'NEW SIGNING')),
            $this->def('retained_spotlight', T::TYPE_RETAINED_WELCOME_CARD, 'Retained — Spotlight', 'Big player cut-out with a RETAINED banner.', self::PORTRAIT, fn ($t) => $this->welcomeSpotlight($t, 'RETAINED')),
            $this->def('retained_split', T::TYPE_RETAINED_WELCOME_CARD, 'Retained — Split', 'Retained player, details on the left.', self::PORTRAIT, fn ($t) => $this->welcomeSplit($t, 'RETAINED FOR THE SEASON')),
            $this->def('match_versus', T::TYPE_MATCH_POSTER, 'Versus', 'Both crests head to head, date, time and venue.', self::PORTRAIT, fn ($t) => $this->matchVersus($t)),
            $this->def('match_captains', T::TYPE_MATCH_POSTER, 'Captains', 'Both captains face off over the team codes.', self::PORTRAIT, fn ($t) => $this->matchCaptains($t)),
            $this->def('summary_result', T::TYPE_MATCH_SUMMARY, 'Result Card', 'Both scores, the result line and the player of the match.', self::SQUARE, fn ($t) => $this->summaryResult($t)),
            $this->def('summary_performers', T::TYPE_MATCH_SUMMARY, 'Top Performers', 'Result, both scores, best batter and best bowler.', self::PORTRAIT, fn ($t) => $this->summaryPerformers($t)),
            $this->def('award_hero', T::TYPE_AWARD_POSTER, 'Award Hero', 'Award title over the player, figures and match underneath.', self::PORTRAIT, fn ($t) => $this->awardHero($t)),
            $this->def('award_split', T::TYPE_AWARD_POSTER, 'Award Split', 'Award and figures on the left, the player on the right.', self::PORTRAIT, fn ($t) => $this->awardSplit($t)),
            $this->def('flyer_bold', T::TYPE_FLYER, 'Registrations Open', 'Big name, dates, location and a QR code to register.', self::PORTRAIT, fn ($t) => $this->flyerBold($t)),
            $this->def('flyer_classic', T::TYPE_FLYER, 'Classic Flyer', 'Centred logo and name, dates panel, QR code.', self::PORTRAIT, fn ($t) => $this->flyerClassic($t)),
            $this->def('champions_trophy', T::TYPE_CHAMPIONS_POSTER, 'Champions', 'CHAMPIONS over a big crest and the team name.', self::PORTRAIT, fn ($t) => $this->championsTrophy($t)),
            $this->def('champions_banner', T::TYPE_CHAMPIONS_POSTER, 'Champions Banner', 'Title and year, crest, team name banner.', self::PORTRAIT, fn ($t) => $this->championsBanner($t)),
            $this->def('table_standard', T::TYPE_POINT_TABLE, 'Points Table', 'Group name and the full table.', self::PORTRAIT, fn ($t) => $this->pointTable($t, false)),
            $this->def('table_compact', T::TYPE_POINT_TABLE, 'Points Table — Compact', 'Tighter rows for bigger groups.', self::PORTRAIT, fn ($t) => $this->pointTable($t, true)),
            $this->def('fixtures_rows', T::TYPE_FIXTURES_POSTER, 'Fixtures List', 'Upcoming matches as rows.', self::PORTRAIT, fn ($t) => $this->fixtures($t, 'row')),
            $this->def('fixtures_cards', T::TYPE_FIXTURES_POSTER, 'Fixtures Cards', 'Upcoming matches as cards.', self::PORTRAIT, fn ($t) => $this->fixtures($t, 'card')),
            $this->def('auction_card', T::TYPE_AUCTION_POSTER, 'Auction Card', 'Player, role, career numbers and price.', self::LANDSCAPE, fn ($t) => $this->auctionLandscape($t, false)),
            $this->def('auction_sold', T::TYPE_AUCTION_POSTER, 'Sold!', 'Player, the buying team and the price.', self::LANDSCAPE, fn ($t) => $this->auctionLandscape($t, true)),
            $this->def('auction_portrait_card', T::TYPE_AUCTION_POSTER_PORTRAIT, 'Auction Card (portrait)', 'Player, role, numbers and base price.', self::PORTRAIT, fn ($t) => $this->auctionPortrait($t, false)),
            $this->def('auction_portrait_sold', T::TYPE_AUCTION_POSTER_PORTRAIT, 'Sold! (portrait)', 'Player, buying team and price.', self::PORTRAIT, fn ($t) => $this->auctionPortrait($t, true)),
        ];
    }

    private function def(string $key, string $type, string $name, string $description, array $size, callable $build): array
    {
        return compact('key', 'type', 'name', 'description', 'size', 'build');
    }

    // ---- shared pieces ----------------------------------------------------------

    private int $z = 0;

    /** Next stacking order: elements are drawn in the order they are listed. */
    private function z(): int
    {
        return ++$this->z;
    }

    private function t(array $a): array
    {
        return $this->text($a + ['zIndex' => $this->z()]);
    }

    private function i(array $a): array
    {
        return $this->image($a + ['zIndex' => $this->z()]);
    }

    private function s(array $a): array
    {
        return $this->shape($a + ['zIndex' => $this->z()]);
    }

    /** Tournament crest and name across the top. */
    private function header(array $t, float $y = 6.5): array
    {
        return [
            $this->i(['placeholder' => 'tournament_logo', 'layerName' => 'Tournament logo', 'x' => 50, 'y' => $y, 'width' => 120, 'height' => 120]),
            $this->t(['placeholder' => 'tournament_name', 'layerName' => 'Tournament name', 'text' => 'PREMIER LEAGUE', 'x' => 50, 'y' => $y + 6.8, 'width' => 900, 'fontSize' => 26, 'color' => $t['muted'], 'fontWeight' => '700', 'textAlign' => 'center', 'textTransform' => 'uppercase', 'autoSize' => true]),
        ];
    }

    /** Up to three sponsor logos along the foot; blank ones vanish. */
    private function footer(array $t, float $y = 93, int $w = 150, int $h = 76): array
    {
        return [
            $this->i(['placeholder' => 'sponsor_logo', 'layerName' => 'Sponsor 1', 'x' => 30, 'y' => $y, 'width' => $w, 'height' => $h]),
            $this->i(['placeholder' => 'sponsor_logo_2', 'layerName' => 'Sponsor 2', 'x' => 50, 'y' => $y, 'width' => $w, 'height' => $h]),
            $this->i(['placeholder' => 'sponsor_logo_3', 'layerName' => 'Sponsor 3', 'x' => 70, 'y' => $y, 'width' => $w, 'height' => $h]),
        ];
    }

    private function display(array $a, array $t): array
    {
        return $this->t($a + ['fontFamily' => 'Bebas Neue', 'fontWeight' => '400', 'color' => $t['ink'], 'textAlign' => 'center', 'textTransform' => 'uppercase']);
    }

    private function body(array $a, array $t): array
    {
        return $this->t($a + ['fontFamily' => 'Montserrat', 'fontWeight' => '700', 'color' => $t['muted'], 'textAlign' => 'center']);
    }

    /** A label, then its value, stacked — for stat boxes. */
    private function stat(float $x, float $y, string $label, string $placeholder, string $sample, array $t, int $size = 64): array
    {
        return [
            $this->display(['placeholder' => $placeholder, 'layerName' => $label, 'text' => $sample, 'x' => $x, 'y' => $y, 'width' => 240, 'fontSize' => $size, 'color' => $t['ink']], $t),
            $this->body(['layerName' => $label . ' label', 'dependsOn' => $placeholder, 'text' => $label, 'x' => $x, 'y' => $y + ($size / 13.5) * 0.85, 'width' => 240, 'fontSize' => 18, 'textTransform' => 'uppercase'], $t),
        ];
    }

    // ---- welcome / retained -----------------------------------------------------

    private function welcomeSpotlight(array $t, string $banner): array
    {
        $this->z = 0;

        return [
            ...$this->header($t),
            $this->display(['layerName' => 'Banner', 'text' => $banner, 'x' => 50, 'y' => 19.5, 'width' => 1000, 'fontSize' => 66, 'color' => $t['accent']], $t),
            $this->t(['placeholder' => 'jersey_number', 'layerName' => 'Jersey number', 'text' => '18', 'x' => 84, 'y' => 31, 'width' => 300, 'fontSize' => 170, 'fontFamily' => 'Anton', 'fontWeight' => '400', 'color' => $t['accent'], 'opacity' => 35, 'textAlign' => 'center']),
            $this->i(['placeholder' => 'team_logo', 'layerName' => 'Team logo', 'x' => 15, 'y' => 31, 'width' => 150, 'height' => 150]),
            $this->i(['placeholder' => 'player_image', 'layerName' => 'Player photo', 'x' => 50, 'y' => 50, 'width' => 760, 'height' => 760]),
            $this->s(['layerName' => 'Name panel', 'x' => 50, 'y' => 80.5, 'width' => 1080, 'height' => 250, 'fill' => $t['panel']]),
            $this->display(['placeholder' => 'player_name', 'layerName' => 'Player name', 'text' => 'ARJUN MEHTA', 'x' => 50, 'y' => 75.8, 'width' => 1000, 'fontSize' => 100, 'autoSize' => true], $t),
            $this->body(['placeholder' => 'team_name', 'layerName' => 'Team', 'text' => 'ROYAL STRIKERS', 'x' => 50, 'y' => 81.6, 'width' => 900, 'fontSize' => 34, 'color' => $t['accent'], 'fontWeight' => '800', 'textTransform' => 'uppercase'], $t),
            $this->body(['placeholder' => 'player_type', 'layerName' => 'Role', 'text' => 'ALL-ROUNDER', 'x' => 50, 'y' => 85.4, 'width' => 900, 'fontSize' => 26, 'textTransform' => 'uppercase'], $t),
            ...$this->footer($t),
        ];
    }

    private function welcomeSplit(array $t, string $banner): array
    {
        $this->z = 0;
        $left = 28; // centre of a 520-px column starting 40 px in

        return [
            $this->s(['layerName' => 'Accent stripe', 'x' => 1.5, 'y' => 50, 'width' => 32, 'height' => 1350, 'fill' => $t['accent']]),
            $this->i(['placeholder' => 'tournament_logo', 'layerName' => 'Tournament logo', 'x' => 12, 'y' => 7.5, 'width' => 110, 'height' => 110]),
            $this->body(['placeholder' => 'tournament_name', 'layerName' => 'Tournament name', 'text' => 'PREMIER LEAGUE', 'x' => 52, 'y' => 7.5, 'width' => 740, 'fontSize' => 26, 'textAlign' => 'left', 'textTransform' => 'uppercase', 'autoSize' => true], $t),
            $this->i(['placeholder' => 'player_image', 'layerName' => 'Player photo', 'x' => 66, 'y' => 54, 'width' => 760, 'height' => 900]),
            $this->display(['layerName' => 'Banner', 'text' => $banner, 'x' => $left, 'y' => 22, 'width' => 520, 'fontSize' => 54, 'color' => $t['accent'], 'textAlign' => 'left'], $t),
            $this->display(['placeholder' => 'player_name', 'layerName' => 'Player name', 'text' => 'ARJUN MEHTA', 'x' => $left, 'y' => 33, 'width' => 520, 'fontSize' => 112, 'textAlign' => 'left'], $t),
            $this->i(['placeholder' => 'team_logo', 'layerName' => 'Team logo', 'x' => 14, 'y' => 55, 'width' => 150, 'height' => 150]),
            $this->body(['placeholder' => 'team_name', 'layerName' => 'Team', 'text' => 'ROYAL STRIKERS', 'x' => $left, 'y' => 64, 'width' => 520, 'fontSize' => 32, 'color' => $t['ink'], 'fontWeight' => '800', 'textAlign' => 'left', 'textTransform' => 'uppercase'], $t),
            $this->body(['placeholder' => 'player_type', 'layerName' => 'Role', 'text' => 'ALL-ROUNDER', 'x' => $left, 'y' => 69, 'width' => 520, 'fontSize' => 26, 'color' => $t['accent'], 'textAlign' => 'left', 'textTransform' => 'uppercase'], $t),
            $this->body(['placeholder' => 'batting_style', 'layerName' => 'Batting', 'text' => 'RIGHT-HAND BAT', 'x' => $left, 'y' => 73, 'width' => 520, 'fontSize' => 24, 'textAlign' => 'left', 'textTransform' => 'uppercase'], $t),
            $this->body(['placeholder' => 'bowling_style', 'layerName' => 'Bowling', 'text' => 'RIGHT-ARM MEDIUM', 'x' => $left, 'y' => 76.5, 'width' => 520, 'fontSize' => 24, 'textAlign' => 'left', 'textTransform' => 'uppercase'], $t),
            ...$this->footer($t),
        ];
    }

    // ---- match poster -----------------------------------------------------------

    private function matchVersus(array $t): array
    {
        $this->z = 0;

        return [
            ...$this->header($t),
            $this->display(['placeholder' => 'match_stage', 'layerName' => 'Stage', 'text' => 'LEAGUE MATCH', 'x' => 50, 'y' => 18.5, 'width' => 900, 'fontSize' => 56, 'color' => $t['accent']], $t),
            $this->i(['placeholder' => 'team_a_logo', 'layerName' => 'Team A logo', 'x' => 25, 'y' => 37, 'width' => 300, 'height' => 300]),
            $this->t(['layerName' => 'VS', 'text' => 'VS', 'x' => 50, 'y' => 37, 'width' => 200, 'fontSize' => 130, 'fontFamily' => 'Anton', 'fontWeight' => '400', 'color' => $t['accent'], 'textAlign' => 'center']),
            $this->i(['placeholder' => 'team_b_logo', 'layerName' => 'Team B logo', 'x' => 75, 'y' => 37, 'width' => 300, 'height' => 300]),
            $this->display(['placeholder' => 'team_a_name', 'layerName' => 'Team A name', 'text' => 'ROYAL STRIKERS', 'x' => 25, 'y' => 51.5, 'width' => 440, 'fontSize' => 56, 'autoSize' => true], $t),
            $this->display(['placeholder' => 'team_b_name', 'layerName' => 'Team B name', 'text' => 'THUNDER XI', 'x' => 75, 'y' => 51.5, 'width' => 440, 'fontSize' => 56, 'autoSize' => true], $t),
            $this->s(['layerName' => 'Details panel', 'x' => 50, 'y' => 70, 'width' => 860, 'height' => 230, 'fill' => $t['panel'], 'rx' => 28, 'ry' => 28]),
            $this->display(['placeholder' => 'match_date', 'layerName' => 'Date', 'text' => 'SAT, 14 NOV 2026', 'x' => 50, 'y' => 65.8, 'width' => 800, 'fontSize' => 68, 'color' => $t['panelInk']], $t),
            $this->body(['placeholder' => 'match_time', 'layerName' => 'Time', 'text' => '07:30 PM', 'x' => 50, 'y' => 70.6, 'width' => 800, 'fontSize' => 38, 'color' => $t['accent'], 'fontWeight' => '800'], $t),
            $this->body(['placeholder' => 'venue', 'layerName' => 'Venue', 'text' => 'CITY CRICKET GROUND', 'x' => 50, 'y' => 74.6, 'width' => 800, 'fontSize' => 26, 'textTransform' => 'uppercase', 'autoSize' => true], $t),
            ...$this->footer($t),
        ];
    }

    private function matchCaptains(array $t): array
    {
        $this->z = 0;

        return [
            ...$this->header($t),
            $this->display(['placeholder' => 'match_stage', 'layerName' => 'Stage', 'text' => 'LEAGUE MATCH', 'x' => 50, 'y' => 18, 'width' => 900, 'fontSize' => 48, 'color' => $t['accent']], $t),
            $this->i(['placeholder' => 'team_a_captain_image', 'layerName' => 'Captain A', 'x' => 27, 'y' => 44, 'width' => 520, 'height' => 620]),
            $this->i(['placeholder' => 'team_b_captain_image', 'layerName' => 'Captain B', 'x' => 73, 'y' => 44, 'width' => 520, 'height' => 620]),
            $this->s(['layerName' => 'Band', 'x' => 50, 'y' => 64, 'width' => 1080, 'height' => 180, 'fill' => $t['panel']]),
            $this->display(['placeholder' => 'team_a_code', 'layerName' => 'Team A code', 'text' => 'RST', 'x' => 26, 'y' => 62.5, 'width' => 400, 'fontSize' => 110, 'autoSize' => true], $t),
            $this->t(['layerName' => 'VS', 'text' => 'VS', 'x' => 50, 'y' => 62.5, 'width' => 200, 'fontSize' => 80, 'fontFamily' => 'Anton', 'fontWeight' => '400', 'color' => $t['accent'], 'textAlign' => 'center']),
            $this->display(['placeholder' => 'team_b_code', 'layerName' => 'Team B code', 'text' => 'THX', 'x' => 74, 'y' => 62.5, 'width' => 400, 'fontSize' => 110, 'autoSize' => true], $t),
            $this->body(['placeholder' => 'team_a_captain_name', 'layerName' => 'Captain A name', 'text' => 'R. SHARMA (C)', 'x' => 26, 'y' => 68, 'width' => 420, 'fontSize' => 22, 'textTransform' => 'uppercase'], $t),
            $this->body(['placeholder' => 'team_b_captain_name', 'layerName' => 'Captain B name', 'text' => 'K. RAHUL (C)', 'x' => 74, 'y' => 68, 'width' => 420, 'fontSize' => 22, 'textTransform' => 'uppercase'], $t),
            $this->display(['placeholder' => 'match_date', 'layerName' => 'Date', 'text' => 'SAT, 14 NOV 2026', 'x' => 50, 'y' => 76, 'width' => 900, 'fontSize' => 60, 'color' => $t['accent']], $t),
            $this->body(['placeholder' => 'match_time', 'layerName' => 'Time', 'text' => '07:30 PM', 'x' => 50, 'y' => 80.4, 'width' => 900, 'fontSize' => 30, 'color' => $t['ink']], $t),
            $this->body(['placeholder' => 'venue', 'layerName' => 'Venue', 'text' => 'CITY CRICKET GROUND', 'x' => 50, 'y' => 84, 'width' => 900, 'fontSize' => 24, 'textTransform' => 'uppercase', 'autoSize' => true], $t),
            ...$this->footer($t),
        ];
    }

    // ---- match summary ------------------------------------------------------------

    private function summaryResult(array $t): array
    {
        $this->z = 0;
        $resultInk = $t['onAccent'];

        return [
            $this->i(['placeholder' => 'tournament_logo', 'layerName' => 'Tournament logo', 'x' => 50, 'y' => 8, 'width' => 110, 'height' => 110]),
            $this->display(['placeholder' => 'match_stage', 'layerName' => 'Stage', 'text' => 'MATCH RESULT', 'x' => 50, 'y' => 17, 'width' => 900, 'fontSize' => 44, 'color' => $t['accent']], $t),
            $this->i(['placeholder' => 'team_a_logo', 'layerName' => 'Team A logo', 'x' => 23, 'y' => 34, 'width' => 200, 'height' => 200]),
            $this->i(['placeholder' => 'team_b_logo', 'layerName' => 'Team B logo', 'x' => 77, 'y' => 34, 'width' => 200, 'height' => 200]),
            $this->display(['layerName' => 'VS', 'text' => 'VS', 'x' => 50, 'y' => 40, 'width' => 160, 'fontSize' => 64, 'color' => $t['muted']], $t),
            $this->display(['placeholder' => 'team_a_score_wickets', 'layerName' => 'Team A score', 'text' => '184/6', 'x' => 23, 'y' => 52, 'width' => 400, 'fontSize' => 104], $t),
            $this->display(['placeholder' => 'team_b_score_wickets', 'layerName' => 'Team B score', 'text' => '179/8', 'x' => 77, 'y' => 52, 'width' => 400, 'fontSize' => 104], $t),
            $this->body(['placeholder' => 'team_a_overs', 'layerName' => 'Team A overs', 'text' => '20.0', 'x' => 23, 'y' => 59.5, 'width' => 400, 'fontSize' => 26], $t),
            $this->body(['placeholder' => 'team_b_overs', 'layerName' => 'Team B overs', 'text' => '20.0', 'x' => 77, 'y' => 59.5, 'width' => 400, 'fontSize' => 26], $t),
            $this->body(['placeholder' => 'team_a_short_name', 'layerName' => 'Team A code', 'text' => 'RST', 'x' => 23, 'y' => 63.5, 'width' => 400, 'fontSize' => 22, 'color' => $t['ink']], $t),
            $this->body(['placeholder' => 'team_b_short_name', 'layerName' => 'Team B code', 'text' => 'THX', 'x' => 77, 'y' => 63.5, 'width' => 400, 'fontSize' => 22, 'color' => $t['ink']], $t),
            $this->s(['layerName' => 'Result band', 'dependsOn' => 'result_summary', 'x' => 50, 'y' => 74, 'width' => 980, 'height' => 112, 'fill' => $t['accent'], 'rx' => 20, 'ry' => 20]),
            $this->display(['placeholder' => 'result_summary', 'layerName' => 'Result', 'text' => 'ROYAL STRIKERS WON BY 5 RUNS', 'x' => 50, 'y' => 74, 'width' => 940, 'fontSize' => 54, 'color' => $resultInk, 'autoSize' => true], $t),
            $this->body(['layerName' => 'POTM label', 'dependsOn' => 'man_of_the_match_name', 'text' => 'PLAYER OF THE MATCH', 'x' => 50, 'y' => 84, 'width' => 900, 'fontSize' => 20, 'textTransform' => 'uppercase'], $t),
            $this->display(['placeholder' => 'man_of_the_match_name', 'layerName' => 'POTM', 'text' => 'ARJUN MEHTA', 'x' => 50, 'y' => 88.5, 'width' => 900, 'fontSize' => 50], $t),
            ...$this->footer($t, 95.5, 120, 54),
        ];
    }

    private function summaryPerformers(array $t): array
    {
        $this->z = 0;
        $row = function (float $y, string $side) use ($t) {
            return [
                $this->s(['layerName' => "Team {$side} row", 'x' => 50, 'y' => $y, 'width' => 980, 'height' => 140, 'fill' => $t['panel'], 'rx' => 20, 'ry' => 20]),
                $this->i(['placeholder' => "team_{$side}_logo", 'layerName' => "Team {$side} logo", 'x' => 13, 'y' => $y, 'width' => 104, 'height' => 104]),
                $this->body(['placeholder' => "team_{$side}_name", 'layerName' => "Team {$side} name", 'text' => $side === 'a' ? 'ROYAL STRIKERS' : 'THUNDER XI', 'x' => 40, 'y' => $y, 'width' => 440, 'fontSize' => 34, 'color' => $t['panelInk'], 'fontWeight' => '800', 'textAlign' => 'left', 'textTransform' => 'uppercase', 'autoSize' => true], $t),
                $this->display(['placeholder' => "team_{$side}_score_wickets", 'layerName' => "Team {$side} score", 'text' => $side === 'a' ? '184/6' : '179/8', 'x' => 79, 'y' => $y - 0.8, 'width' => 300, 'fontSize' => 86, 'color' => $t['panelInk'], 'textAlign' => 'right'], $t),
                $this->body(['placeholder' => "team_{$side}_overs", 'layerName' => "Team {$side} overs", 'text' => '20.0', 'x' => 79, 'y' => $y + 3.4, 'width' => 300, 'fontSize' => 22, 'textAlign' => 'right'], $t),
            ];
        };

        return [
            ...$this->header($t),
            $this->display(['placeholder' => 'result_summary', 'layerName' => 'Result', 'text' => 'ROYAL STRIKERS WON BY 5 RUNS', 'x' => 50, 'y' => 19.5, 'width' => 980, 'fontSize' => 70, 'color' => $t['accent']], $t),
            ...$row(30, 'a'),
            ...$row(42, 'b'),
            $this->body(['layerName' => 'Performers label', 'text' => 'TOP PERFORMERS', 'x' => 50, 'y' => 52, 'width' => 900, 'fontSize' => 24, 'textTransform' => 'uppercase'], $t),
            $this->i(['placeholder' => 'best_batsman_image', 'layerName' => 'Best batter photo', 'x' => 27, 'y' => 66, 'width' => 400, 'height' => 400]),
            $this->i(['placeholder' => 'best_bowler_image', 'layerName' => 'Best bowler photo', 'x' => 73, 'y' => 66, 'width' => 400, 'height' => 400]),
            $this->display(['placeholder' => 'best_batsman_name', 'layerName' => 'Best batter', 'text' => 'ARJUN MEHTA', 'x' => 27, 'y' => 80.5, 'width' => 460, 'fontSize' => 48, 'autoSize' => true], $t),
            $this->display(['placeholder' => 'best_bowler_name', 'layerName' => 'Best bowler', 'text' => 'VIKRAM RAO', 'x' => 73, 'y' => 80.5, 'width' => 460, 'fontSize' => 48, 'autoSize' => true], $t),
            $this->body(['placeholder' => 'best_batsman_batting_figures', 'layerName' => 'Best batter figures', 'text' => '78 (45)', 'x' => 27, 'y' => 84.8, 'width' => 460, 'fontSize' => 34, 'color' => $t['accent'], 'fontWeight' => '800'], $t),
            $this->body(['placeholder' => 'best_bowler_bowling_figures', 'layerName' => 'Best bowler figures', 'text' => '3/22', 'x' => 73, 'y' => 84.8, 'width' => 460, 'fontSize' => 34, 'color' => $t['accent'], 'fontWeight' => '800'], $t),
            ...$this->footer($t),
        ];
    }

    // ---- awards -------------------------------------------------------------------

    private function awardHero(array $t): array
    {
        $this->z = 0;

        return [
            ...$this->header($t),
            $this->display(['placeholder' => 'award_name', 'layerName' => 'Award', 'text' => 'PLAYER OF THE MATCH', 'x' => 50, 'y' => 19.5, 'width' => 1000, 'fontSize' => 92, 'color' => $t['accent'], 'autoSize' => true], $t),
            $this->i(['placeholder' => 'player_image', 'layerName' => 'Player photo', 'x' => 50, 'y' => 50, 'width' => 760, 'height' => 760]),
            $this->s(['layerName' => 'Name panel', 'x' => 50, 'y' => 80.5, 'width' => 1080, 'height' => 250, 'fill' => $t['panel']]),
            $this->display(['placeholder' => 'player_name', 'layerName' => 'Player name', 'text' => 'ARJUN MEHTA', 'x' => 50, 'y' => 75.8, 'width' => 1000, 'fontSize' => 96, 'autoSize' => true], $t),
            $this->body(['placeholder' => 'batting_figures', 'layerName' => 'Batting figures', 'text' => '78 (45)', 'x' => 35, 'y' => 81.5, 'width' => 400, 'fontSize' => 36, 'color' => $t['accent'], 'fontWeight' => '800', 'autoSize' => true], $t),
            $this->body(['placeholder' => 'bowling_figures', 'layerName' => 'Bowling figures', 'text' => '2/18', 'x' => 65, 'y' => 81.5, 'width' => 400, 'fontSize' => 36, 'color' => $t['accent'], 'fontWeight' => '800', 'autoSize' => true], $t),
            $this->body(['placeholder' => 'match_details', 'layerName' => 'Match', 'text' => 'RST vs THX · 14 NOV', 'x' => 50, 'y' => 85.5, 'width' => 960, 'fontSize' => 24, 'textTransform' => 'uppercase', 'autoSize' => true], $t),
            ...$this->footer($t),
        ];
    }

    private function awardSplit(array $t): array
    {
        $this->z = 0;
        $left = 28;

        return [
            $this->s(['layerName' => 'Accent stripe', 'x' => 1.5, 'y' => 50, 'width' => 32, 'height' => 1350, 'fill' => $t['accent']]),
            $this->i(['placeholder' => 'tournament_logo', 'layerName' => 'Tournament logo', 'x' => 12, 'y' => 7.5, 'width' => 110, 'height' => 110]),
            $this->body(['placeholder' => 'tournament_name', 'layerName' => 'Tournament name', 'text' => 'PREMIER LEAGUE', 'x' => 52, 'y' => 7.5, 'width' => 740, 'fontSize' => 26, 'textAlign' => 'left', 'textTransform' => 'uppercase', 'autoSize' => true], $t),
            $this->i(['placeholder' => 'player_image', 'layerName' => 'Player photo', 'x' => 66, 'y' => 54, 'width' => 760, 'height' => 900]),
            $this->display(['placeholder' => 'award_name', 'layerName' => 'Award', 'text' => 'PLAYER OF THE MATCH', 'x' => $left, 'y' => 23, 'width' => 520, 'fontSize' => 76, 'color' => $t['accent'], 'textAlign' => 'left'], $t),
            $this->display(['placeholder' => 'player_name', 'layerName' => 'Player name', 'text' => 'ARJUN MEHTA', 'x' => $left, 'y' => 38, 'width' => 520, 'fontSize' => 104, 'textAlign' => 'left'], $t),
            $this->i(['placeholder' => 'team_logo', 'layerName' => 'Team logo', 'x' => 14, 'y' => 56, 'width' => 140, 'height' => 140]),
            $this->body(['placeholder' => 'team_name', 'layerName' => 'Team', 'text' => 'ROYAL STRIKERS', 'x' => $left, 'y' => 64.5, 'width' => 520, 'fontSize' => 30, 'color' => $t['ink'], 'fontWeight' => '800', 'textAlign' => 'left', 'textTransform' => 'uppercase'], $t),
            $this->display(['placeholder' => 'batting_figures', 'layerName' => 'Batting figures', 'text' => '78 (45)', 'x' => $left, 'y' => 71, 'width' => 520, 'fontSize' => 72, 'color' => $t['accent'], 'textAlign' => 'left', 'autoSize' => true], $t),
            $this->display(['placeholder' => 'bowling_figures', 'layerName' => 'Bowling figures', 'text' => '2/18', 'x' => $left, 'y' => 77, 'width' => 520, 'fontSize' => 72, 'color' => $t['accent'], 'textAlign' => 'left', 'autoSize' => true], $t),
            $this->body(['placeholder' => 'match_details', 'layerName' => 'Match', 'text' => 'RST vs THX · 14 NOV', 'x' => $left, 'y' => 82, 'width' => 520, 'fontSize' => 22, 'textAlign' => 'left', 'textTransform' => 'uppercase'], $t),
            ...$this->footer($t),
        ];
    }

    // ---- flyers ---------------------------------------------------------------------

    private function flyerBold(array $t): array
    {
        $this->z = 0;

        return [
            $this->i(['placeholder' => 'tournament_logo', 'layerName' => 'Tournament logo', 'x' => 50, 'y' => 15, 'width' => 280, 'height' => 280]),
            $this->display(['placeholder' => 'tournament_name', 'layerName' => 'Tournament name', 'text' => 'PREMIER LEAGUE 2026', 'x' => 50, 'y' => 33, 'width' => 980, 'fontSize' => 118], $t),
            $this->s(['layerName' => 'Open pill', 'x' => 50, 'y' => 44, 'width' => 640, 'height' => 92, 'fill' => $t['accent'], 'rx' => 46, 'ry' => 46]),
            $this->display(['layerName' => 'Open', 'text' => 'REGISTRATIONS OPEN', 'x' => 50, 'y' => 44, 'width' => 620, 'fontSize' => 56, 'color' => $t['onAccent']], $t),
            $this->body(['layerName' => 'Starts label', 'dependsOn' => 'start_date', 'text' => 'STARTS', 'x' => 32, 'y' => 52.5, 'width' => 420, 'fontSize' => 20, 'textTransform' => 'uppercase'], $t),
            $this->display(['placeholder' => 'start_date', 'layerName' => 'Start date', 'text' => '14 NOV 2026', 'x' => 32, 'y' => 56.5, 'width' => 420, 'fontSize' => 60], $t),
            $this->body(['layerName' => 'Ends label', 'dependsOn' => 'end_date', 'text' => 'ENDS', 'x' => 68, 'y' => 52.5, 'width' => 420, 'fontSize' => 20, 'textTransform' => 'uppercase'], $t),
            $this->display(['placeholder' => 'end_date', 'layerName' => 'End date', 'text' => '20 DEC 2026', 'x' => 68, 'y' => 56.5, 'width' => 420, 'fontSize' => 60], $t),
            $this->body(['placeholder' => 'location', 'layerName' => 'Location', 'text' => 'CITY CRICKET GROUND', 'x' => 50, 'y' => 62.5, 'width' => 900, 'fontSize' => 30, 'color' => $t['accent'], 'textTransform' => 'uppercase', 'autoSize' => true], $t),
            $this->body(['placeholder' => 'description', 'layerName' => 'Description', 'text' => 'Twelve teams. One trophy. Register your squad today.', 'x' => 50, 'y' => 69, 'width' => 860, 'fontSize' => 28, 'color' => $t['ink'], 'fontWeight' => '600'], $t),
            $this->i(['placeholder' => 'qr_code', 'layerName' => 'QR code', 'x' => 80, 'y' => 82, 'width' => 210, 'height' => 210]),
            $this->body(['layerName' => 'Scan label', 'dependsOn' => 'qr_code', 'text' => 'SCAN TO REGISTER', 'x' => 80, 'y' => 90.5, 'width' => 300, 'fontSize' => 18, 'textTransform' => 'uppercase'], $t),
            $this->body(['placeholder' => 'contact_phone', 'layerName' => 'Phone', 'text' => '+91 98765 43210', 'x' => 36, 'y' => 78.5, 'width' => 620, 'fontSize' => 34, 'color' => $t['ink'], 'fontWeight' => '800', 'textAlign' => 'left'], $t),
            $this->body(['placeholder' => 'registration_link', 'layerName' => 'Link', 'text' => 'sportzley.com/t/premier-league', 'x' => 36, 'y' => 82.5, 'width' => 620, 'fontSize' => 22, 'textAlign' => 'left', 'autoSize' => true], $t),
            ...$this->footer($t, 94, 130, 64),
        ];
    }

    private function flyerClassic(array $t): array
    {
        $this->z = 0;

        return [
            $this->i(['placeholder' => 'tournament_logo', 'layerName' => 'Tournament logo', 'x' => 50, 'y' => 12, 'width' => 220, 'height' => 220]),
            $this->display(['placeholder' => 'tournament_name', 'layerName' => 'Tournament name', 'text' => 'PREMIER LEAGUE 2026', 'x' => 50, 'y' => 26, 'width' => 980, 'fontSize' => 96, 'autoSize' => true], $t),
            $this->body(['placeholder' => 'description', 'layerName' => 'Description', 'text' => 'Twelve teams. One trophy. Register your squad today.', 'x' => 50, 'y' => 34, 'width' => 860, 'fontSize' => 28, 'color' => $t['ink'], 'fontWeight' => '600'], $t),
            $this->s(['layerName' => 'Dates panel', 'x' => 50, 'y' => 48, 'width' => 900, 'height' => 220, 'fill' => $t['panel'], 'rx' => 28, 'ry' => 28]),
            $this->display(['placeholder' => 'start_date', 'layerName' => 'Start date', 'text' => '14 NOV 2026', 'x' => 31, 'y' => 46.5, 'width' => 380, 'fontSize' => 60, 'color' => $t['panelInk']], $t),
            $this->display(['layerName' => 'To', 'text' => 'TO', 'x' => 50, 'y' => 46.5, 'width' => 120, 'fontSize' => 40, 'color' => $t['accent']], $t),
            $this->display(['placeholder' => 'end_date', 'layerName' => 'End date', 'text' => '20 DEC 2026', 'x' => 69, 'y' => 46.5, 'width' => 380, 'fontSize' => 60, 'color' => $t['panelInk']], $t),
            $this->body(['placeholder' => 'location', 'layerName' => 'Location', 'text' => 'CITY CRICKET GROUND', 'x' => 50, 'y' => 52, 'width' => 840, 'fontSize' => 28, 'color' => $t['accent'], 'textTransform' => 'uppercase', 'autoSize' => true], $t),
            $this->i(['placeholder' => 'qr_code', 'layerName' => 'QR code', 'x' => 50, 'y' => 68, 'width' => 240, 'height' => 240]),
            $this->body(['placeholder' => 'registration_link', 'layerName' => 'Link', 'text' => 'sportzley.com/t/premier-league', 'x' => 50, 'y' => 78.5, 'width' => 900, 'fontSize' => 24, 'color' => $t['ink'], 'autoSize' => true], $t),
            $this->body(['placeholder' => 'contact_phone', 'layerName' => 'Phone', 'text' => '+91 98765 43210', 'x' => 50, 'y' => 82.5, 'width' => 900, 'fontSize' => 30, 'color' => $t['ink'], 'fontWeight' => '800'], $t),
            ...$this->footer($t),
        ];
    }

    // ---- champions ----------------------------------------------------------------

    private function championsTrophy(array $t): array
    {
        $this->z = 0;

        return [
            ...$this->header($t),
            $this->display(['placeholder' => 'title', 'layerName' => 'Title', 'text' => 'CHAMPIONS', 'x' => 50, 'y' => 23, 'width' => 1040, 'fontSize' => 190, 'color' => $t['accent'], 'autoSize' => true], $t),
            $this->i(['placeholder' => 'team_logo', 'layerName' => 'Team logo', 'x' => 50, 'y' => 50, 'width' => 520, 'height' => 520]),
            $this->display(['placeholder' => 'team_name', 'layerName' => 'Team name', 'text' => 'ROYAL STRIKERS', 'x' => 50, 'y' => 73, 'width' => 1000, 'fontSize' => 104, 'autoSize' => true], $t),
            $this->body(['placeholder' => 'season', 'layerName' => 'Season', 'text' => 'SEASON 2', 'x' => 50, 'y' => 79, 'width' => 900, 'fontSize' => 34, 'color' => $t['accent'], 'fontWeight' => '800', 'textTransform' => 'uppercase'], $t),
            $this->body(['placeholder' => 'year', 'layerName' => 'Year', 'text' => '2026', 'x' => 50, 'y' => 83, 'width' => 900, 'fontSize' => 28], $t),
            ...$this->footer($t),
        ];
    }

    private function championsBanner(array $t): array
    {
        $this->z = 0;

        return [
            $this->i(['placeholder' => 'tournament_logo', 'layerName' => 'Tournament logo', 'x' => 14, 'y' => 8, 'width' => 120, 'height' => 120]),
            $this->body(['placeholder' => 'tournament_name', 'layerName' => 'Tournament name', 'text' => 'PREMIER LEAGUE', 'x' => 57, 'y' => 8, 'width' => 760, 'fontSize' => 28, 'textAlign' => 'left', 'textTransform' => 'uppercase', 'autoSize' => true], $t),
            $this->display(['placeholder' => 'title', 'layerName' => 'Title', 'text' => 'CHAMPIONS', 'x' => 50, 'y' => 20, 'width' => 1000, 'fontSize' => 150, 'autoSize' => true], $t),
            $this->t(['placeholder' => 'year', 'layerName' => 'Year', 'text' => '2026', 'x' => 50, 'y' => 30, 'width' => 600, 'fontSize' => 110, 'fontFamily' => 'Anton', 'fontWeight' => '400', 'color' => $t['accent'], 'textAlign' => 'center']),
            $this->i(['placeholder' => 'team_logo', 'layerName' => 'Team logo', 'x' => 50, 'y' => 54, 'width' => 540, 'height' => 540]),
            $this->s(['layerName' => 'Name band', 'x' => 50, 'y' => 78, 'width' => 1080, 'height' => 190, 'fill' => $t['accent']]),
            $this->display(['placeholder' => 'team_name', 'layerName' => 'Team name', 'text' => 'ROYAL STRIKERS', 'x' => 50, 'y' => 76.8, 'width' => 1000, 'fontSize' => 100, 'color' => $t['onAccent'], 'autoSize' => true], $t),
            $this->body(['placeholder' => 'season', 'layerName' => 'Season', 'text' => 'SEASON 2', 'x' => 50, 'y' => 81.6, 'width' => 900, 'fontSize' => 28, 'color' => $t['onAccent'], 'fontWeight' => '800', 'textTransform' => 'uppercase'], $t),
            ...$this->footer($t),
        ];
    }

    // ---- point table / fixtures -------------------------------------------------------

    private function pointTable(array $t, bool $compact): array
    {
        $this->z = 0;

        return [
            ...$this->header($t, $compact ? 5.5 : 6.5),
            $this->display(['layerName' => 'Title', 'text' => 'POINTS TABLE', 'x' => 50, 'y' => $compact ? 16.5 : 18.5, 'width' => 900, 'fontSize' => $compact ? 70 : 84], $t),
            $this->body(['placeholder' => 'group_name', 'layerName' => 'Group', 'text' => 'GROUP A', 'x' => 50, 'y' => $compact ? 21 : 23.5, 'width' => 900, 'fontSize' => 30, 'color' => $t['accent'], 'fontWeight' => '800', 'textTransform' => 'uppercase'], $t),
            [
                'type' => 'tableArea', 'placeholder' => 'table_data', 'layerName' => 'Table',
                'x' => 50, 'y' => $compact ? 55 : 56, 'width' => $compact ? 1000 : 980, 'height' => $compact ? 860 : 780,
                'rotation' => 0, 'opacity' => 100, 'zIndex' => $this->z(), 'hidden' => false, 'locked' => false,
                'tableConfig' => [
                    'headerBg' => $t['accent'], 'headerText' => $t['onAccent'],
                    'evenRowBg' => $t['rowA'], 'oddRowBg' => $t['rowB'], 'qualifiedBg' => $t['light'] ? '#dcfce7' : '#064e3b',
                    'textColor' => $t['ink'], 'pointsColor' => $t['accent'],
                    'fontSize' => $compact ? 16 : 18, 'rowHeight' => $compact ? 60 : 78,
                    'showTeamLogo' => true, 'showNRR' => true, 'showLegend' => ! $compact,
                ],
            ],
            $this->body(['placeholder' => 'last_updated', 'layerName' => 'Updated', 'text' => 'UPDATED 14 NOV 2026', 'x' => 50, 'y' => 87.5, 'width' => 900, 'fontSize' => 18, 'textTransform' => 'uppercase'], $t),
            ...$this->footer($t),
        ];
    }

    private function fixtures(array $t, string $layout): array
    {
        $this->z = 0;

        return [
            ...$this->header($t),
            $this->display(['layerName' => 'Title', 'text' => 'UPCOMING FIXTURES', 'x' => 50, 'y' => 18.5, 'width' => 980, 'fontSize' => 86, 'autoSize' => true], $t),
            [
                'type' => 'fixtureArea', 'placeholder' => 'fixture_area', 'layerName' => 'Fixtures',
                'x' => 50, 'y' => 55, 'width' => 1000, 'height' => 820,
                'rotation' => 0, 'opacity' => 100, 'zIndex' => $this->z(), 'hidden' => false, 'locked' => false,
                'fixtureConfig' => [
                    'layout' => $layout, 'maxRows' => 6, 'transparentBg' => $layout === 'row',
                    'rowBg' => $t['rowA'], 'altRowBg' => $t['rowB'], 'textColor' => $t['ink'], 'accentColor' => $t['accent'],
                    'mutedColor' => $t['muted'], 'headerBg' => $t['accent'], 'headerText' => $t['onAccent'],
                    // One full-width card per match fills the area; rows get larger type.
                    'cardColumns' => 1, 'fontSize' => $layout === 'card' ? 20 : 18, 'rowGap' => 14, 'rowPadding' => 22,
                ],
            ],
            ...$this->footer($t),
        ];
    }

    // ---- auction ------------------------------------------------------------------------

    private function auctionLandscape(array $t, bool $sold): array
    {
        $this->z = 0;
        $col = 64; // centre of the 900-px right column

        $common = [
            $this->i(['placeholder' => 'player_image', 'layerName' => 'Player photo', 'x' => 22, 'y' => 54, 'width' => 640, 'height' => 820]),
            $this->i(['placeholder' => 'tournament_logo', 'layerName' => 'Tournament logo', 'x' => 92, 'y' => 12, 'width' => 120, 'height' => 120]),
            $this->body(['placeholder' => 'pool_name', 'layerName' => 'Pool', 'text' => 'POOL A · LOT 12', 'x' => $col, 'y' => 12, 'width' => 900, 'fontSize' => 26, 'color' => $t['accent'], 'textAlign' => 'left', 'textTransform' => 'uppercase'], $t),
            $this->display(['placeholder' => 'player_name', 'layerName' => 'Player name', 'text' => 'ARJUN MEHTA', 'x' => $col, 'y' => 24, 'width' => 900, 'fontSize' => 120, 'textAlign' => 'left', 'autoSize' => true], $t),
            $this->body(['placeholder' => 'player_type', 'layerName' => 'Role', 'text' => 'ALL-ROUNDER', 'x' => $col, 'y' => 34, 'width' => 900, 'fontSize' => 34, 'color' => $t['ink'], 'fontWeight' => '800', 'textAlign' => 'left', 'textTransform' => 'uppercase'], $t),
            $this->body(['placeholder' => 'batting_style', 'layerName' => 'Batting', 'text' => 'RIGHT-HAND BAT', 'x' => $col, 'y' => 39.5, 'width' => 900, 'fontSize' => 24, 'textAlign' => 'left', 'textTransform' => 'uppercase'], $t),
            $this->body(['placeholder' => 'bowling_style', 'layerName' => 'Bowling', 'text' => 'RIGHT-ARM MEDIUM', 'x' => $col, 'y' => 44, 'width' => 900, 'fontSize' => 24, 'textAlign' => 'left', 'textTransform' => 'uppercase'], $t),
        ];

        if (! $sold) {
            return [
                ...$common,
                $this->s(['layerName' => 'Stats panel', 'x' => $col, 'y' => 60, 'width' => 900, 'height' => 170, 'fill' => $t['panel'], 'rx' => 20, 'ry' => 20]),
                ...$this->stat(47, 58, 'MATCHES', 'total_matches', '42', $t),
                ...$this->stat(64, 58, 'RUNS', 'total_runs', '1,284', $t),
                ...$this->stat(81, 58, 'WICKETS', 'total_wickets', '37', $t),
                $this->body(['layerName' => 'Base label', 'dependsOn' => 'base_price', 'text' => 'BASE PRICE', 'x' => $col, 'y' => 76, 'width' => 900, 'fontSize' => 22, 'textAlign' => 'left', 'textTransform' => 'uppercase'], $t),
                $this->display(['placeholder' => 'base_price', 'layerName' => 'Base price', 'text' => '₹ 50,000', 'x' => $col, 'y' => 83, 'width' => 900, 'fontSize' => 96, 'color' => $t['accent'], 'textAlign' => 'left'], $t),
                $this->i(['placeholder' => 'sponsor_logo', 'layerName' => 'Sponsor', 'x' => 92, 'y' => 90, 'width' => 150, 'height' => 70]),
            ];
        }

        return [
            ...$common,
            $this->s(['layerName' => 'Sold band', 'x' => $col, 'y' => 62, 'width' => 900, 'height' => 120, 'fill' => $t['accent'], 'rx' => 20, 'ry' => 20]),
            $this->display(['placeholder' => 'sold_status', 'layerName' => 'Status', 'text' => 'SOLD', 'x' => $col, 'y' => 62, 'width' => 860, 'fontSize' => 92, 'color' => $t['onAccent']], $t),
            $this->i(['placeholder' => 'sold_team_logo', 'layerName' => 'Buying team logo', 'x' => 43, 'y' => 82, 'width' => 150, 'height' => 150]),
            $this->body(['placeholder' => 'sold_team_name', 'layerName' => 'Buying team', 'text' => 'ROYAL STRIKERS', 'x' => 70, 'y' => 78.5, 'width' => 640, 'fontSize' => 36, 'color' => $t['ink'], 'fontWeight' => '800', 'textAlign' => 'left', 'textTransform' => 'uppercase', 'autoSize' => true], $t),
            $this->display(['placeholder' => 'sold_price', 'layerName' => 'Price', 'text' => '₹ 2,40,000', 'x' => 70, 'y' => 86, 'width' => 640, 'fontSize' => 96, 'color' => $t['accent'], 'textAlign' => 'left'], $t),
        ];
    }

    private function auctionPortrait(array $t, bool $sold): array
    {
        $this->z = 0;

        $common = [
            ...$this->header($t, 5.5),
            $this->body(['placeholder' => 'pool_name', 'layerName' => 'Pool', 'text' => 'POOL A · LOT 12', 'x' => 50, 'y' => 16, 'width' => 900, 'fontSize' => 24, 'color' => $t['accent'], 'textTransform' => 'uppercase'], $t),
            $this->i(['placeholder' => 'player_image', 'layerName' => 'Player photo', 'x' => 50, 'y' => 41, 'width' => 640, 'height' => 640]),
            $this->display(['placeholder' => 'player_name', 'layerName' => 'Player name', 'text' => 'ARJUN MEHTA', 'x' => 50, 'y' => 66, 'width' => 1000, 'fontSize' => 100, 'autoSize' => true], $t),
            $this->body(['placeholder' => 'player_type', 'layerName' => 'Role', 'text' => 'ALL-ROUNDER', 'x' => 50, 'y' => 71, 'width' => 900, 'fontSize' => 30, 'color' => $t['ink'], 'fontWeight' => '800', 'textTransform' => 'uppercase'], $t),
        ];

        if (! $sold) {
            return [
                ...$common,
                $this->s(['layerName' => 'Stats panel', 'x' => 50, 'y' => 79.5, 'width' => 900, 'height' => 150, 'fill' => $t['panel'], 'rx' => 20, 'ry' => 20]),
                ...$this->stat(25, 78.3, 'MATCHES', 'total_matches', '42', $t, 56),
                ...$this->stat(50, 78.3, 'RUNS', 'total_runs', '1,284', $t, 56),
                ...$this->stat(75, 78.3, 'WICKETS', 'total_wickets', '37', $t, 56),
                $this->body(['layerName' => 'Base label', 'dependsOn' => 'base_price', 'text' => 'BASE PRICE', 'x' => 50, 'y' => 87.2, 'width' => 900, 'fontSize' => 20, 'textTransform' => 'uppercase'], $t),
                $this->display(['placeholder' => 'base_price', 'layerName' => 'Base price', 'text' => '₹ 50,000', 'x' => 50, 'y' => 92, 'width' => 900, 'fontSize' => 80, 'color' => $t['accent']], $t),
            ];
        }

        return [
            ...$common,
            $this->s(['layerName' => 'Sold band', 'x' => 50, 'y' => 79, 'width' => 1080, 'height' => 120, 'fill' => $t['accent']]),
            $this->display(['placeholder' => 'sold_status', 'layerName' => 'Status', 'text' => 'SOLD', 'x' => 50, 'y' => 79, 'width' => 900, 'fontSize' => 92, 'color' => $t['onAccent']], $t),
            $this->i(['placeholder' => 'sold_team_logo', 'layerName' => 'Buying team logo', 'x' => 20, 'y' => 91, 'width' => 140, 'height' => 140]),
            $this->body(['placeholder' => 'sold_team_name', 'layerName' => 'Buying team', 'text' => 'ROYAL STRIKERS', 'x' => 58, 'y' => 88, 'width' => 640, 'fontSize' => 32, 'color' => $t['ink'], 'fontWeight' => '800', 'textAlign' => 'left', 'textTransform' => 'uppercase', 'autoSize' => true], $t),
            $this->display(['placeholder' => 'sold_price', 'layerName' => 'Price', 'text' => '₹ 2,40,000', 'x' => 58, 'y' => 93.5, 'width' => 640, 'fontSize' => 80, 'color' => $t['accent'], 'textAlign' => 'left'], $t),
        ];
    }
}
