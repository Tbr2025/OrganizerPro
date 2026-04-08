<?php

namespace App\Services\Poster;

use App\Models\Matches;
use App\Models\TournamentTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class EnhancedMatchPosterService extends PosterGeneratorService
{
    protected string $outputDirectory = 'match_posters';

    // Default colors (can be overridden by team colors)
    protected string $teamAColor = '#00BCD4'; // Cyan/Teal
    protected string $teamBColor = '#FF9800'; // Orange
    protected string $backgroundColor = '#1a1a2e';

    /**
     * Generate match poster matching the Kerala League design
     */
    public function generate($match): string
    {
        if (!$match instanceof Matches) {
            throw new \InvalidArgumentException('Expected Matches model');
        }

        return $this->generateSplitDesign($match);
    }

    /**
     * Generate the split design poster (Kerala League style)
     */
    public function generateSplitDesign(Matches $match): string
    {
        $tournament = $match->tournament;
        $settings = $tournament->settings;

        $width = 1080;
        $height = 1080;

        // Get team colors
        $teamAColor = $match->teamA?->primary_color ?? $this->teamAColor;
        $teamBColor = $match->teamB?->primary_color ?? $this->teamBColor;

        // Create base canvas with dark background
        $canvas = $this->createCanvas($width, $height, $this->backgroundColor);

        // Draw split background with diagonal colored shapes
        $this->drawSplitBackground($canvas, $width, $height, $teamAColor, $teamBColor);

        // Add decorative tribal patterns at bottom corners (optional)
        $this->drawDecorativePatterns($canvas, $width, $height);

        // Add tournament sponsors at top left
        $this->drawTopSponsors($canvas, $settings);

        // Add tournament logo at top right
        $this->drawTournamentLogo($canvas, $settings, $width);

        // Draw Team A (left side) with captain image
        $this->drawTeamSection($canvas, $match->teamA, 'left', $width, $height, $teamAColor);

        // Draw Team B (right side) with captain image
        $this->drawTeamSection($canvas, $match->teamB, 'right', $width, $height, $teamBColor);

        // Draw center date block
        $this->drawDateBlock($canvas, $match, $width, $height);

        // Draw VS graphic
        $this->drawVsGraphic($canvas, $width, $height);

        // Draw venue at bottom
        $this->drawVenue($canvas, $match, $width, $height);

        // Draw bottom sponsors
        $this->drawBottomSponsors($canvas, $settings, $width, $height);

        // Save and return path
        $filename = $this->generateFilename('match-' . $match->id);
        $path = $this->saveImage($canvas, $filename);

        // Update match with poster path
        $match->update(['poster_image' => $path]);

        return $path;
    }

    /**
     * Draw split background with diagonal colored accents
     */
    protected function drawSplitBackground(\GdImage $canvas, int $width, int $height, string $teamAColor, string $teamBColor): void
    {
        // Team A colored shape (left side with diagonal cut)
        $rgbA = $this->hexToRgb($teamAColor);
        $colorA = imagecolorallocate($canvas, $rgbA['r'], $rgbA['g'], $rgbA['b']);

        // Draw left diagonal shape (trapezoid)
        $pointsA = [
            0, 100,           // Top left
            280, 100,         // Top right
            220, $height - 200,  // Bottom right (angled)
            0, $height - 200,    // Bottom left
        ];
        imagefilledpolygon($canvas, $pointsA, $colorA);

        // Team B colored shape (right side with diagonal cut)
        $rgbB = $this->hexToRgb($teamBColor);
        $colorB = imagecolorallocate($canvas, $rgbB['r'], $rgbB['g'], $rgbB['b']);

        // Draw right diagonal shape (trapezoid)
        $pointsB = [
            $width - 280, 100,       // Top left
            $width, 100,             // Top right
            $width, $height - 200,   // Bottom right
            $width - 220, $height - 200,  // Bottom left (angled)
        ];
        imagefilledpolygon($canvas, $pointsB, $colorB);
    }

    /**
     * Draw decorative patterns at bottom corners
     */
    protected function drawDecorativePatterns(\GdImage $canvas, int $width, int $height): void
    {
        // Semi-transparent white for tribal patterns
        $patternColor = imagecolorallocatealpha($canvas, 255, 255, 255, 100);

        // Simple geometric pattern at bottom left
        for ($i = 0; $i < 3; $i++) {
            $x = 30 + ($i * 40);
            $y = $height - 120 + ($i * 20);
            imagefilledellipse($canvas, $x, $y, 20, 20, $patternColor);
        }

        // Simple geometric pattern at bottom right
        for ($i = 0; $i < 3; $i++) {
            $x = $width - 30 - ($i * 40);
            $y = $height - 120 + ($i * 20);
            imagefilledellipse($canvas, $x, $y, 20, 20, $patternColor);
        }
    }

    /**
     * Draw top sponsor logos
     */
    protected function drawTopSponsors(\GdImage $canvas, $settings): void
    {
        // Tournament logo on the left side area (placeholder area)
        // This area would typically have multiple small sponsor logos
        // For now, we'll just add placeholder text
        $this->addText(
            $canvas,
            'SPONSORS',
            80,
            50,
            10,
            '#666666',
            'Montserrat-Medium.ttf',
            'center'
        );
    }

    /**
     * Draw tournament logo and name at top right
     */
    protected function drawTournamentLogo(\GdImage $canvas, $settings, int $width): void
    {
        if ($settings?->logo && Storage::disk('public')->exists($settings->logo)) {
            // Add tournament logo
            $this->addImage($canvas, $settings->logo, $width - 150, 30, 200, null);
        }

        // Tournament tagline (if needed)
        $tournament = $settings?->tournament;
        if ($tournament) {
            $this->addText(
                $canvas,
                strtoupper($tournament->name ?? 'TOURNAMENT'),
                $width - 100,
                130,
                14,
                '#FFFFFF',
                'Oswald-Bold.ttf',
                'center'
            );
        }
    }

    /**
     * Draw team section (player photo, team name, location, sponsor)
     */
    protected function drawTeamSection(\GdImage $canvas, $team, string $side, int $width, int $height, string $teamColor): void
    {
        if (!$team) return;

        $isLeft = $side === 'left';
        $centerX = $isLeft ? 200 : $width - 200;
        $playerY = 380;

        // Draw player/captain image
        $captainImage = $team->captain_image ?? null;
        if ($captainImage && Storage::disk('public')->exists($captainImage)) {
            // Draw rectangular player image with some styling
            $this->addImage($canvas, $captainImage, $centerX - 120, $playerY - 150, 240, 300);
        } else {
            // Draw placeholder for player image
            $this->drawPlayerPlaceholder($canvas, $centerX, $playerY, $teamColor);
        }

        // Draw team location (district) - e.g., "Ernakulam"
        $location = $team->location ?? '';
        if ($location) {
            $this->addText(
                $canvas,
                $location,
                $centerX,
                $height - 280,
                42,
                '#FFFFFF',
                'Oswald-Bold.ttf',
                'center'
            );
        }

        // Draw team name (club) - e.g., "Mountrich Cricket Club"
        $this->addText(
            $canvas,
            $team->name ?? 'Team',
            $centerX,
            $height - 230,
            18,
            '#FFFFFF',
            'Montserrat-Medium.ttf',
            'center'
        );

        // Draw team sponsor logo
        if ($team->sponsor_logo && Storage::disk('public')->exists($team->sponsor_logo)) {
            $this->addImage($canvas, $team->sponsor_logo, $centerX - 50, $height - 180, 100, 50);
        }
    }

    /**
     * Draw player placeholder when no image is available
     */
    protected function drawPlayerPlaceholder(\GdImage $canvas, int $centerX, int $centerY, string $color): void
    {
        $rgb = $this->hexToRgb($color);
        $bgColor = imagecolorallocatealpha($canvas, $rgb['r'], $rgb['g'], $rgb['b'], 80);

        // Draw a rounded rectangle placeholder
        imagefilledrectangle($canvas, $centerX - 100, $centerY - 130, $centerX + 100, $centerY + 130, $bgColor);

        // Add "Player" text
        $this->addText(
            $canvas,
            'PLAYER',
            $centerX,
            $centerY,
            20,
            '#FFFFFF',
            'Montserrat-Medium.ttf',
            'center'
        );
    }

    /**
     * Draw styled date block in center
     */
    protected function drawDateBlock(\GdImage $canvas, Matches $match, int $width, int $height): void
    {
        $centerX = $width / 2;
        $dateBlockY = 280;

        if (!$match->match_date) {
            $this->addText($canvas, 'TBD', $centerX, $dateBlockY + 50, 48, '#FFD700', 'Oswald-Bold.ttf', 'center');
            return;
        }

        $date = Carbon::parse($match->match_date);

        // Month (FEB)
        $this->addText(
            $canvas,
            strtoupper($date->format('M')),
            $centerX,
            $dateBlockY,
            24,
            '#FFFFFF',
            'Oswald-Bold.ttf',
            'center'
        );

        // Day number (18) - Large
        $this->addText(
            $canvas,
            $date->format('d'),
            $centerX,
            $dateBlockY + 60,
            72,
            '#FFFFFF',
            'Oswald-Bold.ttf',
            'center'
        );

        // Weekday (WED)
        $this->addText(
            $canvas,
            strtoupper($date->format('D')),
            $centerX,
            $dateBlockY + 105,
            22,
            '#FFFFFF',
            'Oswald-Medium.ttf',
            'center'
        );

        // Time (09:00PM)
        if ($match->start_time) {
            $time = Carbon::parse($match->start_time)->format('h:iA');
            $this->addText(
                $canvas,
                $time,
                $centerX,
                $dateBlockY + 140,
                20,
                '#FFD700', // Gold for time
                'Oswald-Medium.ttf',
                'center'
            );
        }
    }

    /**
     * Draw VS graphic in center
     */
    protected function drawVsGraphic(\GdImage $canvas, int $width, int $height): void
    {
        $centerX = $width / 2;
        $vsY = $height - 280;

        // Draw VS with gradient effect (simplified - just two-tone)
        // First draw shadow
        $this->addText(
            $canvas,
            'VS',
            $centerX + 3,
            $vsY + 3,
            64,
            '#000000',
            'Oswald-Bold.ttf',
            'center'
        );

        // Then draw main VS
        $this->addText(
            $canvas,
            'VS',
            $centerX,
            $vsY,
            64,
            '#FFD700',
            'Oswald-Bold.ttf',
            'center'
        );
    }

    /**
     * Draw venue information at bottom center
     */
    protected function drawVenue(\GdImage $canvas, Matches $match, int $width, int $height): void
    {
        $venue = $match->ground?->name ?? $match->venue ?? '';
        if (!$venue) return;

        $this->addText(
            $canvas,
            strtoupper($venue),
            $width / 2,
            $height - 100,
            16,
            '#FFFFFF',
            'Oswald-Medium.ttf',
            'center'
        );
    }

    /**
     * Draw bottom sponsor logos
     */
    protected function drawBottomSponsors(\GdImage $canvas, $settings, int $width, int $height): void
    {
        // Draw a dark strip at bottom for sponsors
        $stripColor = imagecolorallocatealpha($canvas, 0, 0, 0, 60);
        imagefilledrectangle($canvas, 0, $height - 60, $width, $height, $stripColor);

        // Placeholder text for sponsors area
        $this->addText(
            $canvas,
            'POWERED BY SPONSORS',
            $width / 2,
            $height - 30,
            12,
            '#888888',
            'Montserrat-Medium.ttf',
            'center'
        );
    }

    /**
     * Generate poster using tournament template
     */
    public function generateFromTemplate(Matches $match, TournamentTemplate $template): string
    {
        $templateService = new TemplateRenderService();

        // Prepare data from match
        $data = $this->prepareMatchData($match);

        // Render using template
        $path = $templateService->renderAndSave($template, $data, $this->generateFilename('match-' . $match->id));

        // Update match with poster path
        $match->update(['poster_image' => $path]);

        return $path;
    }

    /**
     * Prepare match data for template rendering
     */
    protected function prepareMatchData(Matches $match): array
    {
        $tournament = $match->tournament;
        $settings = $tournament->settings;

        $data = [
            'tournament_name' => $tournament->name ?? '',
            'tournament_logo' => $settings?->logo ?? '',

            'team_a_name' => $match->teamA?->name ?? 'TBD',
            'team_a_short_name' => $match->teamA?->short_name ?? $match->teamA?->name ?? 'TBD',
            'team_a_logo' => $match->teamA?->team_logo ?? '',
            'team_a_location' => $match->teamA?->location ?? '',
            'team_a_captain_image' => $match->teamA?->captain_image ?? '',
            'team_a_sponsor_logo' => $match->teamA?->sponsor_logo ?? '',
            'team_a_color' => $match->teamA?->primary_color ?? $this->teamAColor,

            'team_b_name' => $match->teamB?->name ?? 'TBD',
            'team_b_short_name' => $match->teamB?->short_name ?? $match->teamB?->name ?? 'TBD',
            'team_b_logo' => $match->teamB?->team_logo ?? '',
            'team_b_location' => $match->teamB?->location ?? '',
            'team_b_captain_image' => $match->teamB?->captain_image ?? '',
            'team_b_sponsor_logo' => $match->teamB?->sponsor_logo ?? '',
            'team_b_color' => $match->teamB?->primary_color ?? $this->teamBColor,

            'match_stage' => $match->stage_display ?? '',
            'match_number' => $match->match_number ?? '',

            'venue' => $match->ground?->name ?? $match->venue ?? '',
            'ground_name' => $match->ground?->name ?? $match->venue ?? '',
        ];

        // Format date/time
        if ($match->match_date) {
            $date = Carbon::parse($match->match_date);
            $data['match_date'] = $date->format('M d, Y');
            $data['match_day'] = $date->format('l');
            $data['match_date_day'] = $date->format('d');
            $data['match_date_month'] = strtoupper($date->format('M'));
            $data['match_date_weekday'] = strtoupper($date->format('D'));
        }

        if ($match->start_time) {
            $data['match_time'] = Carbon::parse($match->start_time)->format('h:i A');
        }

        return $data;
    }
}
