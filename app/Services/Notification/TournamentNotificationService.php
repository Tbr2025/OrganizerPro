<?php

namespace App\Services\Notification;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\Matches;
use App\Models\MatchAward;
use App\Models\NotificationLog;
use App\Models\Player;
use App\Services\Poster\WelcomeCardPosterService;
use App\Services\Poster\MatchSummaryPosterService;
use App\Services\Poster\MatchPosterService;
use App\Services\Poster\AwardPosterService;
use App\Services\Poster\TemplateRenderService;
use App\Services\Poster\TournamentFlyerService;
use App\Mail\PlayerWelcomeMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TournamentNotificationService
{
    protected WelcomeCardPosterService $welcomeCardService;
    protected MatchSummaryPosterService $matchSummaryService;
    protected MatchPosterService $matchPosterService;
    protected AwardPosterService $awardPosterService;

    public function __construct(
        WelcomeCardPosterService $welcomeCardService,
        MatchSummaryPosterService $matchSummaryService,
        MatchPosterService $matchPosterService,
        AwardPosterService $awardPosterService
    ) {
        $this->welcomeCardService = $welcomeCardService;
        $this->matchSummaryService = $matchSummaryService;
        $this->matchPosterService = $matchPosterService;
        $this->awardPosterService = $awardPosterService;
    }

    /**
     * Generate the welcome card poster for a registration (without sending email).
     * Returns the relative storage path, or null if no template/player.
     */
    public function generateWelcomeCard(TournamentRegistration $registration): ?string
    {
        if (!$registration->isPlayerRegistration() || !$registration->player) {
            return null;
        }

        $tournament = $registration->tournament;
        $player = $registration->player;
        $settings = $tournament->settings;

        $template = $tournament->getTemplate(\App\Models\TournamentTemplate::TYPE_WELCOME_CARD);
        if (!$template) {
            return null;
        }

        $data = [
            'player_name' => $player->name,
            'jersey_name' => $player->jersey_name ?: $player->name,
            'jersey_number' => (string) ($player->jersey_number ?? ''),
            'player_type' => $player->playerType?->type ?? $player->playerType?->name ?? '',
            'batting_style' => $player->battingProfile?->style ?? $player->battingProfile?->name ?? '',
            'bowling_style' => $player->bowlingProfile?->style ?? $player->bowlingProfile?->name ?? '',
            'team_name' => $player->playing_team_name_ref ?: ($player->actualTeam?->name ?? $player->team?->name ?? ''),
            'team_logo' => $player->playing_team_name_ref ? '' : ($player->actualTeam?->team_logo ?? $player->team?->logo ?? ''),
            'tournament_name' => $tournament->name,
            'tournament_logo' => $settings->logo ?? $tournament->logo ?? '',
            'player_image' => $player->image_path ?? '',
            'playing_team_name' => $player->playing_team_name_ref ?: ($player->actualTeam?->name ?? ''),
            'playing_team_logo' => $player->playing_team_name_ref ? '' : ($player->actualTeam?->team_logo ?? ''),
        ];

        return app(\App\Services\Poster\TemplateRenderService::class)
            ->renderAndSave($template, $data, TemplateRenderService::posterFilename('welcome-' . \Illuminate\Support\Str::slug($player->name)));
    }

    /**
     * Send welcome card to a registration
     */
    public function sendWelcomeCard(TournamentRegistration $registration, bool $manual = false, bool $force = false): bool
    {
        if (!$registration->isPlayerRegistration() || !$registration->player) {
            return false;
        }

        $tournament = $registration->tournament;
        $player = $registration->player;
        $settings = $tournament->settings;

        // Check if auto-send is enabled (unless manual override)
        if (!$manual && $settings && !$settings->shouldAutoSendWelcomeCards()) {
            return false;
        }

        // Check if already sent (skip this guard on an explicit resend/force).
        if (!$force && $registration->welcome_card_sent) {
            return false;
        }

        $email = $player->email;
        if (!$email) {
            return false;
        }

        // Use the tournament's welcome_card template. If none exists, do NOT
        // generate a card — skip and log (the admin is told in the UI).
        $template = $tournament->getTemplate(\App\Models\TournamentTemplate::TYPE_WELCOME_CARD);
        if (!$template) {
            Log::info('No welcome_card template found — skipping welcome card.', [
                'registration_id' => $registration->id,
                'tournament_id' => $tournament->id,
            ]);

            return false;
        }

        try {
            // Render the welcome card from the editor template + player data.
            $data = [
                'player_name' => $player->name,
                'jersey_name' => $player->jersey_name ?: $player->name,
                'jersey_number' => (string) ($player->jersey_number ?? ''),
                'player_type' => $player->playerType?->type ?? $player->playerType?->name ?? '',
                'batting_style' => $player->battingProfile?->style ?? $player->battingProfile?->name ?? '',
                'bowling_style' => $player->bowlingProfile?->style ?? $player->bowlingProfile?->name ?? '',
                'team_name' => $player->playing_team_name_ref ?: ($player->actualTeam?->name ?? $player->team?->name ?? ''),
                'team_logo' => $player->playing_team_name_ref ? '' : ($player->actualTeam?->team_logo ?? $player->team?->logo ?? ''),
                'tournament_name' => $tournament->name,
                'tournament_logo' => $settings->logo ?? $tournament->logo ?? '',
                'player_image' => $player->image_path ?? '',
                'playing_team_name' => $player->playing_team_name_ref ?: ($player->actualTeam?->name ?? ''),
                'playing_team_logo' => $player->playing_team_name_ref ? '' : ($player->actualTeam?->team_logo ?? ''),
            ];

            $posterPath = app(\App\Services\Poster\TemplateRenderService::class)
                ->renderAndSave($template, $data, TemplateRenderService::posterFilename('welcome-' . \Illuminate\Support\Str::slug($player->name)));

            $log = NotificationLog::log(
                $tournament,
                $registration,
                NotificationLog::TYPE_WELCOME_CARD,
                NotificationLog::CHANNEL_EMAIL,
                $email,
                $posterPath
            );

            Mail::to($email)->send(new PlayerWelcomeMail($player, storage_path('app/public/' . $posterPath), $tournament));

            $registration->markWelcomeCardSent();
            $log->markAsSent();

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send welcome card', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);

            if (isset($log)) {
                $log->markAsFailed($e->getMessage());
            }

            return false;
        }
    }

    /**
     * Send tournament flyer to a registration
     */
    public function sendTournamentFlyer(TournamentRegistration $registration): bool
    {
        $tournament = $registration->tournament;
        $settings = $tournament->settings;

        // Check if auto-send is enabled
        if ($settings && !$settings->shouldAutoSendFlyer()) {
            return false;
        }

        // Check if already sent
        if ($registration->flyer_sent) {
            return false;
        }

        $email = $registration->email;
        if (!$email) {
            return false;
        }

        try {
            // Get or generate flyer
            $flyerPath = $settings->flyer_image;

            if (!$flyerPath && class_exists(TournamentFlyerService::class)) {
                $flyerService = app(TournamentFlyerService::class);
                $flyerPath = $flyerService->generate($tournament);
            }

            if (!$flyerPath) {
                return false;
            }

            // Log notification
            $log = NotificationLog::log(
                $tournament,
                $registration,
                NotificationLog::TYPE_FLYER,
                NotificationLog::CHANNEL_EMAIL,
                $email,
                $flyerPath
            );

            // TODO: Create and send flyer email
            // For now, just mark as sent
            $registration->markFlyerSent();
            $log->markAsSent();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send tournament flyer', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);

            if (isset($log)) {
                $log->markAsFailed($e->getMessage());
            }

            return false;
        }
    }

    /**
     * Send match poster to team members
     */
    public function sendMatchPoster(Matches $match, ?array $recipients = null): int
    {
        $tournament = $match->tournament;
        $sentCount = 0;

        try {
            // Generate poster if not exists
            if (!$match->poster_image) {
                $posterPath = $this->matchPosterService->generate($match);
                $match->update(['poster_image' => $posterPath]);
            }

            // Get recipients
            if ($recipients === null) {
                $recipients = $match->getAllTeamEmails();
            }

            foreach ($recipients as $email) {
                $log = NotificationLog::log(
                    $tournament,
                    $match,
                    NotificationLog::TYPE_MATCH_POSTER,
                    NotificationLog::CHANNEL_EMAIL,
                    $email,
                    $match->poster_image
                );

                // TODO: Send actual email with poster
                $log->markAsSent();
                $sentCount++;
            }

            $match->markPosterSent();

            return $sentCount;
        } catch (\Exception $e) {
            Log::error('Failed to send match poster', [
                'match_id' => $match->id,
                'error' => $e->getMessage(),
            ]);

            return $sentCount;
        }
    }

    /**
     * Send match summary to team members
     */
    public function sendMatchSummary(Matches $match): int
    {
        $tournament = $match->tournament;
        $settings = $tournament->settings;
        $sentCount = 0;

        // Check if auto-send is enabled
        if ($settings && !$settings->shouldAutoSendMatchSummary()) {
            return 0;
        }

        // Check if match is completed
        if (!$match->isCompleted()) {
            return 0;
        }

        try {
            // Get or create summary
            $summary = $match->getOrCreateSummary();

            // Generate poster if not exists
            if (!$summary->summary_poster) {
                $posterPath = $this->matchSummaryService->generate($match);
                $summary->update(['summary_poster' => $posterPath]);
            }

            // Check if already sent
            if ($summary->poster_sent) {
                return 0;
            }

            // Get recipients from both teams
            $recipients = $match->getAllTeamEmails();

            foreach ($recipients as $email) {
                $log = NotificationLog::log(
                    $tournament,
                    $match,
                    NotificationLog::TYPE_MATCH_SUMMARY,
                    NotificationLog::CHANNEL_EMAIL,
                    $email,
                    $summary->summary_poster
                );

                // TODO: Send actual email with summary
                $log->markAsSent();
                $sentCount++;
            }

            $summary->markPosterSent();

            return $sentCount;
        } catch (\Exception $e) {
            Log::error('Failed to send match summary', [
                'match_id' => $match->id,
                'error' => $e->getMessage(),
            ]);

            return $sentCount;
        }
    }

    /**
     * Send award poster to player
     */
    public function sendAwardPoster(MatchAward $award): bool
    {
        $match = $award->match;
        $tournament = $match->tournament;
        $player = $award->player;

        if (!$player || !$player->email) {
            return false;
        }

        try {
            // Generate poster if not exists
            if (!$award->poster_image) {
                $posterPath = $this->awardPosterService->generate($award);
                $award->update(['poster_image' => $posterPath]);
            }

            // Log notification
            $log = NotificationLog::log(
                $tournament,
                $award,
                NotificationLog::TYPE_AWARD_POSTER,
                NotificationLog::CHANNEL_EMAIL,
                $player->email,
                $award->poster_image
            );

            // TODO: Send actual email with award poster
            $award->markPosterSent();
            $log->markAsSent();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send award poster', [
                'award_id' => $award->id,
                'error' => $e->getMessage(),
            ]);

            if (isset($log)) {
                $log->markAsFailed($e->getMessage());
            }

            return false;
        }
    }

    /**
     * Process pending welcome cards for a tournament
     */
    public function processPendingWelcomeCards(Tournament $tournament): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        $registrations = $tournament->registrations()
            ->players()
            ->approved()
            ->where('welcome_card_sent', false)
            ->with('player')
            ->get();

        foreach ($registrations as $registration) {
            if ($this->sendWelcomeCard($registration)) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Process pending match summaries for a tournament
     */
    public function processPendingMatchSummaries(Tournament $tournament): array
    {
        $results = ['sent' => 0, 'failed' => 0];

        $matches = $tournament->matches()
            ->completedMatches()
            ->whereDoesntHave('summary', function ($query) {
                $query->where('poster_sent', true);
            })
            ->get();

        foreach ($matches as $match) {
            $count = $this->sendMatchSummary($match);
            if ($count > 0) {
                $results['sent'] += $count;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Get notification statistics for a tournament
     */
    public function getNotificationStats(Tournament $tournament): array
    {
        return [
            'welcome_cards' => [
                'sent' => NotificationLog::forTournament($tournament->id)->ofType(NotificationLog::TYPE_WELCOME_CARD)->sent()->count(),
                'pending' => NotificationLog::forTournament($tournament->id)->ofType(NotificationLog::TYPE_WELCOME_CARD)->pending()->count(),
                'failed' => NotificationLog::forTournament($tournament->id)->ofType(NotificationLog::TYPE_WELCOME_CARD)->failed()->count(),
            ],
            'match_posters' => [
                'sent' => NotificationLog::forTournament($tournament->id)->ofType(NotificationLog::TYPE_MATCH_POSTER)->sent()->count(),
                'pending' => NotificationLog::forTournament($tournament->id)->ofType(NotificationLog::TYPE_MATCH_POSTER)->pending()->count(),
                'failed' => NotificationLog::forTournament($tournament->id)->ofType(NotificationLog::TYPE_MATCH_POSTER)->failed()->count(),
            ],
            'match_summaries' => [
                'sent' => NotificationLog::forTournament($tournament->id)->ofType(NotificationLog::TYPE_MATCH_SUMMARY)->sent()->count(),
                'pending' => NotificationLog::forTournament($tournament->id)->ofType(NotificationLog::TYPE_MATCH_SUMMARY)->pending()->count(),
                'failed' => NotificationLog::forTournament($tournament->id)->ofType(NotificationLog::TYPE_MATCH_SUMMARY)->failed()->count(),
            ],
            'award_posters' => [
                'sent' => NotificationLog::forTournament($tournament->id)->ofType(NotificationLog::TYPE_AWARD_POSTER)->sent()->count(),
                'pending' => NotificationLog::forTournament($tournament->id)->ofType(NotificationLog::TYPE_AWARD_POSTER)->pending()->count(),
                'failed' => NotificationLog::forTournament($tournament->id)->ofType(NotificationLog::TYPE_AWARD_POSTER)->failed()->count(),
            ],
        ];
    }

    /**
     * Resend a failed notification
     */
    public function resendNotification(NotificationLog $log): bool
    {
        if (!$log->isFailed()) {
            return false;
        }

        // Reset status and try again based on type
        $log->update(['status' => NotificationLog::STATUS_PENDING, 'error_message' => null]);

        return match ($log->type) {
            NotificationLog::TYPE_WELCOME_CARD => $this->resendWelcomeCard($log),
            NotificationLog::TYPE_MATCH_POSTER => $this->resendMatchPoster($log),
            NotificationLog::TYPE_MATCH_SUMMARY => $this->resendMatchSummary($log),
            NotificationLog::TYPE_AWARD_POSTER => $this->resendAwardPoster($log),
            default => false,
        };
    }

    protected function resendWelcomeCard(NotificationLog $log): bool
    {
        if ($log->notifiable instanceof TournamentRegistration) {
            $log->notifiable->update(['welcome_card_sent' => false]);
            return $this->sendWelcomeCard($log->notifiable, true);
        }
        return false;
    }

    protected function resendMatchPoster(NotificationLog $log): bool
    {
        if ($log->notifiable instanceof Matches) {
            return $this->sendMatchPoster($log->notifiable, [$log->recipient]) > 0;
        }
        return false;
    }

    protected function resendMatchSummary(NotificationLog $log): bool
    {
        if ($log->notifiable instanceof Matches) {
            $summary = $log->notifiable->summary;
            if ($summary) {
                $summary->update(['poster_sent' => false]);
            }
            return $this->sendMatchSummary($log->notifiable) > 0;
        }
        return false;
    }

    protected function resendAwardPoster(NotificationLog $log): bool
    {
        if ($log->notifiable instanceof MatchAward) {
            $log->notifiable->update(['poster_sent' => false]);
            return $this->sendAwardPoster($log->notifiable);
        }
        return false;
    }
}
