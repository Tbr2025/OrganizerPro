<?php

namespace App\Support;

use App\Helpers\PlayerFormConfig;
use App\Models\BattingProfile;
use App\Models\BowlingProfile;
use App\Models\Player;
use App\Models\PlayerLocation;
use App\Models\PlayerType;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Single source of truth for filtering a list of players by their registration
 * parameters.
 *
 * Used by both the admin registrations list and the team manager player list, so
 * every condition is written against qualified `players.*` columns — the admin
 * page joins `players` onto `tournament_registrations`, the team manager page
 * queries `players` directly, and the same closure works in both.
 *
 * Which filters appear is driven by the tournament's own registration form
 * config, so a field hidden from the form never shows up as a filter, and a
 * renamed field keeps its custom label here too.
 */
class PlayerFilters
{
    /** Filters that expose employment details, hidden from team managers. */
    public const SENSITIVE = ['employer_name', 'employer_position', 'employer_address'];

    /**
     * Concise filter titles. The tournament's own label wins when it has been
     * customised, since form labels are written as questions ("I am a wicket
     * keeper") which read poorly as a filter heading.
     */
    private static function shortLabels(): array
    {
        return [
            'country' => 'Nationality',
            'state' => 'State / Province',
            'location' => 'Location',
            'visa_status' => 'Visa Status',
            'visa_expiry' => 'Visa Validity',
            'employer_name' => 'Employer',
            'employer_position' => 'Position',
            'employer_address' => 'Employer Address',
            'available_saturday' => 'Available Saturdays',
            'available_sunday' => 'Available Sundays',
            'played_ys_ipl_s1' => 'Played Previous Season',
            'registration_team' => 'Registration Team',
            'playing_team' => 'Playing Team',
            'jersey_name' => 'Jersey Name',
            'jersey_number' => 'Jersey Number',
            'tshirt_size' => 'T-Shirt Size',
            'pant_size' => 'Pant Size',
            'player_type' => 'Role',
            'batting_profile' => 'Batting Hand',
            'batting_mode' => 'Batting Mode',
            'preferred_batting_position' => 'Batting Position',
            'bowling_profile' => 'Bowling Profile',
            'is_wicket_keeper' => 'Wicket Keeper',
            'total_matches' => 'Total Matches',
            'total_runs' => 'Total Runs',
            'total_wickets' => 'Total Wickets',
            'transportation' => 'Transportation',
            'travel_plan' => 'Travel Plan',
            'date_of_birth' => 'Age',
            'image' => 'Player Photo',
            'cricheroes_profile_url' => 'CricHeroes Profile',
            'email' => 'Email',
            'mobile_number' => 'Mobile Number',
        ];
    }

    /** Registration-form section → the filters built from its fields. */
    private static function sectionOf(string $field): string
    {
        foreach (PlayerFormConfig::fieldGroups() as $section => $fields) {
            if (in_array($field, $fields, true)) {
                return $section;
            }
        }

        return 'Other';
    }

