<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\SetupStorage::class,
        Commands\CreatePlaceholderImages::class,
        Commands\SendMatchPostersCommand::class,
        Commands\UpdatePointTablesCommand::class,
        Commands\CleanupRegistrationsCommand::class,
        Commands\SendWelcomeCardsCommand::class,
        Commands\SendMatchSummariesCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Schedule the demo database refresh command every 15 minutes in demo mode.
        $schedule->command('demo:refresh-database')->everyFifteenMinutes();

        // Tournament scheduled tasks
        // Send match posters daily at 9 AM
        $schedule->command('tournament:send-match-posters')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Update point tables daily at 12:30 AM
        $schedule->command('tournament:update-point-tables')
            ->dailyAt('00:30')
            ->withoutOverlapping()
            ->runInBackground();

        // Cleanup old pending registrations every Sunday at 2 AM
        $schedule->command('tournament:cleanup-registrations --days=30')
            ->weeklyOn(0, '02:00')
            ->withoutOverlapping();

        // Send welcome cards daily at 10 AM
        $schedule->command('tournament:send-welcome-cards')
            ->dailyAt('10:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Send match summaries hourly (for recently completed matches)
        $schedule->command('tournament:send-match-summaries')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
