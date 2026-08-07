<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\Player;
use App\Models\User;
use Database\Seeders\AuctionTestBenchSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Build or tear down the local bench for testing an auction by hand.
 *
 * Everything the bench creates is prefixed `TEST`, and --purge deletes exactly that and
 * nothing else. It refuses to run against anything that looks like production, because
 * the purge is a bulk delete and getting that wrong on a live database would be
 * unrecoverable.
 */
class AuctionTestBench extends Command
{
    protected $signature = 'auction:test-bench
        {--purge : Delete everything the bench created}
        {--force : Skip the production guard}';

    protected $description = 'Create (or remove) 100 test players, six teams and two auctions for local testing.';

    public function handle(): int
    {
        if (! $this->guardEnvironment()) {
            return self::FAILURE;
        }

        return $this->option('purge') ? $this->purge() : $this->build();
    }

    /**
     * Refuse to touch a database that is not obviously local.
     *
     * The purge deletes every row whose name starts with TEST. That is fine on a laptop
     * and catastrophic anywhere else, so the check is on by default.
     */
    private function guardEnvironment(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        /*
         * The environment decides this, not the database host.
         *
         * This used to pass when the host was 127.0.0.1 — which is exactly how the
         * production server reaches its own MySQL. So on live the guard read "local",
         * and the command would happily seed 100 TEST players into the real database,
         * or purge every row whose name starts with TEST.
         *
         * An OR of two conditions cannot be a safety check when one of them is true in
         * production. `production` is now refused outright, and everything else has to be
         * an explicitly local environment.
         */
        if (app()->environment('production')) {
            $this->error('Refusing to run against a production environment. This command creates and deletes TEST data.');

            return false;
        }

        if (! app()->environment(['local', 'testing'])) {
            $this->error(sprintf(
                'Environment is "%s", which is not local or testing. Re-run with --force only if you are certain.',
                app()->environment()
            ));

            return false;
        }

        return true;
    }

    private function build(): int
    {
        $this->warn('Reminder: MAIL_MAILER is ' . config('mail.default') . '.');

        if (config('mail.default') === 'smtp') {
            $this->warn('Both bench auctions have email test mode ON, so nothing will be sent — but set MAIL_MAILER=log while testing if you would rather be certain.');
        }

        $this->call('db:seed', ['--class' => AuctionTestBenchSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }

    private function purge(): int
    {
        $prefix = AuctionTestBenchSeeder::PREFIX . '%';

        $auctionIds = Auction::withoutGlobalScopes()->where('name', 'like', $prefix)->pluck('id');
        $playerIds = Player::withoutGlobalScopes()->where('name', 'like', $prefix)->pluck('id');
        $teamIds = ActualTeam::withoutGlobalScopes()->where('name', 'like', $prefix)->pluck('id');
        $userIds = User::where('email', 'like', '%@bench.test')->pluck('id');

        $this->table(['What', 'Count'], [
            ['Auctions', $auctionIds->count()],
            ['Players', $playerIds->count()],
            ['Teams', $teamIds->count()],
            ['Manager logins', $userIds->count()],
        ]);

        if ($auctionIds->isEmpty() && $playerIds->isEmpty() && $teamIds->isEmpty() && $userIds->isEmpty()) {
            $this->info('Nothing to purge.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Delete all of the above?', false)) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($auctionIds, $playerIds, $teamIds, $userIds) {
            // Rounds, entries, bids, pools and auction_players all cascade from the
            // auction, so deleting the auctions clears the sealed-round tables too.
            Auction::withoutGlobalScopes()->whereIn('id', $auctionIds)->delete();

            // Registrations and pivots are keyed on the player, and cascade.
            Player::withoutGlobalScopes()->whereIn('id', $playerIds)->delete();

            ActualTeam::withoutGlobalScopes()->whereIn('id', $teamIds)->delete();

            User::whereIn('id', $userIds)->delete();
        });

        $this->info('Bench removed.');

        return self::SUCCESS;
    }
}