    /**
     * Every filter available for this tournament, keyed by filter param name.
     *
     * @param  Collection<int, Player>  $players  The tournament's player pool, used to
     *                                            build option lists and counts.
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(?Tournament $tournament, Collection $players, bool $includeSensitive = true): array
    {
        $settings = $tournament?->settings;
        $config = PlayerFormConfig::getFieldConfig($settings);
        $defaultLabels = PlayerFormConfig::fieldLabels();
        $short = self::shortLabels();

        // A field is filterable when the tournament shows it on its registration form.
        $visible = fn (string $field) => (bool) ($config[$field]['visible'] ?? false);

        $label = function (string $field) use ($config, $defaultLabels, $short) {
            $custom = $config[$field]['label'] ?? null;
            $isCustomised = $custom && $custom !== ($defaultLabels[$field] ?? null);

            return $isCustomised ? $custom : ($short[$field] ?? $custom ?? $field);
        };

        $defs = [];

        $add = function (string $field, string $key, array $def) use (&$defs, $visible, $label) {
            if (! $visible($field)) {
                return;
            }

            $defs[$key] = array_merge([
                'key' => $key,
                'field' => $field,
                'label' => $label($field),
                'section' => self::sectionOf($field),
                'type' => 'select',
                'sensitive' => in_array($field, self::SENSITIVE, true),
            ], $def);
        };

        // ---- Basic information -------------------------------------------------
        $add('country', 'country', [
            'options' => self::countOf($players, 'country', fn ($v) => self::countryName($v)),
            'apply' => fn ($q, $v) => $v === 'none'
                ? $q->whereNull('players.country')
                : $q->where('players.country', $v),
            'allow_none' => true,
        ]);

        $add('state', 'state', [
            'options' => self::countOf($players, 'state'),
            'apply' => fn ($q, $v) => $v === 'none'
                ? $q->whereNull('players.state')
                : $q->where('players.state', $v),
            'allow_none' => true,
        ]);

        $add('location', 'location', [
            'options' => self::countOfRelated($players, 'location_id', PlayerLocation::class, 'name'),
            'apply' => fn ($q, $v) => $v === 'none'
                ? $q->whereNull('players.location_id')
                : $q->where('players.location_id', $v),
            'allow_none' => true,
        ]);

        $add('registration_team', 'registration_team', [
            'options' => self::countOf($players, 'team_name_ref'),
            'apply' => fn ($q, $v) => $v === 'none'
                ? $q->whereNull('players.team_name_ref')
                : $q->where('players.team_name_ref', $v),
            'allow_none' => true,
        ]);

        $add('email', 'email_status', [
            'label' => 'Email',
            'options' => [
                'has' => 'Provided (' . $players->filter(fn ($p) => filled($p->email))->count() . ')',
                'missing' => 'Missing (' . $players->filter(fn ($p) => blank($p->email))->count() . ')',
            ],
            'apply' => fn ($q, $v) => $v === 'has'
                ? $q->whereNotNull('players.email')->where('players.email', '!=', '')
                : $q->where(fn ($s) => $s->whereNull('players.email')->orWhere('players.email', '')),
        ]);

        $add('cricheroes_profile_url', 'cricheroes', [
            'options' => [
                'has' => 'Provided (' . $players->filter(fn ($p) => filled($p->cricheroes_profile_url))->count() . ')',
                'missing' => 'Missing (' . $players->filter(fn ($p) => blank($p->cricheroes_profile_url))->count() . ')',
            ],
            'apply' => fn ($q, $v) => $v === 'has'
                ? $q->whereNotNull('players.cricheroes_profile_url')->where('players.cricheroes_profile_url', '!=', '')
                : $q->where(fn ($s) => $s->whereNull('players.cricheroes_profile_url')->orWhere('players.cricheroes_profile_url', '')),
        ]);

        $add('date_of_birth', 'age', [
            'type' => 'range',
            'suffix' => 'yrs',
            'apply' => function ($q, $v, string $bound) {
                // Older players have earlier birth dates, so a minimum age is an
                // upper bound on date_of_birth and vice versa.
                $date = now()->subYears((int) $v);

                return $bound === 'min'
                    ? $q->whereNotNull('players.date_of_birth')->whereDate('players.date_of_birth', '<=', $date)
                    : $q->whereNotNull('players.date_of_birth')->whereDate('players.date_of_birth', '>=', $date->subYear());
            },
        ]);

        // ---- Visa & employment -------------------------------------------------
        $add('visa_status', 'visa_status', [
            'options' => self::countOf(
                $players,
                'visa_status',
                fn ($v) => config('registration.visa_statuses.' . $v, $v)
            ),
            'apply' => fn ($q, $v) => $v === 'none'
                ? $q->whereNull('players.visa_status')
                : $q->where('players.visa_status', $v),
            'allow_none' => true,
        ]);

        $add('visa_expiry', 'visa_expiry', [
            'options' => [
                'expired' => 'Expired',
                'expiring_30' => 'Expiring in 30 days',
                'expiring_90' => 'Expiring in 90 days',
                'valid' => 'Valid',
                'none' => 'Not set',
            ],
            'apply' => fn ($q, $v) => match ($v) {
                'expired' => $q->whereNotNull('players.visa_expiry')->whereDate('players.visa_expiry', '<', now()),
                'expiring_30' => $q->whereNotNull('players.visa_expiry')
                    ->whereDate('players.visa_expiry', '>=', now())
                    ->whereDate('players.visa_expiry', '<=', now()->addDays(30)),
                'expiring_90' => $q->whereNotNull('players.visa_expiry')
                    ->whereDate('players.visa_expiry', '>=', now())
                    ->whereDate('players.visa_expiry', '<=', now()->addDays(90)),
                'valid' => $q->whereNotNull('players.visa_expiry')->whereDate('players.visa_expiry', '>=', now()),
                default => $q->whereNull('players.visa_expiry'),
            },
        ]);

        // ~500 distinct employers on a busy tournament, so this is a search box
        // rather than a dropdown.
        $add('employer_name', 'employer_name', [
            'type' => 'text',
            'placeholder' => 'Employer name…',
            'apply' => fn ($q, $v) => $q->where('players.employer_name', 'like', '%' . $v . '%'),
        ]);

        $add('employer_position', 'employer_position', [
            'type' => 'text',
            'placeholder' => 'Position…',
            'apply' => fn ($q, $v) => $q->where('players.employer_position', 'like', '%' . $v . '%'),
        ]);

        // ---- Availability ------------------------------------------------------
        $add('available_saturday', 'available_saturday', self::boolFilter($players, 'available_saturday', 'players.available_saturday'));
        $add('available_sunday', 'available_sunday', self::boolFilter($players, 'available_sunday', 'players.available_sunday'));
        $add('played_ys_ipl_s1', 'played_ys_ipl_s1', self::boolFilter($players, 'played_ys_ipl_s1', 'players.played_ys_ipl_s1'));

        // ---- Jersey ------------------------------------------------------------
        $add('jersey_number', 'jersey_number', [
            'type' => 'text',
            'placeholder' => 'e.g. 7',
            'apply' => fn ($q, $v) => $q->where('players.jersey_number', $v),
        ]);

        $add('tshirt_size', 'tshirt_size', [
            'options' => self::countOf($players, 'tshirt_size'),
            'apply' => fn ($q, $v) => $v === 'none'
                ? $q->whereNull('players.tshirt_size')
                : $q->where('players.tshirt_size', $v),
            'allow_none' => true,
        ]);

        $add('pant_size', 'pant_size', [
            'options' => self::countOf($players, 'pant_size'),
            'apply' => fn ($q, $v) => $v === 'none'
                ? $q->whereNull('players.pant_size')
                : $q->where('players.pant_size', $v),
            'allow_none' => true,
        ]);

        // ---- Player profile ----------------------------------------------------
        $add('player_type', 'player_type', [
            'options' => self::countOfRelated($players, 'player_type_id', PlayerType::class, 'type'),
            'apply' => fn ($q, $v) => $v === 'none'
                ? $q->whereNull('players.player_type_id')
                : $q->where('players.player_type_id', $v),
            'allow_none' => true,
        ]);

        $add('batting_profile', 'batting', [
            'options' => self::countOfRelated($players, 'batting_profile_id', BattingProfile::class, 'style'),
            'apply' => fn ($q, $v) => $v === 'none'
                ? $q->whereNull('players.batting_profile_id')
                : $q->where('players.batting_profile_id', $v),
            'allow_none' => true,
        ]);

        $add('bowling_profile', 'bowling', [
            'options' => self::countOfRelated($players, 'bowling_profile_id', BowlingProfile::class, 'style'),
            'apply' => fn ($q, $v) => $v === 'none'
                ? $q->whereNull('players.bowling_profile_id')
                : $q->where('players.bowling_profile_id', $v),
            'allow_none' => true,
        ]);

        $add('batting_mode', 'batting_mode', [
            'options' => self::countOf($players, 'batting_mode'),
            'apply' => fn ($q, $v) => $v === 'none'
                ? $q->whereNull('players.batting_mode')
                : $q->where('players.batting_mode', $v),
            'allow_none' => true,
        ]);

        $add('preferred_batting_position', 'batting_position', [
            'options' => self::countOfJson($players, 'preferred_batting_positions'),
            // Stored as a JSON array, so match membership rather than equality.
            'apply' => fn ($q, $v) => $q->whereJsonContains('players.preferred_batting_positions', $v),
        ]);

        $add('is_wicket_keeper', 'wk', self::boolFilter($players, 'is_wicket_keeper', 'players.is_wicket_keeper'));

        // ---- Leather ball experience -------------------------------------------
        foreach (['total_matches', 'total_runs', 'total_wickets'] as $stat) {
            $add($stat, $stat, [
                'type' => 'range',
                'apply' => fn ($q, $v, string $bound) => $q->where(
                    'players.' . $stat,
                    $bound === 'min' ? '>=' : '<=',
                    (int) $v
                ),
            ]);
        }

        // ---- Travel & transportation -------------------------------------------
        $add('transportation', 'transportation', self::boolFilter($players, 'transportation_required', 'players.transportation_required'));

        $add('travel_plan', 'travel_plan', [
            'options' => [
                'has_plan' => 'Has travel plans (' . $players->where('no_travel_plan', false)->count() . ')',
                'no_plan' => 'No travel plans (' . $players->where('no_travel_plan', true)->count() . ')',
            ],
            'apply' => fn ($q, $v) => $q->where('players.no_travel_plan', $v === 'no_plan'),
        ]);

        // ---- Photo -------------------------------------------------------------
        $add('image', 'photo', [
            'options' => [
                'has' => 'Uploaded (' . $players->filter(fn ($p) => filled($p->image_path))->count() . ')',
                'missing' => 'Missing (' . $players->filter(fn ($p) => blank($p->image_path))->count() . ')',
            ],
            'apply' => fn ($q, $v) => $v === 'has'
                ? $q->whereNotNull('players.image_path')->where('players.image_path', '!=', '')
                : $q->where(fn ($s) => $s->whereNull('players.image_path')->orWhere('players.image_path', '')),
        ]);

        if (! $includeSensitive) {
            $defs = array_filter($defs, fn ($def) => ! $def['sensitive']);
        }

        return $defs;
    }

    /** Group definitions by registration-form section, in form order. */
    public static function grouped(array $definitions): array
    {
        $order = array_keys(PlayerFormConfig::fieldGroups());
        $groups = [];

        foreach ($definitions as $key => $def) {
            $groups[$def['section']][$key] = $def;
        }

        uksort($groups, function ($a, $b) use ($order) {
            $ai = array_search($a, $order, true);
            $bi = array_search($b, $order, true);

            return ($ai === false ? PHP_INT_MAX : $ai) <=> ($bi === false ? PHP_INT_MAX : $bi);
        });

        return $groups;
    }

