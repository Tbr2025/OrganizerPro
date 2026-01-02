<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\Tournament\PointTableService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdatePointTablesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tournament:update-point-tables
                            {--tournament= : Specific tournament ID to update}
                            {--force : Force update even for completed tournaments}';

    /**
     * The console command description.
     */
    protected $description = 'Recalculate and update point tables for all active tournaments';

    public function __construct(
        private readonly PointTableService $pointTableService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tournamentId = $this->option('tournament');
        $force = $this->option('force');

        $query = Tournament::query();

        if ($tournamentId) {
            $query->where('id', $tournamentId);
        } elseif (!$force) {
            // Only process active tournaments by default
            $query->where('status', 'active');
        }

        $tournaments = $query->get();

        if ($tournaments->isEmpty()) {
            $this->info('No tournaments found to update.');
            return Command::SUCCESS;
        }

        $this->info("Found {$tournaments->count()} tournaments to update.");

        $successCount = 0;
        $failCount = 0;

        foreach ($tournaments as $tournament) {
            try {
                $this->processTournament($tournament);
                $successCount++;
                $this->info("✓ Updated point table for: {$tournament->name}");
            } catch (\Exception $e) {
                $failCount++;
                $this->error("✗ Failed to update {$tournament->name}: {$e->getMessage()}");
                Log::error("UpdatePointTables failed for tournament #{$tournament->id}", [
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
     * Process a single tournament
     */
    private function processTournament(Tournament $tournament): void
    {
        $this->line("  Recalculating point table for {$tournament->name}...");

        // Recalculate point table
        $this->pointTableService->recalculatePointTable($tournament);

        $this->line("  Done.");
    }
}
