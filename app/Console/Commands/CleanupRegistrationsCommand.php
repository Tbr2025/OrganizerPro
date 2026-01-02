<?php

namespace App\Console\Commands;

use App\Models\TournamentRegistration;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupRegistrationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tournament:cleanup-registrations
                            {--days=30 : Days after which pending registrations are deleted}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Remove old pending tournament registrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Looking for pending registrations older than {$cutoffDate->toDateString()}...");

        $query = TournamentRegistration::where('status', 'pending')
            ->where('created_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No old pending registrations found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$count} pending registrations to clean up.");

        if ($dryRun) {
            $this->warn('DRY RUN - No registrations will be deleted.');

            $registrations = $query->with('tournament')->get();

            $this->table(
                ['ID', 'Type', 'Name', 'Tournament', 'Created At'],
                $registrations->map(function ($reg) {
                    return [
                        $reg->id,
                        $reg->registration_type,
                        $reg->data['name'] ?? $reg->data['team_name'] ?? 'Unknown',
                        $reg->tournament?->name ?? 'Unknown',
                        $reg->created_at->toDateTimeString(),
                    ];
                })
            );

            return Command::SUCCESS;
        }

        // Confirm deletion
        if (!$this->confirm("Are you sure you want to delete {$count} pending registrations?")) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        try {
            $deleted = $query->delete();

            $this->info("Successfully deleted {$deleted} pending registrations.");

            Log::info("CleanupRegistrations: Deleted {$deleted} pending registrations older than {$days} days");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to delete registrations: {$e->getMessage()}");

            Log::error("CleanupRegistrations failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