    /**
     * Apply every filter present in the request to the query.
     *
     * Unknown or blank parameters are ignored, so a stale bookmarked URL simply
     * filters by whatever it still recognises.
     */
    public static function apply($query, Request $request, array $definitions): void
    {
        foreach ($definitions as $key => $def) {
            if (($def['type'] ?? 'select') === 'range') {
                foreach (['min', 'max'] as $bound) {
                    $value = $request->get($key . '_' . $bound);
                    if ($value !== null && $value !== '') {
                        ($def['apply'])($query, $value, $bound);
                    }
                }

                continue;
            }

            $value = $request->get($key);
            if ($value !== null && $value !== '') {
                ($def['apply'])($query, $value);
            }
        }
    }

    /**
     * The request parameters these filters own — used to build "reset" links and
     * to detect whether anything is active.
     *
     * @return array<int, string>
     */
    public static function parameterNames(array $definitions): array
    {
        $names = [];

        foreach ($definitions as $key => $def) {
            if (($def['type'] ?? 'select') === 'range') {
                $names[] = $key . '_min';
                $names[] = $key . '_max';
            } else {
                $names[] = $key;
            }
        }

        return $names;
    }

    /**
     * Active filters as display-ready chips: label, value text and the param(s)
     * to drop when the chip is dismissed.
     *
     * @return array<int, array{label:string,value:string,params:array<int,string>}>
     */
    public static function activeChips(Request $request, array $definitions): array
    {
        $chips = [];

        foreach ($definitions as $key => $def) {
            $type = $def['type'] ?? 'select';

            if ($type === 'range') {
                $min = $request->get($key . '_min');
                $max = $request->get($key . '_max');

                if (blank($min) && blank($max)) {
                    continue;
                }

                $suffix = $def['suffix'] ?? '';
                $text = match (true) {
                    filled($min) && filled($max) => $min . '–' . $max . ' ' . $suffix,
                    filled($min) => '≥ ' . $min . ' ' . $suffix,
                    default => '≤ ' . $max . ' ' . $suffix,
                };

                $chips[] = [
                    'label' => $def['label'],
                    'value' => trim($text),
                    'params' => [$key . '_min', $key . '_max'],
                ];

                continue;
            }

            $value = $request->get($key);
            if ($value === null || $value === '') {
                continue;
            }

            // Option labels carry their counts ("Work Visa (538)"); strip those
            // for the chip so it stays compact.
            $raw = $def['options'][$value] ?? $value;
            $text = trim(preg_replace('/\s*\(\d+\)$/', '', (string) $raw));

            $chips[] = [
                'label' => $def['label'],
                'value' => $text,
                'params' => [$key],
            ];
        }

        return $chips;
    }

