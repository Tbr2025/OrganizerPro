<?php

namespace App\Services\Poster;

use App\Models\Tournament;
use App\Models\TournamentGroup;

class PointTablePosterService extends PosterGeneratorService
{
    protected string $outputDirectory = 'point_table_posters';

    /**
     * Generate point table poster for a group
     */
    public function generate($group): string
    {
        if (!$group instanceof TournamentGroup) {
            throw new \InvalidArgumentException('Expected TournamentGroup model');
        }

        $tournament = $group->tournament;
        $settings = $tournament->settings;
        $entries = $group->pointTableEntries()->with('team')->ranked()->get();

        $width = 1080;
        $rowHeight = 80;
        $headerHeight = 250;
        $tableHeaderHeight = 60;
        $height = $headerHeight + $tableHeaderHeight + ($entries->count() * $rowHeight) + 100;

        // Create canvas
        $canvas = $this->createCanvas($width, $height, '#0f172a');

        // Tournament logo
        if ($settings?->logo) {
            $this->addCircularImage($canvas, $settings->logo, 80, 80, 100);
        }

        // Tournament name
        $this->addText(
            $canvas,
            $tournament->name,
            $width / 2,
            60,
            32,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Group name
        $this->addText(
            $canvas,
            $group->name . ' - Point Table',
            $width / 2,
            110,
            28,
            '#FFD700',
            'Montserrat-Medium.ttf',
            'center'
        );

        // Table header
        $tableY = $headerHeight;
        $this->drawTableHeader($canvas, $tableY, $width);

        // Table rows
        $currentY = $tableY + $tableHeaderHeight;
        foreach ($entries as $index => $entry) {
            $isQualified = $entry->qualified;
            $bgColor = $index % 2 === 0 ? '#1e293b' : '#334155';

            if ($isQualified) {
                $bgColor = '#064e3b'; // Green tint for qualified teams
            }

            $this->drawTableRow($canvas, $entry, $currentY, $width, $bgColor);
            $currentY += $rowHeight;
        }

        // Legend
        $legendY = $currentY + 30;
        $rgb = $this->hexToRgb('#064e3b');
        $qualifiedColor = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);
        imagefilledrectangle($canvas, 50, $legendY, 70, $legendY + 20, $qualifiedColor);
        $this->addText($canvas, '= Qualified for next round', 80, $legendY + 15, 14, '#AAAAAA', 'Montserrat-Medium.ttf', 'left');

        // Date
        $this->addText(
            $canvas,
            'Updated: ' . now()->format('d M Y, h:i A'),
            $width - 50,
            $height - 30,
            12,
            '#666666',
            'Montserrat-Medium.ttf',
            'right'
        );

        $filename = $this->generateFilename('points-' . $group->id);
        return $this->saveImage($canvas, $filename);
    }

    /**
     * Draw table header
     */
    protected function drawTableHeader(\GdImage $canvas, int $y, int $width): void
    {
        // Header background
        $rgb = $this->hexToRgb('#1e40af');
        $headerBg = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);
        imagefilledrectangle($canvas, 0, $y, $width, $y + 60, $headerBg);

        // Column headers
        $columns = [
            ['text' => '#', 'x' => 40, 'width' => 40],
            ['text' => 'Team', 'x' => 80, 'width' => 300],
            ['text' => 'P', 'x' => 420, 'width' => 60],
            ['text' => 'W', 'x' => 490, 'width' => 60],
            ['text' => 'L', 'x' => 560, 'width' => 60],
            ['text' => 'T', 'x' => 630, 'width' => 60],
            ['text' => 'NRR', 'x' => 720, 'width' => 100],
            ['text' => 'Pts', 'x' => 850, 'width' => 80],
        ];

        foreach ($columns as $col) {
            $this->addText(
                $canvas,
                $col['text'],
                $col['x'],
                $y + 40,
                16,
                '#FFFFFF',
                'Montserrat-Bold.ttf',
                'left'
            );
        }
    }

    /**
     * Draw table row
     */
    protected function drawTableRow(\GdImage $canvas, $entry, int $y, int $width, string $bgColor): void
    {
        // Row background
        $rgb = $this->hexToRgb($bgColor);
        $rowBg = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);
        imagefilledrectangle($canvas, 0, $y, $width, $y + 80, $rowBg);

        // Position
        $this->addText($canvas, (string) $entry->position, 40, $y + 50, 18, '#FFFFFF', 'Montserrat-Bold.ttf', 'left');

        // Team logo and name
        if ($entry->team?->team_logo) {
            $this->addCircularImage($canvas, $entry->team->team_logo, 110, $y + 40, 50);
        }
        $teamName = substr($entry->team?->name ?? 'Unknown', 0, 20);
        $this->addText($canvas, $teamName, 150, $y + 50, 16, '#FFFFFF', 'Montserrat-Medium.ttf', 'left');

        // Stats
        $this->addText($canvas, (string) $entry->matches_played, 420, $y + 50, 16, '#FFFFFF', 'Montserrat-Medium.ttf', 'left');
        $this->addText($canvas, (string) $entry->won, 490, $y + 50, 16, '#4ADE80', 'Montserrat-Medium.ttf', 'left');
        $this->addText($canvas, (string) $entry->lost, 560, $y + 50, 16, '#F87171', 'Montserrat-Medium.ttf', 'left');
        $this->addText($canvas, (string) $entry->tied, 630, $y + 50, 16, '#FBBF24', 'Montserrat-Medium.ttf', 'left');

        // NRR with sign
        $nrr = $entry->net_run_rate;
        $nrrText = ($nrr >= 0 ? '+' : '') . number_format($nrr, 3);
        $nrrColor = $nrr >= 0 ? '#4ADE80' : '#F87171';
        $this->addText($canvas, $nrrText, 720, $y + 50, 16, $nrrColor, 'Montserrat-Medium.ttf', 'left');

        // Points
        $this->addText($canvas, (string) $entry->points, 850, $y + 50, 20, '#FFD700', 'Montserrat-Bold.ttf', 'left');
    }

    /**
     * Generate combined point table for all groups
     */
    public function generateAllGroups(Tournament $tournament): array
    {
        $paths = [];

        foreach ($tournament->groups as $group) {
            $paths[$group->id] = $this->generate($group);
        }

        return $paths;
    }
}
