<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Services\Poster\MatchPosterService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMatchPostersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tournament:send-match-posters
                            {--days=2 : Days before match to send poster}
                            {--tournament= : Specific tournament ID to process}';

    /**
     * The console command description.
     */
    protected $description = 'Send match posters to teams X days before their matches';

    public function __construct(
        private readonly MatchPosterService $posterService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $daysBeforeMatch = (int) $this->option('days');
        $tournamentId = $this->option('tournament');

        $targetDate = Carbon::now()->addDays($daysBeforeMatch)->toDateString();

        $this->info("Looking for matches on {$targetDate} (in {$daysBeforeMatch} days)...");

        $query = Matches::with([
            'tournament.settings',
            'teamA.players.player.user',
            'teamB.players.player.user',
            'ground',
        ])
            ->where('status', 'upcoming')
            ->where('is_cancelled', false)
            ->where('poster_sent', false)
            ->whereDate('match_date', $targetDate);

        if ($tournamentId) {
            $query->where('tournament_id', $tournamentId);
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->info('No matches found for poster sending.');
            return Command::SUCCESS;
        }

        $this->info("Found {$matches->count()} matches to process.");

        $successCount = 0;
        $failCount = 0;

        foreach ($matches as $match) {
            try {
                $this->processMatch($match);
                $successCount++;
                $this->info("✓ Processed match #{$match->id}: {$match->teamA?->name} vs {$match->teamB?->name}");
            } catch (\Exception $e) {
                $failCount++;
                $this->error("✗ Failed to process match #{$match->id}: {$e->getMessage()}");
                Log::error("SendMatchPosters failed for match #{$match->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Completed: {$successCount} successful, {$failCount} failed");

        return $failCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Process a single match
     */
    private function processMatch(Matches $match): void
    {
        // Check if tournament has poster notifications enabled
        $settings = $match->tournament->settings;
        if (!$settings?->match_poster_enabled) {
            $this->line("  Skipping - poster notifications disabled for tournament");
            return;
        }

        // Generate poster if not already generated
        if (!$match->poster_image) {
            $posterPath = $this->posterService->generate($match);
            $match->update(['poster_image' => $posterPath]);
        }

        // Collect recipients
        $recipients = $this->getMatchRecipients($match);

        if (empty($recipients)) {
            $this->line("  No recipients found for match");
            $match->update(['poster_sent' => true]);
            return;
        }

        // Send notifications
        foreach ($recipients as $email) {
            try {
                Mail::send(
                    'emails.match-poster',
                    [
                        'match' => $match,
                        'tournament' => $match->tournament,
                    ],
                    function ($message) use ($email, $match) {
                        $message->to($email)
                            ->subject("Upcoming Match: {$match->teamA?->name} vs {$match->teamB?->name}")
                            ->attach(storage_path('app/public/' . $match->poster_image));
                    }
                );
            } catch (\Exception $e) {
                Log::warning("Failed to send match poster email to {$email}", [
                    'match_id' => $match->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Mark as sent
        $match->update(['poster_sent' => true]);
    }

    /**
     * Get email recipients for a match
     */
    private function getMatchRecipients(Matches $match): array
    {
        $recipients = [];

        // Get players from both teams
        foreach ([$match->teamA, $match->teamB] as $team) {
            if (!$team) {
                continue;
            }

            foreach ($team->players as $teamPlayer) {
                if ($teamPlayer->player?->user?->email) {
                    $recipients[] = $teamPlayer->player->user->email;
                }
            }
        }

        return array_unique($recipients);
    }
}
