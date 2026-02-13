<?php

namespace App\Services\Poster;

use App\Models\Tournament;
use App\Models\ActualTeam;
use App\Models\TournamentTemplate;

class ChampionsPosterService extends PosterGeneratorService
{
    protected string $outputDirectory = 'champions_posters';
    protected int $defaultWidth = 1080;
    protected int $defaultHeight = 1350;

    public const TYPE_CHAMPION = 'champion';
    public const TYPE_RUNNER_UP = 'runner_up';

    /**
     * Generate champions/runners-up poster
     */
    public function generate($tournament): string
    {
        return $this->generateChampionPoster($tournament);
    }

    /**
     * Generate champion team poster
     */
    public function generateChampionPoster(Tournament $tournament): string
    {
        return $this->generateTeamPoster($tournament, self::TYPE_CHAMPION);
    }

    /**
     * Generate runner-up team poster
     */
    public function generateRunnerUpPoster(Tournament $tournament): string
    {
        return $this->generateTeamPoster($tournament, self::TYPE_RUNNER_UP);
    }

    /**
     * Generate poster for a team (champion or runner-up)
     */
    protected function generateTeamPoster(Tournament $tournament, string $type): string
    {
        $settings = $tournament->settings;
        $team = $type === self::TYPE_CHAMPION
            ? $tournament->champion
            : $tournament->runnerUp;

        if (!$team) {
            throw new \InvalidArgumentException("Tournament has no {$type} team assigned");
        }

        // Get template
        $template = $tournament->getTemplate(TournamentTemplate::TYPE_CHAMPIONS_POSTER);

        // Create canvas
        $bgColor = $settings->primary_color ?? '#1a1a2e';
        $image = $this->createCanvas($this->defaultWidth, $this->defaultHeight, $bgColor);

        // Load background
        if ($template && $template->background_image) {
            $bgImage = $this->loadBackground($template->background_image);
            if ($bgImage) {
                imagecopyresampled(
                    $image, $bgImage,
                    0, 0, 0, 0,
                    $this->defaultWidth, $this->defaultHeight,
                    imagesx($bgImage), imagesy($bgImage)
                );
                imagedestroy($bgImage);
            }
        }

        // Semi-transparent overlay
        $overlay = imagecreatetruecolor($this->defaultWidth, $this->defaultHeight);
        $overlayColor = imagecolorallocatealpha($overlay, 0, 0, 0, 60);
        imagefill($overlay, 0, 0, $overlayColor);
        imagecopy($image, $overlay, 0, 0, 0, 0, $this->defaultWidth, $this->defaultHeight);
        imagedestroy($overlay);

        // Tournament logo
        if ($settings->logo) {
            $this->addCircularImage($image, $settings->logo, 540, 100, 120);
        }

        // Tournament name
        $this->addText(
            $image,
            $tournament->name,
            540, 200,
            28,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Title based on type
        $title = $type === self::TYPE_CHAMPION ? 'CHAMPIONS' : 'RUNNERS UP';
        $titleColor = $type === self::TYPE_CHAMPION ? '#FFD700' : '#C0C0C0';

        $this->addText(
            $image,
            $title,
            540, 320,
            56,
            $titleColor,
            'Montserrat-Bold.ttf',
            'center'
        );

        // Trophy emoji
        $trophyEmoji = $type === self::TYPE_CHAMPION ? '🏆' : '🥈';
        $this->addText(
            $image,
            $trophyEmoji,
            540, 420,
            64,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Team logo (large, centered)
        if ($team->team_logo) {
            $this->addCircularImage($image, $team->team_logo, 540, 650, 280);
        } else {
            $this->drawPlaceholderCircle($image, 540, 650, 140, '#333333');
            $this->addText(
                $image,
                substr($team->name, 0, 2),
                540, 670,
                72,
                '#FFFFFF',
                'Montserrat-Bold.ttf',
                'center'
            );
        }

        // Team name
        $this->addText(
            $image,
            strtoupper($team->name),
            540, 880,
            36,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Team short name
        if ($team->short_name) {
            $this->addText(
                $image,
                $team->short_name,
                540, 940,
                24,
                $titleColor,
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Congratulations text
        $this->addText(
            $image,
            'CONGRATULATIONS!',
            540, 1040,
            28,
            $settings->secondary_color ?? '#FFD700',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Season year
        $year = $tournament->end_date ? $tournament->end_date->format('Y') : date('Y');
        $this->addText(
            $image,
            "Season {$year}",
            540, 1120,
            20,
            '#999999',
            'Montserrat-Medium.ttf',
            'center'
        );

        // Tournament dates
        if ($tournament->start_date && $tournament->end_date) {
            $dates = $tournament->start_date->format('M d') . ' - ' . $tournament->end_date->format('M d, Y');
            $this->addText(
                $image,
                $dates,
                540, 1160,
                16,
                '#666666',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Location
        if ($tournament->location) {
            $this->addText(
                $image,
                $tournament->location,
                540, 1200,
                14,
                '#555555',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Save and return path
        $suffix = $type === self::TYPE_CHAMPION ? 'champion' : 'runner-up';
        $filename = $this->generateFilename("{$suffix}-{$tournament->id}");
        return $this->saveImage($image, $filename);
    }

    /**
     * Generate both champion and runner-up posters
     */
    public function generateBothPosters(Tournament $tournament): array
    {
        $posters = [];

        if ($tournament->champion) {
            $posters['champion'] = $this->generateChampionPoster($tournament);
        }

        if ($tournament->runnerUp) {
            $posters['runner_up'] = $this->generateRunnerUpPoster($tournament);
        }

        return $posters;
    }

    /**
     * Draw a placeholder circle
     */
    protected function drawPlaceholderCircle(\GdImage $image, int $centerX, int $centerY, int $radius, string $color): void
    {
        $rgb = $this->hexToRgb($color);
        $fillColor = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        imagefilledellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $fillColor);
    }
}
