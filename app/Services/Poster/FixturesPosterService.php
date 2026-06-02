<?php

namespace App\Services\Poster;

use App\Models\Tournament;

class FixturesPosterService extends PosterGeneratorService
{
    protected string $outputDirectory = 'fixtures_posters';

    protected array $themes = [
        'dark' => [
            'background' => '#0f172a',
            'text' => '#FFFFFF',
            'accent' => '#FFD700',
            'muted' => '#94a3b8',
            'row_even' => '#1e293b',
            'row_odd' => '#293548',
            'header_bg' => '#1e40af',
            'vs_bg' => '#FFD700',
            'vs_text' => '#0f172a',
            'badge_bg' => '#334155',
            'badge_text' => '#94a3b8',
        ],
        'light' => [
            'background' => '#f8fafc',
            'text' => '#1e293b',
            'accent' => '#4f46e5',
            'muted' => '#64748b',
            'row_even' => '#ffffff',
            'row_odd' => '#f1f5f9',
            'header_bg' => '#4f46e5',
            'vs_bg' => '#4f46e5',
            'vs_text' => '#ffffff',
            'badge_bg' => '#e2e8f0',
            'badge_text' => '#64748b',
        ],
        'green' => [
            'background' => '#064e3b',
            'text' => '#FFFFFF',
            'accent' => '#FFD700',
            'muted' => '#a7f3d0',
            'row_even' => '#065f46',
            'row_odd' => '#047857',
            'header_bg' => '#047857',
            'vs_bg' => '#FFD700',
            'vs_text' => '#064e3b',
            'badge_bg' => '#065f46',
            'badge_text' => '#a7f3d0',
        ],
    ];

    /**
     * Generate fixtures poster
     */
    public function generate($tournament, int $matchCount = 5, string $theme = 'dark'): string
    {
        if (!$tournament instanceof Tournament) {
            throw new \InvalidArgumentException('Expected Tournament model');
        }

        $colors = $this->themes[$theme] ?? $this->themes['dark'];
        $settings = $tournament->settings;

        $matches = $tournament->matches()
            ->where('status', 'upcoming')
            ->where('is_cancelled', false)
            ->with(['teamA', 'teamB', 'ground'])
            ->orderBy('match_date')
            ->orderBy('start_time')
            ->limit($matchCount)
            ->get();

        $width = 1080;
        $headerHeight = 160;
        $rowHeight = 120;
        $footerHeight = 50;
        $matchesCount = $matches->count();

        if ($matchesCount === 0) {
            // Empty state: fixed height
            $height = $headerHeight + 200 + $footerHeight;
        } else {
            $height = $headerHeight + ($matchesCount * $rowHeight) + $footerHeight;
        }

        $canvas = $this->createCanvas($width, $height, $colors['background']);

        // --- Header ---
        $this->renderHeader($canvas, $tournament, $settings, $colors, $width);

        // --- Match Rows ---
        if ($matchesCount === 0) {
            $this->renderEmptyState($canvas, $colors, $width, $headerHeight);
        } else {
            $currentY = $headerHeight;
            foreach ($matches as $index => $match) {
                $this->renderMatchRow($canvas, $match, $index, $currentY, $colors, $width);
                $currentY += $rowHeight;
            }
        }

        // --- Footer ---
        $this->addText(
            $canvas,
            'Updated: ' . now()->format('d M Y, h:i A'),
            $width / 2,
            $height - 20,
            11,
            $colors['muted'],
            'Montserrat-Medium.ttf',
            'center'
        );

        $filename = $this->generateFilename('fixtures-' . $tournament->id);
        return $this->saveImage($canvas, $filename);
    }

    protected function renderHeader(\GdImage $canvas, Tournament $tournament, $settings, array $colors, int $width): void
    {
        // Header background bar
        $hdrRgb = $this->hexToRgb($colors['header_bg']);
        $hdrColor = imagecolorallocate($canvas, $hdrRgb['r'], $hdrRgb['g'], $hdrRgb['b']);
        imagefilledrectangle($canvas, 0, 0, $width, 160, $hdrColor);

        // Tournament logo (circular, left side)
        $logoX = 80;
        if ($settings?->logo) {
            $this->addCircularImage($canvas, $settings->logo, $logoX, 80, 80);
        }

        // Tournament name
        $nameX = $settings?->logo ? 140 : 40;
        $tournamentName = mb_strimwidth($tournament->name, 0, 40, '...');
        $this->addText($canvas, $tournamentName, $nameX, 55, 26, '#FFFFFF', 'Montserrat-Bold.ttf', 'left');

        // Subtitle
        $this->addText($canvas, 'UPCOMING FIXTURES', $nameX, 95, 18, $colors['accent'], 'Montserrat-Bold.ttf', 'left');

        // Accent line below header
        $accentRgb = $this->hexToRgb($colors['accent']);
        $accentColor = imagecolorallocate($canvas, $accentRgb['r'], $accentRgb['g'], $accentRgb['b']);
        imagefilledrectangle($canvas, 0, 156, $width, 160, $accentColor);
    }

