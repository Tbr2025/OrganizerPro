<?php

namespace App\Services\Poster;

use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentTemplate;

class WelcomeCardPosterService extends PosterGeneratorService
{
    protected string $outputDirectory = 'welcome_cards';
    protected int $defaultWidth = 1080;
    protected int $defaultHeight = 1080;

    /**
     * Generate welcome card for a player registration
     */
    public function generate($registration): string
    {
        if ($registration instanceof TournamentRegistration) {
            return $this->generateFromRegistration($registration);
        }

        if ($registration instanceof Player) {
            throw new \InvalidArgumentException('Use generateFromRegistration with TournamentRegistration');
        }

        throw new \InvalidArgumentException('Invalid model type');
    }

    /**
     * Generate welcome card from tournament registration
     */
    public function generateFromRegistration(TournamentRegistration $registration): string
    {
        $tournament = $registration->tournament;
        $player = $registration->player;
        $settings = $tournament->settings;

        if (!$player) {
            throw new \InvalidArgumentException('Registration must have a player');
        }

        // Get template or use default
        $template = $tournament->getTemplate(TournamentTemplate::TYPE_WELCOME_CARD);

        // Create canvas with tournament branding
        $bgColor = $settings->primary_color ?? '#1a1a2e';
        $image = $this->createCanvas($this->defaultWidth, $this->defaultHeight, $bgColor);

        // Load background image if available
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
        } elseif ($settings->background_image) {
            $bgImage = $this->loadBackground($settings->background_image);
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

        // Add semi-transparent overlay
        $overlay = imagecreatetruecolor($this->defaultWidth, $this->defaultHeight);
        $overlayColor = imagecolorallocatealpha($overlay, 0, 0, 0, 80);
        imagefill($overlay, 0, 0, $overlayColor);
        imagecopy($image, $overlay, 0, 0, 0, 0, $this->defaultWidth, $this->defaultHeight);
        imagedestroy($overlay);

        // Tournament logo (top center)
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

        // "WELCOME" text
        $this->addText(
            $image,
            'WELCOME',
            540, 300,
            48,
            $settings->secondary_color ?? '#FFD700',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Player image (circular, center)
        if ($player->profile_image) {
            $this->addCircularImage($image, $player->profile_image, 540, 480, 250);
        } else {
            // Draw placeholder circle
            $this->drawPlaceholderCircle($image, 540, 480, 125, '#333333');
            $this->addText($image, $player->name[0] ?? '?', 540, 500, 80, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
        }

        // Player name
        $this->addText(
            $image,
            strtoupper($player->name),
            540, 680,
            36,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Jersey name and number
        $jerseyText = '';
        if ($player->jersey_name) {
            $jerseyText = $player->jersey_name;
        }
        if ($player->jersey_number) {
            $jerseyText .= ($jerseyText ? ' | #' : '#') . $player->jersey_number;
        }
        if ($jerseyText) {
            $this->addText(
                $image,
                $jerseyText,
                540, 740,
                24,
                $settings->secondary_color ?? '#FFD700',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Player role/type
        if ($player->playerType) {
            $this->addText(
                $image,
                $player->playerType->name,
                540, 790,
                20,
                '#CCCCCC',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Batting and bowling style
        $styles = [];
        if ($player->battingProfile) {
            $styles[] = $player->battingProfile->name;
        }
        if ($player->bowlingProfile) {
            $styles[] = $player->bowlingProfile->name;
        }
        if (!empty($styles)) {
            $this->addText(
                $image,
                implode(' | ', $styles),
                540, 830,
                16,
                '#999999',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Team info (if assigned)
        if ($registration->actualTeam) {
            $team = $registration->actualTeam;

            // Team logo
            if ($team->team_logo) {
                $this->addCircularImage($image, $team->team_logo, 540, 920, 60);
            }

            // Team name
            $this->addText(
                $image,
                $team->name,
                540, 980,
                18,
                '#FFFFFF',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Tournament season/year at bottom
        $year = $tournament->start_date ? $tournament->start_date->format('Y') : date('Y');
        $this->addText(
            $image,
            "Season {$year}",
            540, 1050,
            14,
            '#666666',
            'Montserrat-Medium.ttf',
            'center'
        );

        // Save and return path
        $filename = $this->generateFilename('welcome-card-' . $player->id);
        return $this->saveImage($image, $filename);
    }

    /**
     * Generate welcome card with custom data (for preview)
     */
    public function generatePreview(Tournament $tournament, array $data = []): string
    {
        $settings = $tournament->settings;
        $template = $tournament->getTemplate(TournamentTemplate::TYPE_WELCOME_CARD);

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

        // Sample data
        $playerName = $data['player_name'] ?? 'John Doe';
        $jerseyNumber = $data['jersey_number'] ?? '10';
        $jerseyName = $data['jersey_name'] ?? 'JOHNNY';
        $playerType = $data['player_type'] ?? 'All-Rounder';

        // Add semi-transparent overlay
        $overlay = imagecreatetruecolor($this->defaultWidth, $this->defaultHeight);
        $overlayColor = imagecolorallocatealpha($overlay, 0, 0, 0, 80);
        imagefill($overlay, 0, 0, $overlayColor);
        imagecopy($image, $overlay, 0, 0, 0, 0, $this->defaultWidth, $this->defaultHeight);
        imagedestroy($overlay);

        // Tournament logo
        if ($settings->logo) {
            $this->addCircularImage($image, $settings->logo, 540, 100, 120);
        }

        // Tournament name
        $this->addText($image, $tournament->name, 540, 200, 28, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');

        // Welcome text
        $this->addText($image, 'WELCOME', 540, 300, 48, $settings->secondary_color ?? '#FFD700', 'Montserrat-Bold.ttf', 'center');

        // Placeholder circle
        $this->drawPlaceholderCircle($image, 540, 480, 125, '#333333');
        $this->addText($image, $playerName[0], 540, 500, 80, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');

        // Player details
        $this->addText($image, strtoupper($playerName), 540, 680, 36, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
        $this->addText($image, "{$jerseyName} | #{$jerseyNumber}", 540, 740, 24, $settings->secondary_color ?? '#FFD700', 'Montserrat-Medium.ttf', 'center');
        $this->addText($image, $playerType, 540, 790, 20, '#CCCCCC', 'Montserrat-Medium.ttf', 'center');

        $filename = $this->generateFilename('welcome-card-preview');
        return $this->saveImage($image, $filename);
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
