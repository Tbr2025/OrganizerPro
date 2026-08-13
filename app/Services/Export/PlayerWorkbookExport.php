<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Player;
use Illuminate\Support\Collection;

/**
 * Every field a player carries, as one .xlsx sheet.
 *
 * The existing CSV export hand-lists 25 columns, so anything added to `players` since it was
 * written is silently absent — and the request that prompted this was for "every information".
 * The column set here is therefore DERIVED from the model's own attributes rather than typed
 * out, so a new column appears in the export the day it appears on the player.
 *
 * Uses XlsxWriter, the dependency-free writer already in this codebase, for the same reason it
 * was written: no composer install on a production box to get an export out.
 */
class PlayerWorkbookExport
{
    /**
     * Columns that are noise in a spreadsheet, or that belong to something else.
     *
     * The `verified_*` flags are the verification UI's business — one per real field, doubling
     * the width of the sheet with booleans nobody reads in Excel. `layout_json` is a poster
     * layout blob. The foreign keys are dropped in favour of the NAMES resolved below, which is
     * what a human opening this actually wants.
     */
    private const SKIP = [
        'layout_json', 'image', 'welcome_image_path',
        'team_id', 'kit_size_id', 'batting_profile_id', 'bowling_profile_id',
        'player_type_id', 'location_id', 'organization_id', 'user_id', 'actual_team_id',
        'verified_team_id', 'created_by', 'approved_by',
    ];

    /** Relationship-derived columns, appended after the player's own fields. */
    private const RESOLVED = [
        'organization' => 'Organization',
        'player_type' => 'Player Role',
        'batting_profile' => 'Batting Profile',
        'bowling_profile' => 'Bowling Profile',
        'kit_size' => 'Kit Size',
        'location' => 'Location',
        'registration_team' => 'Registration Team',
        'squad_team' => 'Squad Team',
        'acquisition' => 'Acquired As',
        'acquisition_price' => 'Acquisition Value',
        'user_email' => 'Login Email',
    ];

    /**
     * @param  Collection<int, Player>  $players
     */
    public function write(Collection $players, string $path): void
    {
        $fields = $this->fieldsFor($players);

        $header = array_merge(
            array_map(fn (string $f) => $this->label($f), $fields),
            array_values(self::RESOLVED)
        );

        $rows = [$header];

        foreach ($players as $player) {
            $row = [];

            foreach ($fields as $field) {
                $row[] = $this->scalar($player->getAttribute($field));
            }

            foreach (array_keys(self::RESOLVED) as $key) {
                $row[] = $this->resolved($player, $key);
            }

            $rows[] = $row;
        }

        (new XlsxWriter())->addSheet('Players', $rows)->save($path);
    }

    /**
     * The player's own columns, in table order, minus the noise.
     *
     * Read from the SCHEMA, not from a model's attributes. Attributes vary per instance —
     * anything decorated onto the collection (the acquisition fields, say) would otherwise become
     * a column derived from whichever player happened to be first, and a player without it would
     * silently write blanks under a header that means something else.
     */
    private function fieldsFor(Collection $players): array
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('players');

        return array_values(array_filter(
            $columns,
            fn (string $c) => ! in_array($c, self::SKIP, true)
                // Verification flags are one per real field and read as TRUE/FALSE columns
                // nobody opens a spreadsheet to see.
                && ! str_starts_with($c, 'verified_')
        ));
    }

    /** `mobile_number_full` reads as "Mobile Number Full" rather than as a column name. */
    private function label(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }

    /**
     * Excel takes strings, numbers and blanks — not arrays, objects or booleans.
     *
     * A cast attribute can be a Carbon date, an array (preferred_batting_positions) or a bool,
     * and any of those written raw produces either a broken cell or the word "Array".
     */
    private function scalar(mixed $value): string|int|float
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value));
        }

        return is_numeric($value) ? $value : (string) $value;
    }

    private function resolved(Player $player, string $key): string|int|float
    {
        return match ($key) {
            'organization' => $player->user?->organization?->name ?? $player->organization?->name ?? '',
            'player_type' => $player->playerType?->type ?? '',
            'batting_profile' => $player->battingProfile?->style ?? '',
            'bowling_profile' => $player->bowlingProfile?->style ?? '',
            'kit_size' => $player->kitSize?->size ?? '',
            'location' => $player->location?->name ?? '',
            'registration_team' => $player->team?->name ?? $player->team_name_ref ?? '',
            'squad_team' => $player->actualTeam?->name ?? '',
            // From the auction row, never player_mode — see SquadAcquisitionService.
            'acquisition' => $player->acquisition_label ?? '',
            'acquisition_price' => $player->acquisition_price ?: '',
            'user_email' => $player->user?->email ?? '',
            default => '',
        };
    }
}
