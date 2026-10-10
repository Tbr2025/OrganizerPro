<?php

declare(strict_types=1);

namespace App\Services\Tournament;

use App\Models\ActualTeam;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentSeries;
use App\Models\TournamentSetting;
use App\Models\User;
use App\Services\Poster\TemplateCopier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Start the next season of a tournament, copying what the organizer ticked.
 *
 * A season is an ordinary tournament in the same series. Everything copied is
 * a NEW row owned by the new season — teams included, because squads, budgets,
 * scoring lists and the auction all key off a team's own tournament — and every
 * file is copied to a new path, because replacing a logo or background deletes
 * the old file and would blank the other season.
 *
 * Players are people, not per-season rows: the same Player carries over, joined
 * to the new season by an approved registration (and, for an open tournament,
 * by the squad link to their new team). An auction season starts with empty
 * teams and every approved player back in the pool.
 */
class SeasonService
{
    public const COPY = ['teams', 'squads', 'templates', 'settings'];

    public function __construct(private readonly TemplateCopier $templates) {}

    /**
     * @param  array{name: string, start_date: string, end_date: string, copy: string[]}  $data
     */
    public function startNextSeason(Tournament $from, array $data, User $by): Tournament
    {
        $copy = array_intersect(self::COPY, $data['copy'] ?? []);
        // Squads need teams to sit in (open), and an auction has no squads to copy.
        if (in_array('squads', $copy, true) && ! in_array('teams', $copy, true) && ! $from->isAuction()) {
            $copy[] = 'teams';
        }

        return DB::transaction(function () use ($from, $data, $copy, $by) {
            $series = $this->ensureSeries($from);
            $number = (int) Tournament::withoutGlobalScopes()->where('series_id', $series->id)->max('season_number') + 1;

            $season = Tournament::create([
                'organization_id' => $from->organization_id,
                'zone_id' => $from->zone_id,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'logo' => $this->copyFile($from->logo, 'tournaments/logos'),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'location' => $from->location,
                'status' => 'draft',
                'type' => $from->type,
                'series_id' => $series->id,
                'season_number' => $number,
            ]);

            // The people who ran the last season run this one; the creator too.
            $organizerIds = $from->organizers()->pluck('users.id')->all();
            if ($by->hasRole('Organizer') && ! $by->hasAnyRole(['Superadmin', 'Admin'])) {
                $organizerIds[] = $by->id;
            }
            $season->organizers()->syncWithoutDetaching(array_unique($organizerIds));

            $templateMap = in_array('templates', $copy, true) ? $this->copyTemplates($from, $season) : [];

            if (in_array('settings', $copy, true)) {
                $this->copySettings($from, $season, $templateMap);
            }

            $teamMap = in_array('teams', $copy, true) ? $this->copyTeams($from, $season) : [];

            if (in_array('squads', $copy, true)) {
                $this->carryPlayers($from, $season, $teamMap, $by);
            }

            return $season;
        });
    }

    /**
     * Link an existing tournament into a series as a given season (Super
     * Admin), or take it out of one (null series). Nothing is copied.
     */
    public function link(Tournament $tournament, ?Tournament $seasonOf, ?int $seasonNumber): void
    {
        DB::transaction(function () use ($tournament, $seasonOf, $seasonNumber) {
            if ($seasonOf === null) {
                $tournament->update(['series_id' => null, 'season_number' => null]);

                return;
            }

            $series = $this->ensureSeries($seasonOf);
            $number = $seasonNumber ?: (int) Tournament::withoutGlobalScopes()->where('series_id', $series->id)->max('season_number') + 1;

            $taken = Tournament::withoutGlobalScopes()->where('series_id', $series->id)
                ->where('season_number', $number)->where('id', '!=', $tournament->id)->exists();
            if ($taken) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'season_number' => "Season {$number} already exists in this series.",
                ]);
            }

