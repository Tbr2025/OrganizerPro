<?php

namespace App\Services\Poster;

use App\Models\Tournament;

class TournamentFlyerService extends PosterGeneratorService
{
    protected string $outputDirectory = 'tournament_flyers';

    /**
     * Generate tournament flyer with registration links
     */
    public function generate($tournament): string
    {
        if (!$tournament instanceof Tournament) {
            throw new \InvalidArgumentException('Expected Tournament model');
        }

        $settings = $tournament->settings;
        $width = 1080;
        $height = 1920; // Story/portrait format

        // Create canvas with background
        if ($settings?->background_image) {
            $bg = $this->loadBackground($settings->background_image);
            if ($bg) {
                // Resize background to fit canvas
                $canvas = $this->createCanvas($width, $height, '#000000');
                $bgWidth = imagesx($bg);
                $bgHeight = imagesy($bg);

                // Scale to fit width
                $scale = $width / $bgWidth;
                $newHeight = (int) ($bgHeight * $scale);

                imagecopyresampled(
                    $canvas, $bg,
                    0, 0, 0, 0,
                    $width, $newHeight,
                    $bgWidth, $bgHeight
                );
                imagedestroy($bg);
            } else {
                $canvas = $this->createCanvas($width, $height, $settings->primary_color ?? '#1a365d');
            }
        } else {
            $canvas = $this->createCanvas($width, $height, '#1a365d');
        }

        // Add semi-transparent overlay for readability
        $overlay = imagecolorallocatealpha($canvas, 0, 0, 0, 80);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $overlay);

        // Tournament logo at top
        if ($settings?->logo) {
            $this->addCircularImage($canvas, $settings->logo, $width / 2, 150, 200);
        }

        // Tournament name
        $this->addText(
            $canvas,
            $tournament->name,
            $width / 2,
            320,
            48,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Tagline/description
        if ($settings?->description) {
            $description = substr($settings->description, 0, 100);
            $this->addText(
                $canvas,
                $description,
                $width / 2,
                400,
                24,
                '#CCCCCC',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Dates
        $dateText = '';
        if ($tournament->start_date && $tournament->end_date) {
            $dateText = $tournament->start_date->format('d M') . ' - ' . $tournament->end_date->format('d M Y');
        } elseif ($tournament->start_date) {
            $dateText = 'Starting ' . $tournament->start_date->format('d M Y');
        }
        if ($dateText) {
            $this->addText(
                $canvas,
                $dateText,
                $width / 2,
                480,
                28,
                '#FFD700',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Location
        if ($tournament->location) {
            $this->addText(
                $canvas,
                $tournament->location,
                $width / 2,
                540,
                24,
                '#AAAAAA',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Registration section
        $regSectionY = 700;

        $this->addText(
            $canvas,
            'REGISTRATIONS OPEN',
            $width / 2,
            $regSectionY,
            36,
            '#4ADE80',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Registration deadline
        if ($settings?->registration_deadline) {
            $deadlineText = 'Deadline: ' . $settings->registration_deadline->format('d M Y');
            $this->addText(
                $canvas,
                $deadlineText,
                $width / 2,
                $regSectionY + 50,
                22,
                '#FF6B6B',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Player registration
        if ($settings?->player_registration_open) {
            $this->drawButton(
                $canvas,
                'PLAYER REGISTRATION',
                $width / 2,
                $regSectionY + 150,
                400,
                60,
                '#2563eb'
            );
        }

        // Team registration
        if ($settings?->team_registration_open) {
            $this->drawButton(
                $canvas,
                'TEAM REGISTRATION',
                $width / 2,
                $regSectionY + 230,
                400,
                60,
                '#059669'
            );
        }

        // Tournament format info
        $formatY = 1100;
        if ($settings) {
            $formatText = match ($settings->format) {
                'group_knockout' => 'Groups + Knockouts',
                'league' => 'League Format',
                'knockout' => 'Knockout Tournament',
                default => '',
            };
            if ($formatText) {
                $this->addText($canvas, $formatText, $width / 2, $formatY, 24, '#FFFFFF', 'Montserrat-Medium.ttf', 'center');
            }

            $oversText = $settings->overs_per_match . ' Overs';
            $this->addText($canvas, $oversText, $width / 2, $formatY + 40, 22, '#AAAAAA', 'Montserrat-Medium.ttf', 'center');
        }

        // QR code placeholder area
        $qrY = 1350;
        $this->addText(
            $canvas,
            'SCAN TO REGISTER',
            $width / 2,
            $qrY,
            24,
            '#FFFFFF',
            'Montserrat-Medium.ttf',
            'center'
        );

        // Draw QR placeholder box
        $qrSize = 200;
        $qrX = ($width - $qrSize) / 2;
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, (int) $qrX, $qrY + 30, (int) ($qrX + $qrSize), $qrY + 30 + $qrSize, $white);

        // Contact info
        if ($settings?->contact_email || $settings?->contact_phone) {
            $contactY = $height - 150;
            $this->addText($canvas, 'CONTACT', $width / 2, $contactY, 18, '#888888', 'Montserrat-Medium.ttf', 'center');

            if ($settings->contact_email) {
                $this->addText($canvas, $settings->contact_email, $width / 2, $contactY + 30, 18, '#AAAAAA', 'Montserrat-Medium.ttf', 'center');
            }
            if ($settings->contact_phone) {
                $this->addText($canvas, $settings->contact_phone, $width / 2, $contactY + 55, 18, '#AAAAAA', 'Montserrat-Medium.ttf', 'center');
            }
        }

        // Registration URL at bottom
        $url = route('public.tournament.show', $tournament->slug);
        $this->addText(
            $canvas,
            $url,
            $width / 2,
            $height - 50,
            16,
            '#666666',
            'Montserrat-Medium.ttf',
            'center'
        );

        $filename = $this->generateFilename('flyer-' . $tournament->id);
        $path = $this->saveImage($canvas, $filename);

        // Update tournament settings with flyer path
        if ($settings) {
            $settings->update(['flyer_image' => $path]);
        }

        return $path;
    }

    /**
     * Draw a button-like element
     */
    protected function drawButton(\GdImage $canvas, string $text, int $centerX, int $centerY, int $width, int $height, string $bgColor): void
    {
        $rgb = $this->hexToRgb($bgColor);
        $color = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);

        $x1 = $centerX - $width / 2;
        $y1 = $centerY - $height / 2;
        $x2 = $centerX + $width / 2;
        $y2 = $centerY + $height / 2;

        // Draw rounded rectangle (approximate with filled rectangle)
        imagefilledrectangle($canvas, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $color);

        // Add text
        $this->addText(
            $canvas,
            $text,
            $centerX,
            $centerY + 8,
            20,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );
    }
}
