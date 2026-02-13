<?php

namespace App\Services\Poster;

use App\Models\Matches;
use App\Models\MatchResult;
use App\Models\TournamentTemplate;

class MatchSummaryPosterService extends PosterGeneratorService
{
    protected string $outputDirectory = 'match_summaries';
    protected int $defaultWidth = 1080;
    protected int $defaultHeight = 1350;

    /**
     * Generate match summary poster
     */
    public function generate($match): string
    {
        $tournament = $match->tournament;
        $result = $match->result;
        $settings = $tournament->settings;

        // Get appropriate template based on match stage
        $template = $this->getTemplateForMatch($match);

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
        $overlayColor = imagecolorallocatealpha($overlay, 0, 0, 0, 70);
        imagefill($overlay, 0, 0, $overlayColor);
        imagecopy($image, $overlay, 0, 0, 0, 0, $this->defaultWidth, $this->defaultHeight);
        imagedestroy($overlay);

        // Tournament logo
        if ($settings->logo) {
            $this->addCircularImage($image, $settings->logo, 540, 80, 100);
        }

        // Tournament name
        $this->addText(
            $image,
            $tournament->name,
            540, 160,
            24,
            '#FFFFFF',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Match stage badge
        $stageColor = $this->getStageColor($match->stage);
        $this->addText(
            $image,
            strtoupper($match->stage_display),
            540, 210,
            18,
            $stageColor,
            'Montserrat-Bold.ttf',
            'center'
        );

        // "MATCH RESULT" header
        $this->addText(
            $image,
            'MATCH RESULT',
            540, 280,
            32,
            $settings->secondary_color ?? '#FFD700',
            'Montserrat-Bold.ttf',
            'center'
        );

        // Team A section (left)
        $teamA = $match->teamA;
        if ($teamA) {
            // Logo
            if ($teamA->team_logo) {
                $this->addCircularImage($image, $teamA->team_logo, 200, 420, 140);
            } else {
                $this->drawPlaceholderCircle($image, 200, 420, 70, '#333333');
            }

            // Name
            $this->addText(
                $image,
                $teamA->short_name ?? $teamA->name,
                200, 530,
                22,
                '#FFFFFF',
                'Montserrat-Bold.ttf',
                'center'
            );

            // Score
            $scoreA = $result ? $this->formatScore($result->team_a_score, $result->team_a_wickets, $result->team_a_overs) : '-';
            $this->addText(
                $image,
                $scoreA,
                200, 590,
                36,
                $match->winner_team_id === $teamA->id ? '#00FF00' : '#FFFFFF',
                'Montserrat-Bold.ttf',
                'center'
            );
        }

        // VS text
        $this->addText($image, 'VS', 540, 450, 24, '#666666', 'Montserrat-Bold.ttf', 'center');

        // Team B section (right)
        $teamB = $match->teamB;
        if ($teamB) {
            // Logo
            if ($teamB->team_logo) {
                $this->addCircularImage($image, $teamB->team_logo, 880, 420, 140);
            } else {
                $this->drawPlaceholderCircle($image, 880, 420, 70, '#333333');
            }

            // Name
            $this->addText(
                $image,
                $teamB->short_name ?? $teamB->name,
                880, 530,
                22,
                '#FFFFFF',
                'Montserrat-Bold.ttf',
                'center'
            );

            // Score
            $scoreB = $result ? $this->formatScore($result->team_b_score, $result->team_b_wickets, $result->team_b_overs) : '-';
            $this->addText(
                $image,
                $scoreB,
                880, 590,
                36,
                $match->winner_team_id === $teamB->id ? '#00FF00' : '#FFFFFF',
                'Montserrat-Bold.ttf',
                'center'
            );
        }

        // Result summary
        if ($result && $result->result_summary) {
            $this->addText(
                $image,
                $result->result_summary,
                540, 680,
                20,
                $settings->secondary_color ?? '#FFD700',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Divider line
        $lineColor = imagecolorallocate($image, 100, 100, 100);
        imageline($image, 100, 740, 980, 740, $lineColor);

        // Awards section
        $yPos = 800;
        $awards = $match->matchAwards()->with('player', 'tournamentAward')->get();

        if ($awards->count() > 0) {
            $this->addText($image, 'AWARDS', 540, $yPos - 30, 18, '#666666', 'Montserrat-Bold.ttf', 'center');

            foreach ($awards->take(3) as $index => $award) {
                $xPos = 200 + ($index * 340);

                // Award icon/emoji
                if ($award->tournamentAward && $award->tournamentAward->icon) {
                    $this->addText($image, $award->tournamentAward->icon, $xPos, $yPos, 32, '#FFFFFF', 'Montserrat-Bold.ttf', 'center');
                }

                // Award name
                $awardName = $award->tournamentAward ? $award->tournamentAward->name : 'Award';
                $this->addText($image, $awardName, $xPos, $yPos + 50, 14, '#CCCCCC', 'Montserrat-Medium.ttf', 'center');

                // Player image
                if ($award->player && $award->player->profile_image) {
                    $this->addCircularImage($image, $award->player->profile_image, $xPos, $yPos + 130, 80);
                } else {
                    $this->drawPlaceholderCircle($image, $xPos, $yPos + 130, 40, '#333333');
                }

                // Player name
                if ($award->player) {
                    $this->addText($image, $award->player->name, $xPos, $yPos + 200, 14, '#FFFFFF', 'Montserrat-Medium.ttf', 'center');
                }
            }
        }

        // Match details at bottom
        $yBottom = 1250;

        // Date
        if ($match->match_date) {
            $this->addText(
                $image,
                $match->match_date->format('D, M d, Y'),
                540, $yBottom,
                14,
                '#999999',
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
                '#666666',
                'Montserrat-Medium.ttf',
                'center'
            );
        }

        // Save and return path
        $filename = $this->generateFilename('summary-' . $match->id);
        return $this->saveImage($image, $filename);
    }

    /**
     * Get appropriate template for match stage
     */
    protected function getTemplateForMatch(Matches $match): ?TournamentTemplate
    {
        $settings = $match->tournament->settings;

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

        return $match->tournament->getTemplate(TournamentTemplate::TYPE_MATCH_SUMMARY);
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
            $score .= ' (' . number_format((float) $overs, 1) . ')';
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
            default => '#FFFFFF',
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
}
