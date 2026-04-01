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
        $teamCount = $entries->count();
        $headerHeight = 180;
        $tableHeaderHeight = 48;
        $rowHeight = max(52, min(70, (int) (600 / max($teamCount, 1))));
        $legendHeight = 50;
        $bottomPadding = 50;
        $height = $headerHeight + $tableHeaderHeight + ($teamCount * $rowHeight) + $legendHeight + $bottomPadding;

        // Create canvas
        $canvas = $this->createCanvas($width, $height, '#0f172a');

        // Tournament logo
        if ($settings?->logo) {
            $this->addCircularImage($canvas, $settings->logo, 80, 65, 80);
        }

        // Tournament name
        $this->addText($canvas, $tournament->name, $width / 2, 50, 28, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');

        // Group name
        $this->addText($canvas, $group->name . ' - Point Table', $width / 2, 95, 22, '#FFD700', 'Montserrat-Medium.ttf', 'center');

        // Subtle separator line below header
        $sepY = $headerHeight - 20;
        $lineColor = imagecolorallocatealpha($canvas, 255, 215, 0, 90);
        imageline($canvas, 40, $sepY, $width - 40, $sepY, $lineColor);

        // --- Table header ---
        $tableY = $headerHeight;
        $headerBg = $this->hexToRgb('#1e40af');
        $headerColor = imagecolorallocate($canvas, $headerBg['r'], $headerBg['g'], $headerBg['b']);
        imagefilledrectangle($canvas, 30, $tableY, $width - 30, $tableY + $tableHeaderHeight, $headerColor);

        // Accent line under header
        $accentRgb = $this->hexToRgb('#FFD700');
        $accentColor = imagecolorallocate($canvas, $accentRgb['r'], $accentRgb['g'], $accentRgb['b']);
        imagefilledrectangle($canvas, 30, $tableY + $tableHeaderHeight - 2, $width - 30, $tableY + $tableHeaderHeight, $accentColor);

        // Column positions (center-aligned for stats)
        $cols = [
            'pos' => 60,
            'logoCenter' => 110,
            'teamName' => 140,
            'played' => 460,
            'won' => 540,
            'lost' => 620,
            'tied' => 700,
            'nrr' => 790,
            'pts' => 970,
        ];
        $statW = 60;
        $nrrW = 120;
        $ptsW = 80;

        $hFontSize = 14;
        $hTextY = $tableY + (int) ($tableHeaderHeight * 0.65);

        $this->addText($canvas, '#', $cols['pos'], $hTextY, $hFontSize, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
        $this->addText($canvas, 'Team', $cols['teamName'], $hTextY, $hFontSize, '#FFFFFF', 'Montserrat-Bold.ttf', 'left');
        $this->addText($canvas, 'P', $cols['played'] + $statW / 2, $hTextY, $hFontSize, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
        $this->addText($canvas, 'W', $cols['won'] + $statW / 2, $hTextY, $hFontSize, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
        $this->addText($canvas, 'L', $cols['lost'] + $statW / 2, $hTextY, $hFontSize, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
        $this->addText($canvas, 'T', $cols['tied'] + $statW / 2, $hTextY, $hFontSize, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
        $this->addText($canvas, 'NRR', $cols['nrr'] + $nrrW / 2, $hTextY, $hFontSize, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
        $this->addText($canvas, 'Pts', $cols['pts'] + $ptsW / 2, $hTextY, $hFontSize, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');

        // --- Table rows ---
        $currentY = $tableY + $tableHeaderHeight;
        $bodyFontSize = min(15, (int) ($rowHeight * 0.30));
        $logoDiameter = min((int) ($rowHeight * 0.55), 34);
        $dividerColor = imagecolorallocatealpha($canvas, 255, 255, 255, 100);

        foreach ($entries as $index => $entry) {
            $isQualified = $entry->qualified;
            $bgHex = $isQualified ? '#064e3b' : ($index % 2 === 0 ? '#1e293b' : '#293548');

            $rgb = $this->hexToRgb($bgHex);
            $rowBg = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);
            imagefilledrectangle($canvas, 30, $currentY, $width - 30, $currentY + $rowHeight, $rowBg);

            // Subtle divider line
            if ($index < $teamCount - 1) {
                imageline($canvas, 40, $currentY + $rowHeight - 1, $width - 40, $currentY + $rowHeight - 1, $dividerColor);
            }

            $textY = $currentY + (int) ($rowHeight * 0.62);

            // Position — centered
            $this->addText($canvas, (string) $entry->position, $cols['pos'], $textY, $bodyFontSize, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');

            // Team logo
            $teamNameX = $cols['teamName'];
            if ($entry->team?->team_logo) {
                $logoCenterY = $currentY + (int) ($rowHeight / 2);
                $this->addCircularImage($canvas, $entry->team->team_logo, $cols['logoCenter'], $logoCenterY, $logoDiameter);
                $teamNameX = $cols['logoCenter'] + (int) ($logoDiameter / 2) + 10;
            }

            // Team name
            $teamName = mb_substr($entry->team?->name ?? 'Unknown', 0, 22);
            $this->addText($canvas, $teamName, $teamNameX, $textY, $bodyFontSize, '#FFFFFF', 'Montserrat-Medium.ttf', 'left');

            // Stats — center-aligned
            $this->addText($canvas, (string) $entry->matches_played, $cols['played'] + $statW / 2, $textY, $bodyFontSize, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
            $this->addText($canvas, (string) $entry->won, $cols['won'] + $statW / 2, $textY, $bodyFontSize, '#4ADE80', 'Montserrat-Bold.ttf', 'center');
            $this->addText($canvas, (string) $entry->lost, $cols['lost'] + $statW / 2, $textY, $bodyFontSize, '#F87171', 'Montserrat-Bold.ttf', 'center');
            $this->addText($canvas, (string) $entry->tied, $cols['tied'] + $statW / 2, $textY, $bodyFontSize, '#FBBF24', 'Montserrat-Bold.ttf', 'center');

            // NRR — centered
            $nrr = $entry->net_run_rate;
            $nrrText = ($nrr >= 0 ? '+' : '') . number_format($nrr, 3);
            $nrrColor = $nrr >= 0 ? '#4ADE80' : '#F87171';
            $this->addText($canvas, $nrrText, $cols['nrr'] + $nrrW / 2, $textY, $bodyFontSize, $nrrColor, 'Montserrat-Medium.ttf', 'center');

            // Points — bold, larger
            $ptsFontSize = (int) ($bodyFontSize * 1.25);
            $this->addText($canvas, (string) $entry->points, $cols['pts'] + $ptsW / 2, $textY, $ptsFontSize, '#FFD700', 'Montserrat-Bold.ttf', 'center');

            $currentY += $rowHeight;
        }

        // Legend
        $legendY = $currentY + 15;
        $qualifiedRgb = $this->hexToRgb('#064e3b');
        $qualifiedColor = imagecolorallocate($canvas, $qualifiedRgb['r'], $qualifiedRgb['g'], $qualifiedRgb['b']);
        imagefilledrectangle($canvas, 40, $legendY, 58, $legendY + 16, $qualifiedColor);
        $this->addText($canvas, '= Qualified for next round', 68, $legendY + 13, 12, '#AAAAAA', 'Montserrat-Medium.ttf', 'left');

        // Updated timestamp
        $this->addText(
            $canvas,
            'Updated: ' . now()->format('d M Y, h:i A'),
            $width - 40,
            $height - 20,
            11,
            '#666666',
            'Montserrat-Medium.ttf',
            'right'
        );

        $filename = $this->generateFilename('points-' . $group->id);
        return $this->saveImage($canvas, $filename);
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
