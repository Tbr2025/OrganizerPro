<?php

namespace App\Services\Poster;

use App\Models\Matches;
use App\Models\MatchResult;
use App\Models\TournamentTemplate;
use Carbon\Carbon;

class MatchSummaryPosterService extends PosterGeneratorService
{
    protected string $outputDirectory = 'match_summaries';
    protected int $defaultWidth = 1080;
    protected int $defaultHeight = 1350;
    protected ?TemplateRenderService $templateRenderService = null;

    /**
     * Generate match summary poster - uses Tournament Template if available
     */
    public function generate($match, string $templateType = 'classic'): string
    {
        $tournament = $match->tournament;

        // Try to use Tournament Template (match_summary type)
        $template = $tournament?->templates()
            ->where('type', TournamentTemplate::TYPE_MATCH_SUMMARY)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        if ($template && $template->background_image) {
            return $this->generateFromTemplate($match, $template);
        }

        // Fallback to hardcoded rendering
        return $this->generateLegacy($match, $templateType);
    }

    /**
     * Generate summary poster using Tournament Template
     */
    protected function generateFromTemplate(Matches $match, TournamentTemplate $template): string
    {
        if (!$this->templateRenderService) {
            $this->templateRenderService = new TemplateRenderService();
        }

        $result = $match->result;
        $tournament = $match->tournament;
        $settings = $tournament?->settings;

        // Prepare data for template placeholders
        $data = [
            // Tournament info
            'tournament_name' => $tournament->name ?? 'Tournament',
            'tournament_logo' => $settings?->logo ?? null,

            // Team A info
            'team_a_name' => $match->teamA?->name ?? 'Team A',
            'team_a_short_name' => $match->teamA?->short_name ?? strtoupper(substr($match->teamA?->name ?? 'TMA', 0, 3)),
            'team_a_logo' => $match->teamA?->team_logo ?? null,
            'team_a_score' => $this->formatScore($result?->team_a_score, $result?->team_a_wickets, null),

            // Team B info
            'team_b_name' => $match->teamB?->name ?? 'Team B',
            'team_b_short_name' => $match->teamB?->short_name ?? strtoupper(substr($match->teamB?->name ?? 'TMB', 0, 3)),
            'team_b_logo' => $match->teamB?->team_logo ?? null,
            'team_b_score' => $this->formatScore($result?->team_b_score, $result?->team_b_wickets, null),

            // Result info
            'result_summary' => $result?->result_summary ?? '',
            'winner_name' => $match->winner?->name ?? '',

            // Match info
            'match_date' => $match->match_date ? Carbon::parse($match->match_date)->format('d M Y') : '',
            'match_stage' => $match->stage_display ?? $match->stage ?? 'Group Stage',
            'venue' => $match->venue ?? $match->ground?->name ?? '',
            'match_number' => $match->match_number ?? '',
        ];

        // Add Man of the Match if available
        $momAward = $match->matchAwards()->whereHas('tournamentAward', function($q) {
            $q->where('name', 'like', '%Man of the Match%')
              ->orWhere('name', 'like', '%Player of the Match%');
        })->with('player')->first();

        if ($momAward && $momAward->player) {
            $data['man_of_the_match_name'] = $momAward->player->name ?? '';
            $data['man_of_the_match_image'] = $momAward->player->image_path ?? null;
        }

        // Render using template
        $filename = 'summary-' . $match->id . '-' . time() . '.png';
        return $this->templateRenderService->renderAndSave($template, $data, $filename);
    }