    protected function renderMatchRow(\GdImage $canvas, $match, int $index, int $y, array $colors, int $width): void
    {
        $rowHeight = 120;

        // Alternating row background
        $bgHex = $index % 2 === 0 ? $colors['row_even'] : $colors['row_odd'];
        $bgRgb = $this->hexToRgb($bgHex);
        $bgColor = imagecolorallocate($canvas, $bgRgb['r'], $bgRgb['g'], $bgRgb['b']);
        imagefilledrectangle($canvas, 0, $y, $width, $y + $rowHeight, $bgColor);

        // Subtle divider line at bottom
        $divRgb = $this->hexToRgb($colors['muted']);
        $divColor = imagecolorallocatealpha($canvas, $divRgb['r'], $divRgb['g'], $divRgb['b'], 100);
        imageline($canvas, 30, $y + $rowHeight - 1, $width - 30, $y + $rowHeight - 1, $divColor);

        // Match number badge (top-right corner)
        $matchNum = $match->match_number ?? ($index + 1);
        $badgeText = 'Match ' . $matchNum;
        $badgeRgb = $this->hexToRgb($colors['badge_bg']);
        $badgeColor = imagecolorallocate($canvas, $badgeRgb['r'], $badgeRgb['g'], $badgeRgb['b']);
        imagefilledrectangle($canvas, $width - 150, $y + 10, $width - 20, $y + 32, $badgeColor);
        $this->addText($canvas, $badgeText, $width - 85, $y + 21, 10, $colors['badge_text'], 'Montserrat-Medium.ttf', 'center');

        // Layout: Team A logo + name ... VS ... Team B name + logo
        $logoDiameter = 50;
        $teamALogoX = 70;
        $teamBLogoX = $width - 70;
        $centerY = $y + 48;

        // Team A logo
        if ($match->teamA?->team_logo) {
            $this->addCircularImage($canvas, $match->teamA->team_logo, $teamALogoX, $centerY, $logoDiameter);
        }

        // Team A name
        $teamAName = mb_strimwidth($match->teamA?->short_name ?? $match->teamA?->name ?? 'TBD', 0, 18, '..');
        $this->addText($canvas, $teamAName, $teamALogoX + 40, $centerY, 16, $colors['text'], 'Montserrat-Bold.ttf', 'left');

        // VS badge (centered)
        $vsCenterX = $width / 2;
        $vsRgb = $this->hexToRgb($colors['vs_bg']);
        $vsColor = imagecolorallocate($canvas, $vsRgb['r'], $vsRgb['g'], $vsRgb['b']);
        $vsSize = 28;
        imagefilledellipse($canvas, (int)$vsCenterX, $centerY, $vsSize * 2, $vsSize, $vsColor);
        $this->addText($canvas, 'VS', (int)$vsCenterX, $centerY, 12, $colors['vs_text'], 'Montserrat-Bold.ttf', 'center');

        // Team B name (right-aligned, before logo)
        $teamBName = mb_strimwidth($match->teamB?->short_name ?? $match->teamB?->name ?? 'TBD', 0, 18, '..');
        $this->addText($canvas, $teamBName, $teamBLogoX - 40, $centerY, 16, $colors['text'], 'Montserrat-Bold.ttf', 'right');

        // Team B logo
        if ($match->teamB?->team_logo) {
            $this->addCircularImage($canvas, $match->teamB->team_logo, $teamBLogoX, $centerY, $logoDiameter);
        }

        // Date/time + Venue (below teams, centered)
        $infoY = $y + 88;
        $dateStr = $match->match_date ? $match->match_date->format('d M Y') : '';
        $timeStr = $match->start_time ? \Carbon\Carbon::parse($match->start_time)->format('h:i A') : '';
        $dateTime = trim($dateStr . ($timeStr ? '  |  ' . $timeStr : ''));
        $venue = $match->ground?->name ?? $match->venue ?? '';

        if ($dateTime) {
            $this->addText($canvas, $dateTime, (int)$vsCenterX, $infoY, 12, $colors['muted'], 'Montserrat-Medium.ttf', 'center');
        }
        if ($venue) {
            $venueY = $dateTime ? $infoY + 18 : $infoY;
            $this->addText($canvas, $venue, (int)$vsCenterX, $venueY, 11, $colors['muted'], 'Montserrat-Medium.ttf', 'center');
        }
    }

    protected function renderEmptyState(\GdImage $canvas, array $colors, int $width, int $startY): void
    {
        $centerY = $startY + 100;
        $this->addText($canvas, 'No upcoming fixtures', $width / 2, $centerY, 22, $colors['muted'], 'Montserrat-Medium.ttf', 'center');
        $this->addText($canvas, 'Check back later for schedule updates', $width / 2, $centerY + 35, 14, $colors['muted'], 'Montserrat-Medium.ttf', 'center');
    }
}