    // -- option builders -----------------------------------------------------

    /** A Yes/No filter over a boolean column, with counts. */
    private static function boolFilter(Collection $players, string $attribute, string $column): array
    {
        $yes = $players->where($attribute, true)->count();
        $no = $players->count() - $yes;

        return [
            'options' => [
                '1' => 'Yes (' . $yes . ')',
                '0' => 'No (' . $no . ')',
            ],
            'apply' => fn ($q, $v) => $q->where($column, (bool) (int) $v),
        ];
    }

    /**
     * Distinct values of a column among the pool, most common first, each label
     * carrying its count. A "Not set" option is appended when values are missing.
     */
    private static function countOf(Collection $players, string $attribute, ?callable $labeller = null): array
    {
        $counts = $players->pluck($attribute)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->countBy()
            ->sortDesc();

        $options = [];
        foreach ($counts as $value => $count) {
            $name = $labeller ? $labeller($value) : $value;
            $options[$value] = $name . ' (' . $count . ')';
        }

        $missing = $players->count() - $counts->sum();
        if ($missing > 0) {
            $options['none'] = 'Not set (' . $missing . ')';
        }

        return $options;
    }

    /** Same as countOf() but resolves a foreign key to its display name. */
    private static function countOfRelated(Collection $players, string $foreignKey, string $model, string $nameColumn): array
    {
        $counts = $players->pluck($foreignKey)->filter()->countBy()->sortDesc();

        $names = $model::whereIn('id', $counts->keys())->pluck($nameColumn, 'id');

        $options = [];
        foreach ($counts as $id => $count) {
            $options[$id] = ($names[$id] ?? ('#' . $id)) . ' (' . $count . ')';
        }

        $missing = $players->count() - $counts->sum();
        if ($missing > 0) {
            $options['none'] = 'Not set (' . $missing . ')';
        }

        return $options;
    }

