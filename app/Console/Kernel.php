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
        /*
         * DANGER. This command runs `migrate:fresh --seed --force`. It DROPS EVERY TABLE.
         *
         * It is scheduled every fifteen minutes, and the only thing that has ever stopped it
         * running against the live tournament database is that no crontab exists on the server —
         * so `schedule:run` has never once fired. The moment anyone installs the obvious
         * `* * * * * php artisan schedule:run`, this becomes a fifteen-minute timer pointed at
         * production, held off by a single `.env` line.
         *
         * `->when()` here is defence in depth: the command already checks demo mode internally,
         * but that check lives one refactor away from the destructive call, and the scheduler
         * should not even invoke it. Now the entry is skipped before the command is constructed.
         *
         * Before installing a crontab, read this comment and decide deliberately. If this
         * deployment is not a demo site, the safest thing is to delete this entry outright.
         */
        $schedule->command('demo:refresh-database')
            ->everyFifteenMinutes()
            ->when(fn () => (bool) config('app.demo_mode'));

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
