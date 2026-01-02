<?php

namespace App\Services\Poster;

use App\Models\MatchAward;
use App\Models\Player;
use Carbon\Carbon;

class AwardPosterService extends PosterGeneratorService
{
    protected string $outputDirectory = 'award_posters';

    /**
     * Generate award poster
     */
    public function generate($matchAward): string
    {
        if (!$matchAward instanceof MatchAward) {
            throw new \InvalidArgumentException('Expected MatchAward model');
        }

        $match = $matchAward->match;
        $player = $matchAward->player;
        $award = $matchAward->tournamentAward;
        $tournament = $match->tournament;
        $settings = $tournament->settings;

        $width = 1080;
        $height = 1350;

        // Create canvas with background
        if ($settings?->background_image) {
            $canvas = $this->loadBackground($settings->background_image);
            if (!$canvas) {
                $canvas = $this->createCanvas($width, $height, '#1a1a2e');
            }
        } else {
            $canvas = $this->createCanvas($width, $height, '#1a1a2e');
        }

        // Award title
        $this->addText(
            $canvas,
            strtoupper($award->name),
            $width / 2,
            80,
            40,
            '#FFD700',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Tournament logo
        if ($settings?->logo) {
            $this->addCircularImage($canvas, $settings->logo, $width / 2, 180, 100);
        }

        // Tournament name
        $this->addText(
            $canvas,
            $tournament->name,
            $width / 2,
            260,
            24,
            '#FFFFFF',
            'Montserrat-Medium.ttf',
            'center'
        );

        // Player image (large, center)
        $playerImageY = 550;
        if ($player->image_path) {
            $this->addImage($canvas, $player->image_path, ($width - 400) / 2, 320, 400, null);
        } else {
            // Placeholder
            $this->drawPlaceholderCircle($canvas, $width / 2, $playerImageY, 300, '#333333');
        }

        // Player name
        $this->addText(
            $canvas,
            $player->jersey_name ?? $player->name,
            $width / 2,
            800,
            48,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Team name
        $teamName = $player->actualTeam?->name ?? '';
        if ($teamName) {
            $this->addText(
                $canvas,
                $teamName,
                $width / 2,
                860,
                28,
                '#CCCCCC',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Match info
        $matchInfo = "{$match->teamA?->name} vs {$match->teamB?->name}";
        $this->addText(
            $canvas,
            $matchInfo,
            $width / 2,
            950,
            22,
            '#888888',
            'Montserrat-Medium.ttf',
            'center'
        );

        // Date
        $dateText = $match->match_date
            ? Carbon::parse($match->match_date)->format('d M Y')
            : '';
        $this->addText(
            $canvas,
            $dateText,
            $width / 2,
            990,
            20,
            '#666666',
            'Montserrat-Medium.ttf',
            'center'
        );

        // Award icon/emoji if set
        if ($award->icon) {
            $this->addText(
                $canvas,
                $award->icon,
                $width / 2,
                $height - 100,
                60,
                '#FFD700',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Remarks if any
        if ($matchAward->remarks) {
            $this->addText(
                $canvas,
                $matchAward->remarks,
                $width / 2,
                1100,
                18,
                '#AAAAAA',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        $filename = $this->generateFilename('award-' . $matchAward->id);
        $path = $this->saveImage($canvas, $filename);

        // Update award with poster path
        $matchAward->update(['poster_image' => $path]);

        return $path;
    }

    /**
     * Draw placeholder circle
     */
    protected function drawPlaceholderCircle(\GdImage $canvas, int $centerX, int $centerY, int $diameter, string $color): void
    {
        $rgb = $this->hexToRgb($color);
        $fillColor = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);
        imagefilledellipse($canvas, $centerX, $centerY, $diameter, $diameter, $fillColor);
    }

    /**
     * Generate champion/runner-up poster
     */
    public function generateChampionPoster($tournament, string $type = 'champion'): string
    {
        $width = 1080;
        $height = 1350;

        $settings = $tournament->settings;
        $team = $type === 'champion' ? $tournament->champion : $tournament->runnerUp;

        if (!$team) {
            throw new \InvalidArgumentException("No {$type} team set for tournament");
        }

        $canvas = $this->createCanvas($width, $height, '#0a0a0a');

        // Title based on type
        $title = $type === 'champion' ? 'CHAMPIONS' : 'RUNNERS UP';
        $titleColor = $type === 'champion' ? '#FFD700' : '#C0C0C0';

        $this->addText(
            $canvas,
            $title,
            $width / 2,
            80,
            56,
            $titleColor,
            'Montserrat-Bold.ttf',
            'center'
        );

        // Tournament logo
        if ($settings?->logo) {
            $this->addCircularImage($canvas, $settings->logo, $width / 2, 200, 120);
        }

        // Tournament name
        $this->addText(
            $canvas,
            $tournament->name,
            $width / 2,
            300,
            28,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Team logo (large)
        if ($team->team_logo) {
            $this->addCircularImage($canvas, $team->team_logo, $width / 2, 550, 300);
        }

        // Team name
        $this->addText(
            $canvas,
            $team->name,
            $width / 2,
            780,
            48,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Trophy emoji
        $trophy = $type === 'champion' ? '🏆' : '🥈';
        $this->addText(
            $canvas,
            $trophy,
            $width / 2,
            900,
            80,
            '#FFD700',
            'Montserrat-Medium.ttf',
            'center'
        );

        // Year/Season
        $year = $tournament->start_date?->format('Y') ?? date('Y');
        $this->addText(
            $canvas,
            "Season {$year}",
            $width / 2,
            $height - 100,
            24,
            '#888888',
            'Montserrat-Medium.ttf',
            'center'
        );

        $filename = $this->generateFilename("{$type}-{$tournament->id}");
        return $this->saveImage($canvas, $filename);
    }
}
