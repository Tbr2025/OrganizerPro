<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The scheduler must not be able to drop the production database.
 *
 * `demo:refresh-database` runs `migrate:fresh --seed --force` and is scheduled every fifteen
 * minutes. The only reason it has never destroyed the live tournament data is that no crontab
 * exists on the server, so `schedule:run` has never fired — which means the safety of this
 * application currently rests on a missing cron entry.
 *
 * This test is the guard that lets someone install a crontab without holding their breath.
 */
class ScheduleSafetyTest extends TestCase
{
    /** @return array<int, \Illuminate\Console\Scheduling\Event> */
    private function dueEvents(): array
    {
        $schedule = app(Schedule::class);

        return array_values(array_filter(
            $schedule->events(),
            // filtersPass() is what the scheduler itself calls to honour ->when()/->skip().
            fn ($event) => $event->filtersPass($this->app)
        ));
    }

    #[Test]
    public function the_destructive_demo_refresh_never_runs_outside_demo_mode(): void
    {
        config(['app.demo_mode' => false]);

        foreach ($this->dueEvents() as $event) {
            $this->assertStringNotContainsString(
                'demo:refresh-database',
                (string) $event->command,
                'demo:refresh-database runs migrate:fresh --seed --force. It must be filtered out '
                . 'whenever demo mode is off, at the scheduler and not only inside the command.'
            );
        }
    }

    #[Test]
    public function it_is_still_scheduled_when_demo_mode_is_deliberately_on(): void
    {
        // The guard must be a guard, not a deletion: a real demo site still needs this.
        config(['app.demo_mode' => true]);

        $commands = array_map(fn ($e) => (string) $e->command, $this->dueEvents());

        $this->assertTrue(
            collect($commands)->contains(fn ($c) => str_contains($c, 'demo:refresh-database')),
            'with demo mode on, the refresh should be scheduled again'
        );
    }
}
