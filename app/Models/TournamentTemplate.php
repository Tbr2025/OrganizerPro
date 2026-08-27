<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'type',
        'name',
        'background_image',
        'layout_json',
        'overlay_images',
        'canvas_width',
        'canvas_height',
        'placeholders',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'layout_json' => 'array',
        'overlay_images' => 'array',
        'placeholders' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public const TYPE_WELCOME_CARD = 'welcome_card';
    public const TYPE_MATCH_POSTER = 'match_poster';
    public const TYPE_MATCH_SUMMARY = 'match_summary';
    public const TYPE_AWARD_POSTER = 'award_poster';
    public const TYPE_FLYER = 'flyer';
    public const TYPE_CHAMPIONS_POSTER = 'champions_poster';
    public const TYPE_POINT_TABLE = 'point_table';
    public const TYPE_FIXTURES_POSTER = 'fixtures_poster';
    public const TYPE_RETAINED_WELCOME_CARD = 'retained_welcome_card';

    /**
     * The auction poster, in the two shapes a poster actually gets used in.
     *
     * Two types rather than one type with a size picker, because the canvas is not a setting
     * on a poster — it is the design. A layout drawn for 1920x1080 does not become a portrait
     * poster by changing two numbers; every element lands in the wrong place. Keeping them
     * apart also means "is there a vertical one yet" is a question the templates page can
     * answer, which it could not if both hid behind a single type.
     *
     * Distinct from `AuctionTemplate`'s player_card, which is the LED wall's own card: that one
     * is fixed at the wall's 1601x910 and is screenshotted from the wall so the hall and the
     * download cannot disagree. These are posters, drawn in the drag editor, for everywhere
     * else — a feed, a story, a printed sheet.
     */
    public const TYPE_AUCTION_POSTER = 'auction_poster';
    public const TYPE_AUCTION_POSTER_PORTRAIT = 'auction_poster_portrait';

    /**
     * The pre-match line-up poster: eleven names, both crests, one featured player.
     *
     * Its own type rather than a match_poster variant, because the eleven names are not a
     * placeholder — they are a repeating region whose height depends on how many names it holds,
     * the way `fixture_area` is. A match_poster has no such region, and giving it one would mean
     * every match_poster layout carried a list it never draws.
     *
     * The XI is chosen per poster from the team's roster; nothing persists it. There is no
     * lineup table in this schema, so an XI cannot be read back — a poster is the only place
     * this selection has ever existed.
     */
    public const TYPE_PLAYING_XI = 'playing_xi';

    public const TYPES = [
        self::TYPE_WELCOME_CARD,
        self::TYPE_RETAINED_WELCOME_CARD,
        self::TYPE_MATCH_POSTER,
        self::TYPE_MATCH_SUMMARY,
        self::TYPE_AWARD_POSTER,
        self::TYPE_FLYER,
        self::TYPE_CHAMPIONS_POSTER,
        self::TYPE_POINT_TABLE,
        self::TYPE_FIXTURES_POSTER,
        self::TYPE_AUCTION_POSTER,
        self::TYPE_AUCTION_POSTER_PORTRAIT,
        self::TYPE_PLAYING_XI,
    ];

    /**
     * Every placeholder whose value is a FILE PATH rather than words.
     *
     * One list, read by the editor's sidebar and by the renderer, because these two disagreeing is
     * a whole class of bug rather than one: the editor decides which drawer a placeholder appears
     * in, and that decides the element type it is dragged in as, and THAT is what the renderer
     * dispatches on. `sold_team_logo` was missing here, so it sat under "Text Placeholders" — a
     * template author dragged the buying team's crest onto their poster and got its file path
     * printed as a line of text. `team_a_sponsor_logo` and `team_b_sponsor_logo` were missing the
     * same way.
     *
     * Keep additions here, not in the view. A placeholder added to getDefaultPlaceholders() above
     * and forgotten here renders as a path, which looks like a data bug and is a list bug.
     *
     * @return list<string>
     */
    public static function imagePlaceholders(): array
    {
        return [
            'player_image',
            'team_logo',
            'sold_team_logo',
            'playing_team_logo',
            'tournament_logo',
            'team_a_logo',
            'team_b_logo',
            'team_a_sponsor_logo',
            'team_b_sponsor_logo',
            'team_a_captain_image',
            'team_b_captain_image',
            'man_of_the_match_image',
            'best_batsman_image',
            'best_bowler_image',
            'winner_logo',
            'qr_code',
            'featured_player_image',
        ];
    }

    /**
     * Placeholders whose value is a fact, not a caption.
     *
     * The generate page lets an organizer retype any field on a poster, which is what makes one
     * template serve an academic league and a weekend tournament. These are the exception: they
     * are read straight off the match record, and a poster whose score disagrees with the
     * scorecard it came from is worse than no poster. Hiding them is still allowed — that is a
     * layout choice; only editing the value is refused.
     *
     * @return list<string>
     */
    public static function lockedPlaceholders(): array
    {
        return [
            // Totals, and every way the design might spell them
            'team_a_score', 'team_b_score',
            'team_a_score_wickets', 'team_b_score_wickets',
            'team_a_score_overs', 'team_b_score_overs',
            'team_a_runs', 'team_b_runs',
            'team_a_wickets', 'team_b_wickets',
            'team_a_overs', 'team_b_overs',
            // The outcome
            'result_summary', 'win_margin', 'winner_name',
            // Who played. A team's name is its identity in the fixture list and the point table.
            'team_a_name', 'team_b_name',
            'team_a_short_name', 'team_b_short_name',
            'lineup_team_name', 'lineup_team_short_name',
            'opponent_team_name', 'opponent_team_short_name',
        ];
    }

    /** Is this placeholder read-only at generate time? See lockedPlaceholders(). */
    public static function isLockedPlaceholder(?string $placeholder): bool
    {
        return $placeholder !== null && in_array($placeholder, self::lockedPlaceholders(), true);
    }

    /** Is this placeholder's value a file path? See imagePlaceholders(). */
    public static function isImagePlaceholder(?string $placeholder): bool
    {
        return $placeholder !== null && in_array($placeholder, self::imagePlaceholders(), true);
    }

    /**
     * Default placeholders for each template type
     */
    public static function getDefaultPlaceholders(string $type): array
    {
        return match ($type) {
            self::TYPE_WELCOME_CARD, self::TYPE_RETAINED_WELCOME_CARD => [
                'player_name',
                'jersey_name',
                'jersey_number',
                'team_name',
                'team_logo',
                'tournament_name',
                'tournament_logo',
                'player_image',
                'player_type',
                'batting_style',
                'bowling_style',
                'playing_team_name',
                'playing_team_logo',
            ],
            self::TYPE_MATCH_POSTER => [
                // Tournament
                'tournament_name',
                'tournament_logo',
                // Team A
                'team_a_name',
                'team_a_short_name',
                'team_a_logo',
                'team_a_location',
                'team_a_captain_name',
                'team_a_captain_image',
                'team_a_sponsor_logo',
                // Team B
                'team_b_name',
                'team_b_short_name',
                'team_b_logo',
                'team_b_location',
                'team_b_captain_name',
                'team_b_captain_image',
                'team_b_sponsor_logo',
                // Match Info
                'match_date',
                'match_date_day',
                'match_date_month',
                'match_date_weekday',
                'match_time',
                'match_day',
                'venue',
                'ground_name',
                'match_stage',
                'match_number',
            ],
            self::TYPE_MATCH_SUMMARY => [
                'tournament_name',
                'tournament_logo',
                // Team A
                'team_a_name',
                'team_a_short_name',
                'team_a_logo',
                'team_a_score',
                'team_a_score_wickets',
                'team_a_runs',
                'team_a_wickets',
                'team_a_overs',
                // Team B
                'team_b_name',
                'team_b_short_name',
                'team_b_logo',
                'team_b_score',
                'team_b_score_wickets',
                'team_b_runs',
                'team_b_wickets',
                'team_b_overs',
                // Result
                'result_summary',
                'winner_name',
                'winner_logo',
                'win_margin',
                'toss_result',
                // Match Info
                'match_date',
                'match_time',
                'venue',
                'match_stage',
                'match_number',
                // Awards
                'man_of_the_match_name',
                'man_of_the_match_image',
                'best_batsman_name',
                'best_batsman_image',
                'best_bowler_name',
                'best_bowler_image',
                // Man of the Match stats
                'man_of_the_match_runs', 'man_of_the_match_balls', 'man_of_the_match_fours', 'man_of_the_match_sixes',
                'man_of_the_match_overs', 'man_of_the_match_wickets', 'man_of_the_match_bowling_runs', 'man_of_the_match_maidens',
                'man_of_the_match_batting_figures', 'man_of_the_match_bowling_figures',
                // Best Batsman stats
                'best_batsman_runs', 'best_batsman_balls', 'best_batsman_fours', 'best_batsman_sixes',
                'best_batsman_batting_figures',
                // Best Bowler stats
                'best_bowler_overs', 'best_bowler_wickets', 'best_bowler_bowling_runs', 'best_bowler_maidens',
                'best_bowler_bowling_figures',
                // Performance Figures
                'batting_figures',
                'bowling_figures',
                // Scorecard Tables
                'batting_table_a',
                'batting_table_b',
                'bowling_table_a',
                'bowling_table_b',
            ],
            self::TYPE_AWARD_POSTER => [
                'tournament_name',
                'tournament_logo',
                'award_name',
                'player_name',
                'player_image',
                'jersey_number',
                'team_name',
                'team_logo',
                'match_details',
                'achievement_text',
                // Team A
                'team_a_name',
                'team_a_short_name',
                'team_a_logo',
                'team_a_score',
                'team_a_score_wickets',
                'team_a_runs',
                'team_a_wickets',
                'team_a_overs',
                'team_a_score_overs',
                // Team B
                'team_b_name',
                'team_b_short_name',
                'team_b_logo',
                'team_b_score',
                'team_b_score_wickets',
                'team_b_runs',
                'team_b_wickets',
                'team_b_overs',
                'team_b_score_overs',
                // Result
                'result_summary',
                'winner_name',
                'winner_logo',
                'win_margin',
                'batting_figures',
                'bowling_figures',
                // Individual batting stats
                'batting_runs',
                'batting_balls',
                'batting_fours',
                'batting_sixes',
                // Individual bowling stats
                'bowling_overs',
                'bowling_runs',
                'bowling_maidens',
                'bowling_wickets',
            ],
            self::TYPE_FLYER => [
                'tournament_name',
                'tournament_logo',
                'description',
                'start_date',
                'end_date',
                'location',
                'registration_link',
                'qr_code',
                'contact_phone',
                'contact_email',
            ],
            self::TYPE_CHAMPIONS_POSTER => [
                'tournament_name',
                'tournament_logo',
                'team_name',
                'team_logo',
                'title', // Champions / Runners Up
                'season',
                'year',
            ],
            self::TYPE_POINT_TABLE => [
                'tournament_name',
                'tournament_logo',
                'group_name',
                'table_data', // JSON array of teams with stats
                'last_updated',
            ],
            self::TYPE_FIXTURES_POSTER => [
                'tournament_name',
                'tournament_logo',
                'fixture_area',
            ],
            /*
             * Both orientations carry the SAME fields. The difference between them is the
             * canvas, not the content — a vertical poster is not a horizontal one with less
             * on it, and offering a different field list per shape would only mean designing
             * one and then discovering the other cannot say what the first one said.
             */
            self::TYPE_AUCTION_POSTER, self::TYPE_AUCTION_POSTER_PORTRAIT => [
                // The player
                'player_name',
                'jersey_name',
                'jersey_number',
                'player_image',
                'player_type',
                'batting_style',
                'bowling_style',
                'player_location',
                'player_age',
                /*
                 * Career figures — the same three the LED wall puts under a player's name
                 * (matches, runs, wickets). Left out of the first cut and immediately missed:
                 * a bidding poster with no stats gives a team nothing to bid on.
                 *
                 * These three and no more, because these are the three the players table
                 * actually holds. A placeholder for an average or a strike rate would render
                 * blank on every poster ever made, which is worse than not offering it.
                 */
                'total_matches',
                'total_runs',
                'total_wickets',
                // The lot
                'lot_number',
                'pool_name',
                'base_price',
                // A kept player's figure, for a template that wants to caption it as such —
                // `base_price` already falls back to it, see AuctionPosterData.
                'retained_value',
                /*
                 * The team a player CURRENTLY plays for — their club before this auction. A
                 * separate fact from whoever buys them, and both belong on the poster: one is
                 * where they come from, the other is where they are going.
                 */
                'playing_team_name',
                'playing_team_logo',

                // The result. Blank before the hammer falls, which is what makes one template
                // serve both the "coming up" poster and the "sold" one.
                'sold_price',
                'sold_status',
                'sold_team_name',
                'sold_team_logo',
                // Kept as aliases of sold_team_* so a template drawn before the split still
                // renders — renaming a placeholder must not blank an existing design.
                'team_name',
                'team_logo',

                // Travel window, for a tournament that flies players in. Pair it with the plane
                // icon in the editor's icon palette.
                'travel_plan',
                // The event
                'auction_name',
                'tournament_name',
                'tournament_logo',
            ],
            self::TYPE_PLAYING_XI => [
                // The event
                'tournament_name',
                'tournament_logo',
                // The two sides. Both crests appear even though only one XI is listed —
                // a line-up poster is always "our XI against them".
                'team_a_name',
                'team_a_short_name',
                'team_a_logo',
                'team_b_name',
                'team_b_short_name',
                'team_b_logo',
                // Whose XI this is. Resolved from the picked side, so a layout can caption
                // the list without the designer having to know which side is A.
                'lineup_team_name',
                'lineup_team_short_name',
                'lineup_team_logo',
                'opponent_team_name',
                'opponent_team_short_name',
                'opponent_team_logo',
                // Match info
                'match_date',
                'match_time',
                'venue',
                'ground_name',
                'match_stage',
                'match_number',
                // The cut-out player the poster is built around, and the captions for them.
                'featured_player_name',
                'featured_player_image',
                'featured_player_role',
                /*
                 * The eleven. A repeating region, not a text placeholder: the renderer draws
                 * one row per selected player and sizes itself to the count, exactly as
                 * `fixture_area` does. Dropping it as text would print a JSON blob.
                 */
                'lineup_area',
            ],
            default => [],
        };
    }

    /**
     * The canvas a type should start on.
     *
     * A poster type that opens on the wrong shape is one the designer has to fix before they
     * can start, every time — and the two auction types exist precisely to BE two shapes.
     * 1920x1080 is the ordinary landscape screen; 1080x1350 is the 4:5 the feeds crop to.
     *
     * @return array{0: int, 1: int}
     */
    public static function defaultCanvas(string $type): array
    {
        return match ($type) {
            self::TYPE_AUCTION_POSTER => [1920, 1080],
            self::TYPE_AUCTION_POSTER_PORTRAIT => [1080, 1350],
            default => [1080, 1080],
        };
    }

    // Relationships
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    // Scopes
    public function scopeWelcomeCards($query)
    {
        return $query->where('type', self::TYPE_WELCOME_CARD);
    }

    public function scopeMatchPosters($query)
    {
        return $query->where('type', self::TYPE_MATCH_POSTER);
    }

    public function scopeMatchSummaries($query)
    {
        return $query->where('type', self::TYPE_MATCH_SUMMARY);
    }

    public function scopeAwardPosters($query)
    {
        return $query->where('type', self::TYPE_AWARD_POSTER);
    }

    public function scopeFlyers($query)
    {
        return $query->where('type', self::TYPE_FLYER);
    }

    public function scopeChampionsPosters($query)
    {
        return $query->where('type', self::TYPE_CHAMPIONS_POSTER);
    }

    public function scopePointTables($query)
    {
        return $query->where('type', self::TYPE_POINT_TABLE);
    }

    public function scopeFixturesPosters($query)
    {
        return $query->where('type', self::TYPE_FIXTURES_POSTER);
    }

    public function scopeRetainedWelcomeCards($query)
    {
        return $query->where('type', self::TYPE_RETAINED_WELCOME_CARD);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Helpers
    public function getBackgroundImageUrlAttribute(): ?string
    {
        return $this->background_image ? asset('storage/' . $this->background_image) : null;
    }

    public function getTypeDisplayAttribute(): string
    {
        return static::getTypeDisplay($this->type);
    }

    /**
     * Get display name for a template type (static version)
     */
    public static function getTypeDisplay(string $type): string
    {
        return match ($type) {
            self::TYPE_WELCOME_CARD => 'Welcome Card',
            // The label moved to Icon Player; the type CONSTANT and its stored value did not.
            self::TYPE_RETAINED_WELCOME_CARD => 'Icon Player Welcome Card',
            self::TYPE_MATCH_POSTER => 'Match Poster',
            self::TYPE_MATCH_SUMMARY => 'Match Summary',
            self::TYPE_AWARD_POSTER => 'Award Poster',
            self::TYPE_FLYER => 'Tournament Flyer',
            self::TYPE_CHAMPIONS_POSTER => 'Champions Poster',
            self::TYPE_POINT_TABLE => 'Point Table',
            self::TYPE_FIXTURES_POSTER => 'Fixtures Poster',
            self::TYPE_AUCTION_POSTER => 'Auction Poster (Horizontal)',
            self::TYPE_AUCTION_POSTER_PORTRAIT => 'Auction Poster (Vertical)',
            self::TYPE_PLAYING_XI => 'Playing XI',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function setAsDefault(): void
    {
        // Remove default from other templates of same type
        static::where('tournament_id', $this->tournament_id)
            ->where('type', $this->type)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
