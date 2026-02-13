<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\Notification\TournamentNotificationService;
use Illuminate\Console\Command;

class SendWelcomeCardsCommand extends Command
{
    protected $signature = 'tournament:send-welcome-cards
                            {--tournament= : Specific tournament ID}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send welcome cards to approved player registrations';

    public function handle(TournamentNotificationService $notificationService): int
    {
        $this->info('Starting welcome card processing...');

        $tournamentId = $this->option('tournament');
        $dryRun = $this->option('dry-run');

        // Get tournaments to process
        $query = Tournament::whereIn('status', ['registration', 'active']);

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

            // Get pending welcome cards
            $registrations = $tournament->registrations()
                ->players()
                ->approved()
                ->where('welcome_card_sent', false)
                ->with('player')
                ->get();

            if ($registrations->isEmpty()) {
                $this->info("  No pending welcome cards.");
                continue;
            }

            $this->info("  Found {$registrations->count()} pending welcome cards.");

            if ($dryRun) {
                foreach ($registrations as $registration) {
                    $this->line("  Would send to: {$registration->player?->email}");
                }
                continue;
            }

            $results = $notificationService->processPendingWelcomeCards($tournament);

            $totalSent += $results['sent'];
            $totalFailed += $results['failed'];

            $this->info("  Sent: {$results['sent']}, Failed: {$results['failed']}");
        }

        $this->newLine();
        $this->info("Welcome card processing complete.");
        $this->info("Total sent: {$totalSent}");
        $this->info("Total failed: {$totalFailed}");

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
