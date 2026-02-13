<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Models\Matches;
use App\Services\Notification\TournamentNotificationService;
use Illuminate\Console\Command;

class SendMatchSummariesCommand extends Command
{
    protected $signature = 'tournament:send-match-summaries
                            {--tournament= : Specific tournament ID}
                            {--match= : Specific match ID}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send match summaries to team members for completed matches';

    public function handle(TournamentNotificationService $notificationService): int
    {
        $this->info('Starting match summary processing...');

        $tournamentId = $this->option('tournament');
        $matchId = $this->option('match');
        $dryRun = $this->option('dry-run');

        // If specific match is provided
        if ($matchId) {
            $match = Matches::find($matchId);

            if (!$match) {
                $this->error("Match not found: {$matchId}");
                return self::FAILURE;
            }

            return $this->processMatch($match, $notificationService, $dryRun);
        }

        // Get tournaments to process
        $query = Tournament::whereIn('status', ['active', 'completed']);

        if ($tournamentId) {
            $query->where('id', $tournamentId);
        }

        $tournaments = $query->get();

        if ($tournaments->isEmpty()) {
            $this->info('No tournaments found to process.');
            return self::SUCCESS;
        }

        $totalSent = 0;
        $totalFailed = 0;

        foreach ($tournaments as $tournament) {
            $this->info("Processing tournament: {$tournament->name}");

            // Get completed matches without sent summaries
            $matches = $tournament->matches()
                ->completedMatches()
                ->whereDoesntHave('summary', function ($query) {
                    $query->where('poster_sent', true);
                })
                ->with(['teamA', 'teamB', 'result'])
                ->get();

            if ($matches->isEmpty()) {
                $this->info("  No pending match summaries.");
                continue;
            }

            $this->info("  Found {$matches->count()} completed matches without sent summaries.");

            foreach ($matches as $match) {
                $this->line("  Processing: {$match->match_title}");

                if ($dryRun) {
                    $emails = $match->getAllTeamEmails();
                    $this->line("    Would send to: " . count($emails) . " recipients");
                    continue;
                }

                $count = $notificationService->sendMatchSummary($match);

                if ($count > 0) {
                    $totalSent += $count;
                    $this->info("    Sent to {$count} recipients");
                } else {
                    $totalFailed++;
                    $this->warn("    Failed or no recipients");
                }
            }
        }

        $this->newLine();
        $this->info("Match summary processing complete.");
        $this->info("Total emails sent: {$totalSent}");
        $this->info("Total matches failed: {$totalFailed}");

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function processMatch(Matches $match, TournamentNotificationService $notificationService, bool $dryRun): int
    {
        $this->info("Processing match: {$match->match_title}");

        if (!$match->isCompleted()) {
            $this->warn("Match is not completed yet.");
            return self::FAILURE;
        }

        if ($dryRun) {
            $emails = $match->getAllTeamEmails();
            $this->line("Would send to: " . count($emails) . " recipients");
            foreach ($emails as $email) {
                $this->line("  - {$email}");
            }
            return self::SUCCESS;
        }

        $count = $notificationService->sendMatchSummary($match);

        if ($count > 0) {
            $this->info("Sent to {$count} recipients.");
            return self::SUCCESS;
        }

        $this->warn("No recipients or sending failed.");
        return self::FAILURE;
    }
}
