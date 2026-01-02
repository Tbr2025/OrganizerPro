<?php

namespace App\Services\Poster;

use App\Models\Matches;
use App\Models\Tournament;
use Carbon\Carbon;

class MatchPosterService extends PosterGeneratorService
{
    protected string $outputDirectory = 'match_posters';

    /**
     * Generate match poster
     */
    public function generate($match): string
    {
        if (!$match instanceof Matches) {
            throw new \InvalidArgumentException('Expected Matches model');
        }

        $tournament = $match->tournament;
        $settings = $tournament->settings;

        // Determine dimensions based on template or default
        $width = 1080;
        $height = 1080;

        // Create canvas with background
        if ($settings?->background_image) {
            $canvas = $this->loadBackground($settings->background_image);
            if (!$canvas) {
                $canvas = $this->createCanvas($width, $height, $settings->primary_color ?? '#1a365d');
            }
        } else {
            $canvas = $this->createCanvas($width, $height, '#1a365d');
        }

        // Add tournament logo at top
        if ($settings?->logo) {
            $this->addCircularImage($canvas, $settings->logo, $width / 2, 100, 120);
        }

        // Tournament name
        $this->addText(
            $canvas,
            $tournament->name,
            $width / 2,
            200,
            36,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Match stage
        $this->addText(
            $canvas,
            $match->stage_display,
            $width / 2,
            250,
            24,
            '#FFD700',
            'Montserrat-Medium.ttf',
            'center'
        );

        // Team A (Left side)
        $teamALogoX = 200;
        $teamALogoY = 450;
        if ($match->teamA?->team_logo) {
            $this->addCircularImage($canvas, $match->teamA->team_logo, $teamALogoX, $teamALogoY, 180);
        } else {
            // Draw placeholder circle
            $this->drawPlaceholderCircle($canvas, $teamALogoX, $teamALogoY, 180, '#333333');
        }
        $this->addText(
            $canvas,
            $match->teamA?->name ?? 'TBD',
            $teamALogoX,
            $teamALogoY + 120,
            28,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // VS text
        $this->addText(
            $canvas,
            'VS',
            $width / 2,
            450,
            48,
            '#FFD700',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Team B (Right side)
        $teamBLogoX = $width - 200;
        $teamBLogoY = 450;
        if ($match->teamB?->team_logo) {
            $this->addCircularImage($canvas, $match->teamB->team_logo, $teamBLogoX, $teamBLogoY, 180);
        } else {
            $this->drawPlaceholderCircle($canvas, $teamBLogoX, $teamBLogoY, 180, '#333333');
        }
        $this->addText(
            $canvas,
            $match->teamB?->name ?? 'TBD',
            $teamBLogoX,
            $teamBLogoY + 120,
            28,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Match details section
        $detailsY = 700;

        // Date
        $dateText = $match->match_date
            ? Carbon::parse($match->match_date)->format('l, d M Y')
            : 'Date TBD';
        $this->addText(
            $canvas,
            $dateText,
            $width / 2,
            $detailsY,
            28,
            '#FFFFFF',
            'Montserrat-Medium.ttf',
            'center'
        );

        // Time
        if ($match->start_time) {
            $timeText = Carbon::parse($match->start_time)->format('h:i A');
            $this->addText(
                $canvas,
                $timeText,
                $width / 2,
                $detailsY + 50,
                24,
                '#CCCCCC',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Venue
        $venueText = $match->ground?->name ?? $match->venue ?? $tournament->location ?? '';
        if ($venueText) {
            $this->addText(
                $canvas,
                $venueText,
                $width / 2,
                $detailsY + 100,
                22,
                '#CCCCCC',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Match number
        if ($match->match_number) {
            $this->addText(
                $canvas,
                "Match #{$match->match_number}",
                $width / 2,
                $height - 80,
                20,
                '#888888',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Save and return path
        $filename = $this->generateFilename('match-' . $match->id);
        $path = $this->saveImage($canvas, $filename);

        // Update match with poster path
        $match->update(['poster_image' => $path]);

        return $path;
    }

    /**
     * Draw a placeholder circle
     */
    protected function drawPlaceholderCircle(\GdImage $canvas, int $centerX, int $centerY, int $diameter, string $color): void
    {
        $rgb = $this->hexToRgb($color);
        $fillColor = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);

        imagefilledellipse(
            $canvas,
            $centerX,
            $centerY,
            $diameter,
            $diameter,
            $fillColor
        );
    }

    /**
     * Generate finals/semi-finals poster with special template
     */
    public function generateFinalsPosters(Matches $match): string
    {
        $tournament = $match->tournament;
        $settings = $tournament->settings;

        $width = 1080;
        $height = 1350; // Taller for finals

        // Create canvas with darker gradient background
        $canvas = $this->createCanvas($width, $height, '#0a0a0a');

        // Add special header based on stage
        $headerText = match ($match->stage) {
            'final' => 'GRAND FINAL',
            'semi_final' => 'SEMI FINAL',
            'third_place' => '3RD PLACE PLAYOFF',
            default => strtoupper($match->stage_display),
        };

        // Gold banner for finals
        $this->addText(
            $canvas,
            $headerText,
            $width / 2,
            80,
            48,
            '#FFD700',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Tournament logo
        if ($settings?->logo) {
            $this->addCircularImage($canvas, $settings->logo, $width / 2, 200, 150);
        }

        // Tournament name
        $this->addText(
            $canvas,
            $tournament->name,
            $width / 2,
            320,
            32,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Team logos and names (larger for finals)
        $teamALogoX = 270;
        $teamBLogoX = $width - 270;
        $teamsY = 550;

        if ($match->teamA?->team_logo) {
            $this->addCircularImage($canvas, $match->teamA->team_logo, $teamALogoX, $teamsY, 220);
        }
        $this->addText(
            $canvas,
            $match->teamA?->name ?? 'TBD',
            $teamALogoX,
            $teamsY + 150,
            26,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // VS
        $this->addText($canvas, 'VS', $width / 2, $teamsY, 56, '#FFD700', 'Montserrat-Bold.ttf', 'center');

        if ($match->teamB?->team_logo) {
            $this->addCircularImage($canvas, $match->teamB->team_logo, $teamBLogoX, $teamsY, 220);
        }
        $this->addText(
            $canvas,
            $match->teamB?->name ?? 'TBD',
            $teamBLogoX,
            $teamsY + 150,
            26,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Match details
        $detailsY = 850;
        $dateText = $match->match_date
            ? Carbon::parse($match->match_date)->format('l, d M Y')
            : 'Date TBD';

        $this->addText($canvas, $dateText, $width / 2, $detailsY, 32, '#FFFFFF', 'Montserrat-Medium.ttf', 'center');

        if ($match->start_time) {
            $this->addText(
                $canvas,
                Carbon::parse($match->start_time)->format('h:i A'),
                $width / 2,
                $detailsY + 50,
                28,
                '#CCCCCC',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        $venue = $match->ground?->name ?? $match->venue ?? '';
        if ($venue) {
            $this->addText($canvas, $venue, $width / 2, $detailsY + 100, 24, '#CCCCCC', 'Montserrat-Medium.ttf', 'center');
        }

        // "Champions will be crowned" text for final
        if ($match->stage === 'final') {
            $this->addText(
                $canvas,
                'CHAMPIONS WILL BE CROWNED',
                $width / 2,
                $height - 100,
                20,
                '#FFD700',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        $filename = $this->generateFilename('finals-' . $match->id);
        $path = $this->saveImage($canvas, $filename);

        $match->update(['poster_image' => $path]);

        return $path;
    }
}