            $tournament->update(['series_id' => $series->id, 'season_number' => $number]);
        });
    }

    private function ensureSeries(Tournament $tournament): TournamentSeries
    {
        if ($tournament->series_id) {
            return TournamentSeries::findOrFail($tournament->series_id);
        }

        $series = TournamentSeries::create([
            'organization_id' => $tournament->organization_id,
            'name' => preg_replace('/\s*[-–—:]?\s*season\s*\d+\s*$/i', '', $tournament->name) ?: $tournament->name,
        ]);
        $tournament->update(['series_id' => $series->id, 'season_number' => 1]);

        return $series;
    }

    /** @return array<int, int> old template id => new template id */
    private function copyTemplates(Tournament $from, Tournament $to): array
    {
        $map = [];
        foreach ($from->templates()->get() as $template) {
            $map[$template->id] = $this->templates->copy($template, $to->id, null, keepDefault: true)->id;
        }

        return $map;
    }

    private function copySettings(Tournament $from, Tournament $to, array $templateMap): void
    {
        $settings = TournamentSetting::where('tournament_id', $from->id)->first();
        if ($settings) {
            $new = $settings->replicate();
            $new->tournament_id = $to->id;
            foreach (['logo', 'background_image', 'flyer_image', 'og_image', 'photo_sample_path', 'team_photo_sample_path'] as $file) {
                $new->{$file} = $this->copyFile($settings->{$file}, 'tournaments/season-copies');
            }
            // Point the default templates at this season's copies, never at last season's.
            foreach (['default_welcome_template_id', 'default_match_template_id', 'default_summary_template_id', 'semi_final_template_id', 'final_template_id'] as $col) {
                $new->{$col} = $settings->{$col} ? ($templateMap[$settings->{$col}] ?? null) : null;
            }
            // A new season opens its own registration window.
            $new->registration_deadline = null;
            $new->save();
        }

        foreach ($from->awards()->get() as $award) {
            $copy = $award->replicate();
            $copy->tournament_id = $to->id;
            $copy->template_image = $this->copyFile($award->template_image, 'tournament_awards');
            $copy->save();
        }

        foreach ($from->customFields()->get() as $field) {
            $copy = $field->replicate();
            $copy->tournament_id = $to->id;
            $copy->save();
        }

        foreach (\App\Models\TournamentBanner::where('tournament_id', $from->id)->get() as $banner) {
            $copy = $banner->replicate();
            $copy->tournament_id = $to->id;
            $copy->image_path = $this->copyFile($banner->image_path, 'tournament_banners');
            $copy->save();
        }
    }

    /** @return array<int, int> old team id => new team id */
    private function copyTeams(Tournament $from, Tournament $to): array
    {
        $map = [];
        foreach ($this->teamsOf($from) as $team) {
            $copy = $team->replicate(['invite_code']);
            $copy->tournament_id = $to->id;
            $copy->is_global = false;
            $copy->invite_code = Str::random(12);
            $copy->team_logo = $this->copyFile($team->team_logo, 'team-logos');
            $copy->sponsor_logo = $this->copyFile($team->sponsor_logo, 'team-logos');
            $copy->captain_image = $this->copyFile($team->captain_image, 'player_images');
            $copy->save();
            $map[$team->id] = $copy->id;

            // Owners, managers and captains keep their place on the team.
            // Players are added by carryPlayers() for an open tournament only.
            $staff = DB::table('actual_team_users')->where('actual_team_id', $team->id)
                ->whereRaw('LOWER(role) <> ?', ['player'])->get();
            foreach ($staff as $row) {
                DB::table('actual_team_users')->insert([
                    'actual_team_id' => $copy->id, 'user_id' => $row->user_id, 'role' => $row->role,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return $map;
    }

    /**
     * Approved players come back approved. Open: to the same team's squad.
     * Auction: no team — the pool for the new auction.
     */
    private function carryPlayers(Tournament $from, Tournament $to, array $teamMap, User $by): void
    {
        $squad = DB::table('player_actual_team_tournament')->where('tournament_id', $from->id)->get()->keyBy('player_id');

        $playerIds = TournamentRegistration::where('tournament_id', $from->id)
            ->where('type', 'player')->where('status', 'approved')->whereNotNull('player_id')
            ->pluck('player_id')
            ->merge($squad->keys())
            ->unique()->values();

        $players = \App\Models\Player::withoutOrganizationScope()->whereIn('id', $playerIds)->get()->keyBy('id');

        foreach ($playerIds as $playerId) {
            $player = $players[$playerId] ?? null;
            if (! $player) {
                continue;
            }

            $newTeamId = ! $to->isAuction() && isset($squad[$playerId]) ? ($teamMap[$squad[$playerId]->actual_team_id] ?? null) : null;

            TournamentRegistration::create([
                'tournament_id' => $to->id,
                'organization_id' => $to->organization_id,
                'type' => 'player',
                'player_id' => $playerId,
                'status' => 'approved',
                'processed_at' => now(),
                'processed_by' => $by->id,
                'actual_team_id' => $newTeamId,
                'remarks' => 'Carried over from ' . $from->name,
            ]);

            if ($newTeamId) {
                DB::table('player_actual_team_tournament')->insert([
                    'player_id' => $playerId, 'actual_team_id' => $newTeamId, 'tournament_id' => $to->id,
                    'role' => $squad[$playerId]->role ?? null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                // On the team for scoring too (actual_team_users needs the account).
                if ($player->user_id) {
                    $exists = DB::table('actual_team_users')->where('actual_team_id', $newTeamId)->where('user_id', $player->user_id)->exists();
                    if (! $exists) {
                        DB::table('actual_team_users')->insert([
                            'actual_team_id' => $newTeamId, 'user_id' => $player->user_id, 'role' => 'Player',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    /** Teams of a tournament: by their own tournament_id or the legacy pivot. */
    private function teamsOf(Tournament $tournament)
    {
        return ActualTeam::withoutGlobalScopes()
            ->where('tournament_id', $tournament->id)
            ->orWhereIn('id', DB::table('actual_team_tournament')->where('tournament_id', $tournament->id)->pluck('actual_team_id'))
            ->orderBy('id')->get();
    }

    /** A copy of a public-disk file at a new path (null stays null; a missing file keeps its path). */
    private function copyFile(?string $path, string $dir): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return $path ?: null;
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $new = trim($dir, '/') . '/' . uniqid('season_') . ($ext ? '.' . $ext : '');
        Storage::disk('public')->copy($path, $new);

        return $new;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'season';
        $slug = $base;
        $i = 2;
        while (Tournament::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
