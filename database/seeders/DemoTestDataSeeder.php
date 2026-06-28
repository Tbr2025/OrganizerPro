<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Clickable demo environment for manually testing the tournament-lifecycle work
 * (org isolation, tournament type, registration flow, auction pools/budgets).
 *
 * Run AFTER `php artisan migrate:fresh --seed` (which creates roles + Superadmin):
 *   php artisan db:seed --class=DemoTestDataSeeder
 *
 * Idempotent — safe to re-run. NOT wired into DatabaseSeeder (won't affect prod).
 */
class DemoTestDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $orgA = Organization::firstOrCreate(['name' => 'Alpha Sports'], ['max_tournaments' => 50, 'auction_enabled' => true]);
            $orgB = Organization::firstOrCreate(['name' => 'Beta Cricket'], ['max_tournaments' => 50, 'auction_enabled' => true]);

            $organizerA = $this->organizer('Alpha Organizer', 'organizer.a@test.com', $orgA->id);
            $this->organizer('Beta Organizer', 'organizer.b@test.com', $orgB->id);

            // --- Org A tournaments ---
            $alphaOpen = $this->tournament('Alpha Open Cup', 'alpha-open-cup', $orgA->id, 'open', 'registration');
            TournamentSetting::firstOrCreate(
                ['tournament_id' => $alphaOpen->id],
                ['player_registration_open' => true, 'team_registration_open' => true]
            );

            $alphaAuctionT = $this->tournament('Alpha Auction League', 'alpha-auction-league', $orgA->id, 'auction', 'active');

            // --- Org B tournament (isolation contrast) ---
            $this->tournament('Beta Open Cup', 'beta-open-cup', $orgB->id, 'open', 'registration');

            // --- Players in Org A: approved (retain-eligible, with users) + pending (queue) ---
            foreach (['Aaron Approved', 'Bilal Approved', 'Chetan Approved'] as $i => $name) {
                $this->player($name, "approved{$i}.a@test.com", $orgA->id, 'approved', withUser: true);
            }
            foreach (['Dimit Pending', 'Imran Pending'] as $i => $name) {
                $this->player($name, "pending{$i}.a@test.com", $orgA->id, 'pending', withUser: true);
            }

            // --- Players in Org B (isolation contrast) ---
            $this->player('Beta Player One', 'p1.b@test.com', $orgB->id, 'approved');
            $this->player('Beta Player Two', 'p2.b@test.com', $orgB->id, 'pending');

            // --- Org A teams for the auction ---
            $teams = collect(['Alpha Strikers', 'Alpha Titans', 'Alpha Kings'])
                ->map(fn ($n) => ActualTeam::firstOrCreate(
                    ['name' => $n, 'organization_id' => $orgA->id],
                    ['tournament_id' => $alphaAuctionT->id, 'short_name' => Str::upper(Str::substr($n, 6, 3))]
                ));

            // --- Auction in Org A with 10 waiting players (no pool yet) ---
            $auction = Auction::firstOrCreate(
                ['name' => 'Alpha Auction League', 'organization_id' => $orgA->id],
                [
                    'tournament_id' => $alphaAuctionT->id,
                    'status' => 'scheduled',
                    'base_price' => 100,
                    'max_bid_per_player' => 50000,
                    'max_budget_per_team' => 100000,
                    'bid_type' => 'open',
                    'open_bid_mode' => 'online',
                    'mode_manually_overridden' => false,
                ]
            );

            if ($auction->players()->count() === 0) {
                for ($i = 1; $i <= 10; $i++) {
                    $player = $this->player("Auction Player {$i}", "auc{$i}.a@test.com", $orgA->id, 'approved');
                    AuctionPlayer::create([
                        'auction_id' => $auction->id,
                        'player_id' => $player->id,
                        'organization_id' => $orgA->id,
                        'base_price' => 100,
                        'starting_price' => 100,
                        'status' => 'waiting',
                    ]);
                }
            }
        });

        $this->command->info('Demo data ready. Logins: organizer.a@test.com / organizer.b@test.com (password: "password").');
    }

    private function organizer(string $name, string $email, int $orgId): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => Str::slug($name) . '-' . Str::random(4),
                'password' => Hash::make('password'),
                'organization_id' => $orgId,
                'email_verified_at' => now(),
            ]
        );

        if (! $user->organization_id) {
            $user->update(['organization_id' => $orgId]);
        }
        if (! $user->hasRole('Organizer') && \App\Models\Role::where('name', 'Organizer')->exists()) {
            $user->assignRole('Organizer');
        }

        return $user;
    }

    private function tournament(string $name, string $slug, int $orgId, string $type, string $status): Tournament
    {
        return Tournament::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'organization_id' => $orgId,
                'type' => $type,
                'status' => $status,
                'start_date' => now()->addWeek()->toDateString(),
                'end_date' => now()->addWeeks(3)->toDateString(),
            ]
        );
    }

    private function player(string $name, string $email, int $orgId, string $status, bool $withUser = false): Player
    {
        $userId = null;
        if ($withUser) {
            $u = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'username' => Str::slug($name) . '-' . Str::random(4),
                    'password' => Hash::make('password'),
                    'organization_id' => $orgId,
                    'email_verified_at' => now(),
                ]
            );
            $userId = $u->id;
        }

        return Player::firstOrCreate(
            ['email' => $email],
            [
                'organization_id' => $orgId,
                'user_id' => $userId,
                'name' => $name,
                'status' => $status,
                'jersey_name' => Str::upper(Str::substr($name, 0, 6)),
            ]
        );
    }
}
