<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Models\ActualTeam;
use App\Models\ActualTeamUser;
use App\Models\Matches;
use App\Models\Player;
use App\Models\User;
use App\Services\Auth\PlayerLogin;
use App\Support\Phone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Add a walk-in player to a match side from the scoring screen.
 *
 * A ball's batsman/bowler/fielder point at actual_team_users, and the scorer's
 * dropdowns only list a squad row that resolves to a Player through user_id.
 * So a player who never registered needs all three rows — users, players,
 * actual_team_users — before they can face or bowl a ball. This creates them
 * from a name and a phone, or attaches an existing player found by that phone.
 */
class QuickPlayerService
{
    /**
     * Players in the match's organization already holding this phone number,
     * so the scorer can pick the existing record instead of a duplicate.
     *
     * @return Collection<int, Player>
     */
    public function candidates(Matches $match, string $phone): Collection
    {
        if ($phone === '') {
            return collect();
        }

        return Player::withoutOrganizationScope()
            ->where('organization_id', $this->organizationId($match))
            ->where('mobile_number_full', $phone)
            ->orderBy('id')
            ->limit(5)
            ->get();
    }

    /**
     * Put a player on $team for this match and return the squad row the
     * scorer selects. $existing re-uses a player picked from candidates().
     */
    public function add(
        Matches $match,
        ActualTeam $team,
        string $name,
        string $countryCode,
        string $nationalNumber,
        User $by,
        ?Player $existing = null,
    ): ActualTeamUser {
        if (! in_array((int) $team->id, [(int) $match->team_a_id, (int) $match->team_b_id], true)) {
            throw new InvalidArgumentException('That team is not playing this match.');
        }

        $orgId = $this->organizationId($match);

        if ($existing !== null && (int) $existing->organization_id !== (int) $orgId) {
            throw new InvalidArgumentException('That player belongs to another organization.');
        }

        return DB::transaction(function () use ($match, $team, $name, $countryCode, $nationalNumber, $by, $existing, $orgId) {
            $player = $existing ?? $this->createPlayer($match, $name, $countryCode, $nationalNumber, $by, $orgId);

            if (! $player->user_id) {
                $player->user_id = $this->createUser($player->name, $player->email, $orgId, $player->mobile_number_full)->id;
                $player->save();
            }

            $squadRow = ActualTeamUser::firstOrCreate(
                ['actual_team_id' => $team->id, 'user_id' => $player->user_id],
                ['role' => 'Player'],
            );

            // The tournament squad list. One team per player per tournament
            // (unique player_id + tournament_id) — a player already listed for
            // another team keeps that listing; they still play this match.
            if ($match->tournament_id) {
                DB::table('player_actual_team_tournament')->insertOrIgnore([
                    'player_id' => $player->id,
                    'tournament_id' => $match->tournament_id,
                    'actual_team_id' => $team->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $squadRow->setRelation('player', $player);
        });
    }

    private function createPlayer(Matches $match, string $name, string $countryCode, string $national, User $by, int $orgId): Player
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        [$first, $last] = array_pad(explode(' ', $name, 2), 2, null);

        $user = $this->createUser($name, null, $orgId, Phone::full($countryCode, $national));

        return Player::withoutOrganizationScope()->create([
            'organization_id' => $orgId,
            'user_id' => $user->id,
            'name' => $name,
            'first_name' => $first,
            'last_name' => $last,
            'jersey_name' => $name,
            'mobile_country_code' => '+' . ltrim(preg_replace('/[^\d]/', '', $countryCode), '0'),
            'mobile_national_number' => preg_replace('/\D/', '', $national),
            'mobile_number_full' => Phone::full($countryCode, $national),
            // Playing in a match the organizers are scoring is the approval.
            'status' => 'approved',
            'approved_by' => $by->id,
            'created_by' => $by->id,
            'created_via' => 'scoring',
            'source_match_id' => $match->id,
        ]);
    }

    private function createUser(string $name, ?string $email, int $orgId, ?string $phone = null): User
    {
        // A phone-only player signs in with their number; an admin sets the password.
        return app(PlayerLogin::class)->createUser($name, $email, $phone, $orgId);
    }

    private function organizationId(Matches $match): int
    {
        return (int) ($match->tournament?->organization_id
            ?? throw new InvalidArgumentException('The match has no tournament.'));
    }
}
