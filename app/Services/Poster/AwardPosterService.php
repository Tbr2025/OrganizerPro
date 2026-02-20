<?php

namespace App\Services\Poster;

use App\Models\MatchAward;
use App\Models\TournamentTemplate;
use App\Models\Player;
use Carbon\Carbon;

class AwardPosterService extends PosterGeneratorService
{
    protected string $outputDirectory = 'award_posters';
    protected TemplateRenderService $templateRenderService;

    public function __construct()
    {
        $this->templateRenderService = new TemplateRenderService();
    }

    /**
     * Generate award poster - uses Tournament Template if available, otherwise fallback
     */
    public function generate($matchAward): string
    {
        if (!$matchAward instanceof MatchAward) {
            throw new \InvalidArgumentException('Expected MatchAward model');
        }

        $match = $matchAward->match;
        $tournament = $match->tournament;

        // Try to use Tournament Template (award_poster type)
        $template = $tournament->templates()
            ->where('type', TournamentTemplate::TYPE_AWARD_POSTER)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        if ($template && $template->background_image) {
            return $this->generateFromTemplate($matchAward, $template);
        }

        // Fallback to hardcoded rendering
        return $this->generateLegacy($matchAward);
    }

    /**
     * Generate award poster using Tournament Template
     */
    protected function generateFromTemplate(MatchAward $matchAward, TournamentTemplate $template): string
    {
        $match = $matchAward->match;
        $player = $matchAward->player;
        $award = $matchAward->tournamentAward;
        $tournament = $match->tournament;
        $settings = $tournament->settings;

        // Prepare data for template placeholders
        $data = [
            // Award info
            'award_name' => $award->name ?? 'Award',
            'award_icon' => $award->icon ?? '',

            // Player info
            'player_name' => $player->jersey_name ?? $player->name ?? 'Player',
            'player_image' => $player->image_path ?? null,
            'jersey_name' => $player->jersey_name ?? strtoupper(substr($player->name ?? 'P', 0, 10)),
            'jersey_number' => $player->jersey_number ?? '',

            // Team info
            'team_name' => $player->actualTeam?->name ?? '',
            'team_logo' => $player->actualTeam?->team_logo ?? null,

            // Tournament info
            'tournament_name' => $tournament->name ?? 'Tournament',
            'tournament_logo' => $settings?->logo ?? null,

            // Match info
            'match_info' => ($match->teamA?->name ?? 'Team A') . ' vs ' . ($match->teamB?->name ?? 'Team B'),
            'team_a_name' => $match->teamA?->name ?? 'Team A',
            'team_b_name' => $match->teamB?->name ?? 'Team B',
            'match_date' => $match->match_date ? Carbon::parse($match->match_date)->format('d M Y') : '',
            'venue' => $match->venue ?? $match->location ?? '',

            // Achievement/remarks
            'achievement_text' => $matchAward->remarks ?? '',
            'remarks' => $matchAward->remarks ?? '',
        ];

        // Render using template
        $filename = 'award-' . $matchAward->id . '-' . time() . '.png';
        $path = $this->templateRenderService->renderAndSave($template, $data, $filename);

        // Update award with poster path
        $matchAward->update(['poster_image' => $path]);

        return $path;
    }

    /**
     * Legacy hardcoded poster generation (fallback)
     */
    protected function generateLegacy(MatchAward $matchAward): string
    {
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
     * Generate champion/runner-up poster - uses Tournament Template if available
     */
    public function generateChampionPoster($tournament, string $type = 'champion'): string
    {
        // Try to use Tournament Template (champions_poster type)
        $template = $tournament->templates()
            ->where('type', TournamentTemplate::TYPE_CHAMPIONS_POSTER)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        if ($template && $template->background_image) {
            return $this->generateChampionFromTemplate($tournament, $template, $type);
        }

        // Fallback to hardcoded rendering
        return $this->generateChampionLegacy($tournament, $type);
    }

    /**
     * Generate champion poster using Tournament Template
     */
    protected function generateChampionFromTemplate($tournament, TournamentTemplate $template, string $type): string
    {
        $settings = $tournament->settings;
        $team = $type === 'champion' ? $tournament->champion : $tournament->runnerUp;

        if (!$team) {
            throw new \InvalidArgumentException("No {$type} team set for tournament");
        }

        $data = [
            'title' => $type === 'champion' ? 'CHAMPIONS' : 'RUNNERS UP',
            'team_name' => $team->name,
            'team_logo' => $team->team_logo ?? null,
            'tournament_name' => $tournament->name,
            'tournament_logo' => $settings?->logo ?? null,
            'season' => 'Season ' . ($tournament->start_date?->format('Y') ?? date('Y')),
            'year' => $tournament->start_date?->format('Y') ?? date('Y'),
        ];

        $filename = "{$type}-{$tournament->id}-" . time() . '.png';
        return $this->templateRenderService->renderAndSave($template, $data, $filename);
    }

    /**
     * Legacy champion poster generation (fallback)
     */
    protected function generateChampionLegacy($tournament, string $type): string
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