    /**
     * Legacy hardcoded summary poster generation (fallback)
     */
    protected function generateLegacy(Matches $match, string $templateType = 'classic'): string
    {
        $tournament = $match->tournament;
        $result = $match->result;
        $settings = $tournament?->settings;

        // Get appropriate template based on match stage (for background only)
        $template = $this->getTemplateForMatch($match);

        // Get colors based on template type
        $colors = $this->getTemplateColors($templateType, $settings);
        $primaryColor = $colors['primary'];
        $secondaryColor = $colors['secondary'];
        $bgColor = $colors['background'];

        // Create canvas with gradient background based on template
        $image = $this->createGradientCanvas($this->defaultWidth, $this->defaultHeight, $bgColor);

        // Load background if available
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

        // Add semi-transparent overlay for better text readability
        $this->addOverlay($image, 0, 0, $this->defaultWidth, $this->defaultHeight, 0, 0, 0, 60);

        // Add decorative elements
        $this->addDecorativeElements($image, $settings);

        // Tournament logo at top
        if ($settings?->logo) {
            $this->addCircularImage($image, $settings->logo, 540, 100, 120);
            // Add glow effect around logo
            $this->addCircleBorder($image, 540, 100, 62, '#FFD700', 3);
        }

        // Tournament name
        $this->addText(
            $image,
            strtoupper($tournament->name),
            540, 190,
            22,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Match stage badge with background
        $stageColor = $this->getStageColor($match->stage);
        $this->addRoundedRect($image, 440, 210, 200, 36, 18, $stageColor, 0.8);
        $this->addText(
            $image,
            strtoupper($match->stage_display),
            540, 235,
            14,
            '#000000',
            'Montserrat-Bold.ttf',
            'center'
        );

        // "MATCH RESULT" header with decorative line
        $this->addText(
            $image,
            'MATCH RESULT',
            540, 310,
            36,
            $settings?->secondary_color ?? '#FFD700',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Decorative lines under header
        $lineColor = $this->hexToRgb($settings?->secondary_color ?? '#FFD700');
        $lineColorAllocated = imagecolorallocate($image, $lineColor['r'], $lineColor['g'], $lineColor['b']);
        imagesetthickness($image, 2);
        imageline($image, 340, 330, 480, 330, $lineColorAllocated);
        imageline($image, 600, 330, 740, 330, $lineColorAllocated);

        // Team A section (left side)
        $teamA = $match->teamA;
        $teamAWinner = $match->winner_team_id === $match->team_a_id;
        $this->renderTeamSection($image, $teamA, $result, 'A', 270, 500, $teamAWinner, $settings);

        // VS badge in center
        $this->addVSBadge($image, 540, 500);

        // Team B section (right side)
        $teamB = $match->teamB;
        $teamBWinner = $match->winner_team_id === $match->team_b_id;
        $this->renderTeamSection($image, $teamB, $result, 'B', 810, 500, $teamBWinner, $settings);

        // Result summary with background
        if ($result && $result->result_summary) {
            $this->addRoundedRect($image, 90, 700, 900, 50, 25, '#FFD700', 0.15);
            $this->addText(
                $image,
                $result->result_summary,
                540, 733,
                18,
                $settings?->secondary_color ?? '#FFD700',
                'Montserrat-Bold.ttf',
                'center'
            );
        }

        // Divider line
        $this->addGradientLine($image, 100, 780, 980, 780, '#333333', '#666666');

        // Awards section
        $yPos = 850;
        $awards = $match->matchAwards()->with('player', 'tournamentAward')->get();

        if ($awards->count() > 0) {
            // Awards header
            $this->addText($image, 'MATCH AWARDS', 540, $yPos - 30, 16, '#888888', 'Montserrat-Bold.ttf', 'center');

            // Calculate positions for awards (centered)
            $awardCount = min($awards->count(), 3);
            $startX = 540 - (($awardCount - 1) * 170);

            foreach ($awards->take(3) as $index => $award) {
                $xPos = $startX + ($index * 340);

                // Award background circle
                $this->addFilledCircle($image, $xPos, $yPos + 80, 70, '#1a1a2e', 0.5);

                // Player image
                if ($award->player && $award->player->image_path) {
                    $this->addCircularImage($image, $award->player->image_path, $xPos, $yPos + 80, 120);
                } else {
                    $this->drawPlaceholderCircle($image, $xPos, $yPos + 80, 60, '#333333');
                }

                // Award icon (emoji as text)
                if ($award->tournamentAward && $award->tournamentAward->icon) {
                    $this->addText($image, $award->tournamentAward->icon, $xPos, $yPos + 10, 28, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
                }

                // Award name badge
                $awardName = $award->tournamentAward ? $award->tournamentAward->name : 'Award';
                $this->addText($image, $awardName, $xPos, $yPos + 170, 12, '#FFD700', 'Montserrat-Bold.ttf', 'center');

                // Player name
                if ($award->player) {
                    $this->addText($image, $award->player->name, $xPos, $yPos + 195, 14, '#FFFFFF', 'Montserrat-Medium.ttf', 'center');
                }
            }
        }

        // Match details at bottom
        $yBottom = 1250;

        // Date icon and text
        if ($match->match_date) {
            $this->addText(
                $image,
                $match->match_date->format('D, M d, Y'),
                540, $yBottom,
                14,
                '#AAAAAA',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Venue
        $venue = $match->ground?->name ?? $match->venue;
        if ($venue) {
            $this->addText(
                $image,
                $venue,
                540, $yBottom + 25,
                12,
                '#777777',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Match number / ID
        if ($match->match_number) {
            $this->addText(
                $image,
                'Match #' . $match->match_number,
                540, $yBottom + 50,
                10,
                '#555555',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Bottom branding line
        $this->addGradientLine($image, 0, $this->defaultHeight - 5, $this->defaultWidth, $this->defaultHeight - 5, $settings?->primary_color ?? '#1a1a2e', $settings?->secondary_color ?? '#FFD700');

        // Save and return path
        $filename = $this->generateFilename('summary-' . $match->id);
        return $this->saveImage($image, $filename);
    }

    /**
     * Create canvas with gradient background
     */
    protected function createGradientCanvas(int $width, int $height, string $baseColor): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);

        $rgb1 = $this->hexToRgb($baseColor);
        $rgb2 = $this->hexToRgb($this->darkenColor($baseColor, 30));

        // Create vertical gradient
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = (int) ($rgb1['r'] + ($rgb2['r'] - $rgb1['r']) * $ratio);
            $g = (int) ($rgb1['g'] + ($rgb2['g'] - $rgb1['g']) * $ratio);
            $b = (int) ($rgb1['b'] + ($rgb2['b'] - $rgb1['b']) * $ratio);
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $color);
        }

        return $image;
    }

    /**
     * Add semi-transparent overlay
     */
    protected function addOverlay(\GdImage $image, int $x, int $y, int $width, int $height, int $r, int $g, int $b, int $alpha): void
    {
        $overlay = imagecreatetruecolor($width, $height);
        $color = imagecolorallocatealpha($overlay, $r, $g, $b, $alpha);
        imagefill($overlay, 0, 0, $color);
        imagecopymerge($image, $overlay, $x, $y, 0, 0, $width, $height, 100 - ($alpha * 100 / 127));
        imagedestroy($overlay);
    }

    /**
     * Add decorative elements to poster
     */
    protected function addDecorativeElements(\GdImage $image, $settings): void
    {
        $accentColor = $this->hexToRgb($settings?->secondary_color ?? '#FFD700');
        $color = imagecolorallocatealpha($image, $accentColor['r'], $accentColor['g'], $accentColor['b'], 110);

        // Corner decorations
        imagefilledellipse($image, 0, 0, 300, 300, $color);
        imagefilledellipse($image, $this->defaultWidth, 0, 300, 300, $color);
        imagefilledellipse($image, 0, $this->defaultHeight, 200, 200, $color);
        imagefilledellipse($image, $this->defaultWidth, $this->defaultHeight, 200, 200, $color);
    }

    /**
     * Render team section with logo, name, and score
     */
    protected function renderTeamSection(\GdImage $image, $team, $result, string $teamKey, int $centerX, int $centerY, bool $isWinner, $settings): void
    {
        if (!$team) return;

        // Winner glow effect
        if ($isWinner) {
            $this->addFilledCircle($image, $centerX, $centerY, 90, '#00FF00', 0.15);
        }

        // Team logo with border
        if ($team->team_logo) {
            $this->addCircularImage($image, $team->team_logo, $centerX, $centerY, 140);
            $borderColor = $isWinner ? '#00FF00' : '#FFFFFF';
            $this->addCircleBorder($image, $centerX, $centerY, 72, $borderColor, 3);
        } else {
            $this->drawPlaceholderCircle($image, $centerX, $centerY, 70, '#333333');
            // Add team initials
            $initials = substr($team->name ?? 'T', 0, 2);
            $this->addText($image, strtoupper($initials), $centerX, $centerY + 10, 32, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
        }

        // Team name
        $teamName = $team->short_name ?? $team->name;
        if (strlen($teamName) > 12) {
            $teamName = substr($teamName, 0, 12) . '...';
        }
        $this->addText(
            $image,
            strtoupper($teamName),
            $centerX, $centerY + 100,
            18,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Score
        $scoreField = $teamKey === 'A' ? 'team_a_score' : 'team_b_score';
        $wicketsField = $teamKey === 'A' ? 'team_a_wickets' : 'team_b_wickets';
        $oversField = $teamKey === 'A' ? 'team_a_overs' : 'team_b_overs';

        $score = $result ? $this->formatScore($result->$scoreField, $result->$wicketsField, $result->$oversField) : '-';
        $scoreColor = $isWinner ? '#00FF00' : '#FFFFFF';

        $this->addText(
            $image,
            $score,
            $centerX, $centerY + 150,
            36,
            $scoreColor,
            'Montserrat-Bold.ttf',
            'center'
        );

        // Winner badge
        if ($isWinner) {
            $this->addRoundedRect($image, $centerX - 40, $centerY + 165, 80, 24, 12, '#00FF00', 1);
            $this->addText($image, 'WINNER', $centerX, $centerY + 182, 10, '#000000', 'Montserrat-Bold.ttf', 'center');
        }
    }

    /**
     * Add VS badge in center
     */
    protected function addVSBadge(\GdImage $image, int $x, int $y): void
    {
        // Outer circle
        $this->addFilledCircle($image, $x, $y, 45, '#FFD700', 1);
        // Inner circle
        $this->addFilledCircle($image, $x, $y, 38, '#FF8C00', 1);
        // VS text
        $this->addText($image, 'VS', $x, $y + 8, 20, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
    }

    /**
     * Add a filled circle with transparency
     */
    protected function addFilledCircle(\GdImage $image, int $centerX, int $centerY, int $radius, string $color, float $opacity = 1): void
    {
        $rgb = $this->hexToRgb($color);
        $alpha = (int) (127 * (1 - $opacity));
        $fillColor = imagecolorallocatealpha($image, $rgb['r'], $rgb['g'], $rgb['b'], $alpha);
        imagefilledellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $fillColor);
    }

    /**
     * Add circle border
     */
    protected function addCircleBorder(\GdImage $image, int $centerX, int $centerY, int $radius, string $color, int $thickness = 2): void
    {
        $rgb = $this->hexToRgb($color);
        $borderColor = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        imagesetthickness($image, $thickness);
        imagearc($image, $centerX, $centerY, $radius * 2, $radius * 2, 0, 360, $borderColor);
    }

    /**
     * Add rounded rectangle with transparency
     */
    protected function addRoundedRect(\GdImage $image, int $x, int $y, int $width, int $height, int $radius, string $color, float $opacity = 1): void
    {
        $rgb = $this->hexToRgb($color);
        $alpha = (int) (127 * (1 - $opacity));
        $fillColor = imagecolorallocatealpha($image, $rgb['r'], $rgb['g'], $rgb['b'], $alpha);

        // Draw rounded rectangle
        imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius, $y + $height, $fillColor);
        imagefilledrectangle($image, $x, $y + $radius, $x + $width, $y + $height - $radius, $fillColor);
        imagefilledellipse($image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $fillColor);
        imagefilledellipse($image, $x + $width - $radius, $y + $radius, $radius * 2, $radius * 2, $fillColor);
        imagefilledellipse($image, $x + $radius, $y + $height - $radius, $radius * 2, $radius * 2, $fillColor);
        imagefilledellipse($image, $x + $width - $radius, $y + $height - $radius, $radius * 2, $radius * 2, $fillColor);
    }

    /**
     * Add gradient line
     */
    protected function addGradientLine(\GdImage $image, int $x1, int $y1, int $x2, int $y2, string $color1, string $color2): void
    {
        $rgb1 = $this->hexToRgb($color1);
        $rgb2 = $this->hexToRgb($color2);
        $steps = abs($x2 - $x1);

        for ($i = 0; $i <= $steps; $i++) {
            $ratio = $steps > 0 ? $i / $steps : 0;
            $r = (int) ($rgb1['r'] + ($rgb2['r'] - $rgb1['r']) * $ratio);
            $g = (int) ($rgb1['g'] + ($rgb2['g'] - $rgb1['g']) * $ratio);
            $b = (int) ($rgb1['b'] + ($rgb2['b'] - $rgb1['b']) * $ratio);
            $color = imagecolorallocate($image, $r, $g, $b);
            imagesetpixel($image, $x1 + $i, $y1, $color);
        }
    }

    /**
     * Darken a color by a percentage
     */
    protected function darkenColor(string $hex, int $percent): string
    {
        $rgb = $this->hexToRgb($hex);
        $factor = 1 - ($percent / 100);
        $r = max(0, (int) ($rgb['r'] * $factor));
        $g = max(0, (int) ($rgb['g'] * $factor));
        $b = max(0, (int) ($rgb['b'] * $factor));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Get appropriate template for match stage
     */
    protected function getTemplateForMatch(Matches $match): ?TournamentTemplate
    {
        $settings = $match->tournament?->settings;

        if (!$settings) {
            return null;
        }

        // Use special template for finals
        if ($match->isFinal() && $settings->final_template_id) {
            return TournamentTemplate::find($settings->final_template_id);
        }

        // Use special template for semi-finals
        if ($match->isSemiFinal() && $settings->semi_final_template_id) {
            return TournamentTemplate::find($settings->semi_final_template_id);
        }

        // Use default summary template
        if ($settings->default_summary_template_id) {
            return TournamentTemplate::find($settings->default_summary_template_id);
        }

        return $match->tournament?->getTemplate(TournamentTemplate::TYPE_MATCH_SUMMARY);
    }

    /**
     * Format score display
     */
    protected function formatScore(?int $runs, ?int $wickets, $overs): string
    {
        if ($runs === null) {
            return '-';
        }

        $score = (string) $runs;

        if ($wickets !== null) {
            $score .= '/' . $wickets;
        }

        if ($overs !== null) {
            $score .= "\n(" . number_format((float) $overs, 1) . ' ov)';
        }

        return $score;
    }

    /**
     * Get color for match stage
     */
    protected function getStageColor(string $stage): string
    {
        return match ($stage) {
            'final' => '#FFD700',
            'semi_final' => '#C0C0C0',
            'quarter_final' => '#CD7F32',
            'third_place' => '#CD7F32',
            default => '#4A90D9',
        };
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

    /**
     * Get colors based on template type
     */
    protected function getTemplateColors(string $templateType, $settings): array
    {
        $defaultPrimary = $settings?->primary_color ?? '#1a1a2e';
        $defaultSecondary = $settings?->secondary_color ?? '#fbbf24';

        return match ($templateType) {
            'modern' => [
                'primary' => '#1e293b',
                'secondary' => $defaultSecondary,
                'background' => '#0f172a',
            ],
            'minimal' => [
                'primary' => '#ffffff',
                'secondary' => $defaultSecondary,
                'background' => '#ffffff',
            ],
            'gradient' => [
                'primary' => '#667eea',
                'secondary' => '#f093fb',
                'background' => '#667eea',
            ],
            'dark' => [
                'primary' => '#000000',
                'secondary' => $defaultSecondary,
                'background' => '#000000',
            ],
            default => [ // classic
                'primary' => $defaultPrimary,
                'secondary' => $defaultSecondary,
                'background' => $defaultPrimary,
            ],
        };
    }
}