    /** Distinct members of a JSON array column (e.g. preferred batting positions). */
    private static function countOfJson(Collection $players, string $attribute): array
    {
        $counts = $players->flatMap(function ($player) use ($attribute) {
            $value = $player->{$attribute};

            if (is_string($value)) {
                $value = json_decode($value, true);
            }

            return is_array($value) ? $value : [];
        })->filter(fn ($v) => $v !== null && $v !== '')->countBy()->sortDesc();

        $options = [];
        foreach ($counts as $value => $count) {
            $options[$value] = $value . ' (' . $count . ')';
        }

        return $options;
    }

    private static function countryName(string $code): string
    {
        return config('countries.list.' . $code, $code);
    }

    /**
     * The player pool a tournament's filters are built from — one lightweight
     * query, so option lists and counts cover every player in the tournament
     * rather than just the current page.
     *
     * @param  array<int, int>  $playerIds
     * @return Collection<int, Player>
     */
    public static function pool(array $playerIds): Collection
    {
        if (empty($playerIds)) {
            return collect();
        }

        return Player::withoutOrganizationScope()
            ->whereIn('id', $playerIds)
            ->get([
                'id', 'country', 'state', 'location_id', 'team_name_ref', 'email',
                'cricheroes_profile_url', 'date_of_birth', 'visa_status', 'visa_expiry',
                'available_saturday', 'available_sunday', 'played_ys_ipl_s1',
                'jersey_number', 'tshirt_size', 'pant_size', 'player_type_id',
                'batting_profile_id', 'bowling_profile_id', 'batting_mode',
                'preferred_batting_positions', 'is_wicket_keeper', 'total_matches',
                'total_runs', 'total_wickets', 'transportation_required',
                'no_travel_plan', 'image_path', 'actual_team_id', 'player_mode',
            ]);
    }
}
