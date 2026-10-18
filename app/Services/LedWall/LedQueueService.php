<?php

declare(strict_types=1);

namespace App\Services\LedWall;

use App\Events\LedDisplayChanged;
use App\Models\Ball;
use App\Models\LedDisplay;
use App\Models\LedQueueItem;
use App\Models\Matches;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What the LED wall shows, and when.
 *
 * The screen never decides. Every read calls tick(), which — under a row lock —
 * ends the panel whose time is up and starts the next one that may air. So any
 * number of screens on one display agree, a screen that reloads lands
 * mid-rotation, and nothing depends on a browser staying open.
 *
 * Between items the live scoreboard is up. In auto mode:
 *  - match moments (wicket, boundary, over, innings, result) are queued by
 *    onBall(), each waiting air_delay_seconds so the operator can skip it, or
 *    held for approval when require_approval is on;
 *  - after rotation_seconds of scoreboard with nothing waiting and no ball
 *    bowled, the next filler (top performers, partnership, sponsors) airs;
 *  - a new ball cuts a filler short: live play beats the filler.
 * Manual mode queues moments as held and runs no fillers.
 */
class LedQueueService
{
    private const EVENT_PANELS = ['wicket', 'boundary', 'last_over', 'innings_summary', 'result'];

    /** Between innings the wall rotates the first innings' story, not the live strip. */
    private const BREAK_PANELS = ['innings_summary', 'full_scorecard', 'top_batting', 'top_bowling', 'tournament_batting', 'tournament_bowling', 'sponsor'];

    /** Before the match starts: teams, toss, and (when set) the Playing XI. */
    private const PREMATCH_PANELS = ['prematch', 'playing_xi', 'sponsor'];

    /** After the match: the result, then who starred. */
    private const AFTER_PANELS = ['result', 'top_batting', 'top_bowling', 'sponsor'];

    /** Seconds of live score between break / after-match panels. */
    private const BREAK_GAP_SECONDS = 4;

    public function __construct(private readonly WallState $wall) {}

    /** The item on screen now (null = live scoreboard), advancing the queue if due. */
    public function tick(LedDisplay $display, ?CarbonInterface $now = null): ?LedQueueItem
    {
        $now ??= now();

        return DB::transaction(function () use ($display, $now) {
            $display = LedDisplay::whereKey($display->id)->lockForUpdate()->firstOrFail();

            $current = $display->queue()->where('status', 'showing')->orderByDesc('shown_at')->first();

            if ($display->frozen) {
                return $current;
            }

            if ($current && $current->ends_at && $current->ends_at->gt($now)) {
                return $current;
            }

            if ($current) {
                $current->update(['status' => 'shown']);
            }

            $next = $this->waiting($display)
                ->where('status', 'queued')
                ->where(fn ($q) => $q->whereNull('air_after')->orWhere('air_after', '<=', $now))
                ->first();

            if (! $next && $display->mode === 'auto' && $this->idleLongEnough($display, $now)) {
                $next = $this->queueFiller($display, $now);
            }

            if ($next) {
                $next->update([
                    'status' => 'showing',
                    'shown_at' => $now,
                    'ends_at' => $now->copy()->addSeconds(max(3, $next->show_seconds)),
                ]);
            }

            return $next;
        });
    }

    /**
     * What the broadcast ticker lays over the video: the panel on the match's LED
     * wall, on the same clock — but never frozen. Freeze is for the big screen; on
     * the ticker a panel goes after its own time (or sooner, if the operator ends
     * it) and the score strip carries on. Ticking here also means the ticker
     * follows the console with no wall screen open.
     *
     * @return array{display_id:?int, item_id:?int, panel:string, payload:?array, ends_in_ms:?int}
     */
    public function forTicker(Matches $match, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $display = LedDisplay::where('match_id', $match->id)->whereNull('revoked_at')->latest('id')->first();
        $none = ['display_id' => $display?->id, 'item_id' => null, 'panel' => 'scoreboard', 'payload' => null, 'ends_in_ms' => null];

        $item = $display ? $this->tick($display, $now) : null;
        if (! $item || ! $item->shown_at || $item->panel === 'scoreboard') {
            return $none;
        }

        // Its own time from when it went up — a frozen wall's open-ended hold,
        // or the fresh time an unfreeze gives it, never stretches it here.
        $ends = $item->shown_at->copy()->addSeconds(max(3, $item->show_seconds));
        if ($item->ends_at && $item->ends_at->lt($ends)) {
            $ends = $item->ends_at;
        }
        if ($ends->lte($now)) {
            return $none;
        }

        return array_merge($none, [
            'item_id' => $item->id,
            'panel' => $item->panel,
            'payload' => $item->payload,
            'ends_in_ms' => (int) $now->diffInMilliseconds($ends, false),
        ]);
    }

    /**
     * A delivery was recorded: queue the moments it made, on every live
     * display of this match. Called after the ball is committed.
     */
    public function onBall(Matches $match, Ball $ball, array $before, array $after): void
    {
        $displays = LedDisplay::where('match_id', $match->id)->whereNull('revoked_at')->get();
        if ($displays->isEmpty()) {
            return;
        }

        $state = $this->wall->forMatch($match);
        $moments = $this->moments($ball, $before, $after, $state);

        foreach ($displays as $display) {
            DB::transaction(function () use ($display, $moments, $ball) {
                $display = LedDisplay::whereKey($display->id)->lockForUpdate()->firstOrFail();

                // Live play beats a filler: cut it short.
                $display->queue()->where('status', 'showing')
                    ->whereNotIn('panel', self::EVENT_PANELS)->where('source', 'auto')
                    ->update(['status' => 'shown', 'ends_at' => now()]);

                foreach ($moments as $m) {
                    $this->add($display, $m['panel'], $m['payload'], $m['summary'], [
                        'ball_id' => $ball->id,
                        'show_seconds' => (int) $display->setting('event_seconds'),
                        'status' => $display->mode === 'manual' || $display->setting('require_approval') ? 'held' : 'queued',
                        'air_after' => now()->addSeconds((int) $display->setting('air_delay_seconds')),
                    ]);
                }
            });

            $this->changed($display);
        }
    }

    /** A ball was undone: nothing built from it may air. */
    public function onUndo(Matches $match, int $ballId): void
    {
        $displays = LedDisplay::where('match_id', $match->id)->get();

        foreach ($displays as $display) {
            $affected = $display->queue()->where('ball_id', $ballId)
                ->whereIn('status', ['queued', 'held', 'showing'])
                ->update(['status' => 'skipped', 'ends_at' => now()]);

            if ($affected) {
                $this->changed($display);
            }
        }
    }

    // ---- operator actions -------------------------------------------------

    public function approve(LedQueueItem $item, int $userId): void
    {
        if (in_array($item->status, LedQueueItem::WAITING, true)) {
            $item->update(['status' => 'queued', 'air_after' => now(), 'approved_by' => $userId]);
        }
        $this->changed($item->display);
    }

    public function hold(LedQueueItem $item): void
    {
        if ($item->status === 'queued') {
            $item->update(['status' => 'held']);
        }
        $this->changed($item->display);
    }

    public function skip(LedQueueItem $item): void
    {
        if (in_array($item->status, ['queued', 'held', 'showing'], true)) {
            $item->update(['status' => 'skipped', 'ends_at' => now()]);
        }
        $this->changed($item->display);
    }

    public function pin(LedQueueItem $item, bool $pinned): void
    {
        $item->update(['pinned' => $pinned]);
        $this->changed($item->display);
    }

    /** Swap with the neighbour above (-1) or below (+1) in the waiting list. */
    public function move(LedQueueItem $item, int $direction): void
    {
        DB::transaction(function () use ($item, $direction) {
            $list = $this->waiting($item->display)->get()->values();
            $i = $list->search(fn ($x) => $x->id === $item->id);
            $j = $i === false ? false : $i + ($direction < 0 ? -1 : 1);

            if ($i === false || $j < 0 || $j >= $list->count()) {
                return;
            }

            // Pinned items always lead; reordering happens within that group.
            $a = $list[$i];
            $b = $list[$j];
            if ($a->pinned !== $b->pinned) {
                return;
            }
            [$pa, $pb] = [$a->position, $b->position];
            if ($pa === $pb) {
                $pb = $pa + ($direction < 0 ? -1 : 1);
            }
            $a->update(['position' => $pb]);
            $b->update(['position' => $pa]);
        });
        $this->changed($item->display);
    }

    /** Put a panel on screen now, ending whatever is up. */
    public function push(LedDisplay $display, string $panel, int $userId): LedQueueItem
    {
        $item = DB::transaction(function () use ($display, $panel, $userId) {
            $display = LedDisplay::whereKey($display->id)->lockForUpdate()->firstOrFail();
            $display->queue()->where('status', 'showing')->update(['status' => 'shown', 'ends_at' => now()]);

            $now = now();
            $item = $this->add($display, $panel, $this->panelPayload($display, $panel), LedDisplay::PANELS[$panel] ?? $panel, [
                'source' => 'manual',
                'status' => 'showing',
                'approved_by' => $userId,
                'show_seconds' => (int) $display->setting($panel === 'scoreboard' ? 'rotation_seconds' : 'panel_seconds'),
            ]);
            $item->update(['shown_at' => $now, 'ends_at' => $now->copy()->addSeconds(max(3, $item->show_seconds))]);

            return $item;
        });

        $this->changed($display);

        return $item;
    }

    /** End the current panel and drop queued fillers: straight back to the score. */
    public function backToLive(LedDisplay $display): void
    {
        $display->queue()->where('status', 'showing')->update(['status' => 'shown', 'ends_at' => now()]);
        $display->queue()->whereIn('status', LedQueueItem::WAITING)
            ->whereIn('panel', LedDisplay::FILLERS)->update(['status' => 'skipped']);
        $display->update(['frozen' => false]);
        $this->changed($display);
    }

    public function setMode(LedDisplay $display, string $mode): void
    {
        $display->update(['mode' => $mode === 'manual' ? 'manual' : 'auto']);
        $this->changed($display);
    }

    public function freeze(LedDisplay $display, bool $frozen): void
    {
        DB::transaction(function () use ($display, $frozen) {
            $display->update(['frozen' => $frozen]);
            if (! $frozen) {
                // Unfrozen: the item on screen gets its full time from now.
                $current = $display->queue()->where('status', 'showing')->first();
                $current?->update(['ends_at' => now()->addSeconds(max(3, $current->show_seconds))]);
            }
        });
        $this->changed($display);
    }

    // ---- helpers ------------------------------------------------------------

    /** Waiting items in airing order: pinned first, then position, then age. */
    public function waiting(LedDisplay $display)
    {
        return $display->queue()->whereIn('status', LedQueueItem::WAITING)
            ->orderByDesc('pinned')->orderBy('position')->orderBy('id');
    }

    private function add(LedDisplay $display, string $panel, ?array $payload, string $summary, array $extra = []): LedQueueItem
    {
        $position = (int) $display->queue()->max('position') + 1;

        return $display->queue()->create(array_merge([
            'panel' => $panel,
            'payload' => $payload,
            'summary' => mb_substr($summary, 0, 255),
            'status' => 'queued',
            'source' => 'auto',
            'position' => $position,
            'show_seconds' => (int) $display->setting('panel_seconds'),
        ], $extra));
    }

    /** Scoreboard has been up for rotation_seconds, nothing waiting, no ball since. */
    private function idleLongEnough(LedDisplay $display, CarbonInterface $now): bool
    {
        if ($this->waiting($display)->exists()) {
            return false;
        }

        $rotation = in_array($this->phase($display), ['innings_break', 'completed'], true)
            ? self::BREAK_GAP_SECONDS
            : (int) $display->setting('rotation_seconds');
        $lastEnded = $display->queue()->max('ends_at');
        $lastBall = $display->match_id ? Ball::where('match_id', $display->match_id)->max('created_at') : null;
        $since = collect([$lastEnded, $lastBall, $display->updated_at])->filter()->map(fn ($t) => Carbon::parse($t))->max();

        return $since === null || $since->copy()->addSeconds($rotation)->lte($now);
    }

    private function queueFiller(LedDisplay $display, CarbonInterface $now): ?LedQueueItem
    {
        $fillers = match ($this->phase($display)) {
            'prematch' => self::PREMATCH_PANELS,
            'innings_break' => self::BREAK_PANELS,
            'completed' => self::AFTER_PANELS,
            default => array_values(array_intersect(LedDisplay::FILLERS, (array) $display->setting('fillers'))),
        };
        if ($fillers === []) {
            return null;
        }

        // Next after the last filler that aired, skipping panels with nothing to show.
        $last = $display->queue()->whereIn('panel', $fillers)->where('source', 'auto')->orderByDesc('id')->value('panel');
        $start = $last === null ? 0 : (array_search($last, $fillers, true) + 1) % count($fillers);

        for ($k = 0; $k < count($fillers); $k++) {
            $panel = $fillers[($start + $k) % count($fillers)];
            $payload = $this->panelPayload($display, $panel);
            if ($payload !== null) {
                return $this->add($display, $panel, $payload, LedDisplay::PANELS[$panel] ?? $panel, ['air_after' => $now]);
            }
        }

        return null;
    }

    /**
     * Data for a panel built from the current state; null when it would be
     * empty (no partnership yet, no sponsors) so the rotation skips it.
     */
    public function panelPayload(LedDisplay $display, string $panel): ?array
    {
        $state = $display->match ? $this->wall->forMatch($display->match) : null;
        $extras = $this->wall->tournamentExtras($display->tournament);

        return match ($panel) {
            'top_batting' => $state && $state['top_batters'] ? ['rows' => $state['top_batters']] : null,
            'top_bowling' => $state && $state['top_bowlers'] ? ['rows' => $state['top_bowlers']] : null,
            'partnership' => $state && ($state['partnership']['balls'] ?? 0) > 0
                ? ['partnership' => $state['partnership'], 'striker' => $state['striker'], 'non_striker' => $state['non_striker']] : null,
            'tournament_batting' => $extras['tournament_batting'] ? ['rows' => $extras['tournament_batting']] : null,
            'tournament_bowling' => $extras['tournament_bowling'] ? ['rows' => $extras['tournament_bowling']] : null,
            'sponsor' => $extras['sponsors'] ? ['sponsors' => $extras['sponsors']] : null,
            'batter_card' => $state && $state['striker'] ? ['batter' => $state['striker']] : null,
            'bowler_card' => $state && $state['bowler'] ? ['bowler' => $state['bowler']] : null,
            'last_over' => $state && $state['last_over'] ? ['over' => $state['last_over']] : null,
            'innings_summary' => $state ? ['innings' => $state['innings'][$this->summaryInnings($state)] ?? null] : null,
            'full_scorecard' => $state ? ['innings' => $state['innings'][$this->summaryInnings($state)] ?? null] : null,
            'playing_xi' => $state && ! empty($state['lineups']) ? ['lineups' => $state['lineups'], 'teams' => $state['teams']] : null,
            'opening' => $state ? $this->openingPayload($state) : null,
            'result' => $state && $state['result'] ? ['result' => $state['result']] : null,
            // Live panels draw from the state the screen already holds.
            default => [],
        };
    }

    /** prematch | live | innings_break | completed | cancelled — from the wall's own state. */
    private function phase(LedDisplay $display): ?string
    {
        return $display->match ? ($this->wall->forMatch($display->match)['status'] ?? null) : null;
    }

    /** Opening pair + first bowler from the current innings (or from lineups when no ball bowled yet). */
    private function openingPayload(array $state): ?array
    {
        $striker = $state['striker'];
        $nonStriker = $state['non_striker'];

        // Before the first ball: fall back to lineup batting_order 1 & 2.
        if (! $striker && ! $nonStriker) {
            $battingId = $state['batting']['id'] ?? null;
            $bowlingId = $state['bowling']['id'] ?? null;
            $lineup = ($battingId && isset($state['lineups'][$battingId])) ? $state['lineups'][$battingId]['players'] : [];
            if (count($lineup) < 2) {
                return null;
            }
            $sorted = collect($lineup)->sortBy('batting_order')->values();
            $striker = ['name' => $sorted[0]['name']];
            $nonStriker = ['name' => $sorted[1]['name']];

            // First bowler from the bowling side's lineup.
            $bowlLineup = ($bowlingId && isset($state['lineups'][$bowlingId])) ? $state['lineups'][$bowlingId]['players'] : [];
            $bowler = ! empty($bowlLineup) ? ['name' => $bowlLineup[0]['name']] : null;
        } else {
            $bowler = $state['bowler'];
        }

        return [
            'striker' => $striker,
            'non_striker' => $nonStriker,
            'bowler' => $bowler,
            'batting' => $state['batting'],
            'bowling' => $state['bowling'],
        ];
    }

    /** The innings worth summarising: the one just finished while the next has not begun. */
    private function summaryInnings(array $state): int
    {
        $n = (int) $state['current_innings'];
        $current = $state['innings'][$n] ?? null;

        return $n > 1 && ! ($current['batted'] ?? false) ? $n - 1 : $n;
    }

    /** The scorer ended the innings: put its summary up first thing in the break. */
    public function onInningsBreak(Matches $match): void
    {
        $this->queueForMatch($match, 'innings_summary', 'Innings break');
    }

    /** The scorer ended the match: the result goes up. */
    public function onMatchEnd(Matches $match): void
    {
        $this->queueForMatch($match, 'result', 'Result');
    }

    private function queueForMatch(Matches $match, string $panel, string $label): void
    {
        foreach (LedDisplay::where('match_id', $match->id)->whereNull('revoked_at')->get() as $display) {
            DB::transaction(function () use ($display, $panel, $label) {
                $display = LedDisplay::whereKey($display->id)->lockForUpdate()->firstOrFail();
                $payload = $this->panelPayload($display, $panel);
                if ($payload === null) {
                    return;
                }
                // A filler on screen gives way.
                $display->queue()->where('status', 'showing')->where('source', 'auto')
                    ->whereNotIn('panel', self::EVENT_PANELS)->update(['status' => 'shown', 'ends_at' => now()]);
                $this->add($display, $panel, $payload, $label, [
                    'show_seconds' => (int) $display->setting('event_seconds'),
                    'status' => $display->setting('require_approval') ? 'held' : 'queued',
                ]);
            });
            $this->changed($display);
        }
    }

    /**
     * The panels one delivery earns, each with a one-line summary for the
     * operator. Payloads are frozen now: what the operator reads is what airs.
     *
     * @return array<int, array{panel: string, payload: array, summary: string}>
     */
    private function moments(Ball $ball, array $before, array $after, array $state): array
    {
        $out = [];
        $n = $before['current_innings'];
        $was = $before['innings'][$n];
        $now = $after['innings'][$n];
        $score = "{$now['runs']}/{$now['wickets']} ({$now['overs']})";

        if ($ball->is_wicket) {
            $w = $state['last_wicket'];
            $out[] = [
                'panel' => 'wicket',
                'payload' => ['wicket' => $w, 'score' => $score, 'team' => $state['batting']],
                'summary' => "WICKET — {$w['name']} {$w['runs']} ({$w['balls']}) {$w['how_out']} · {$score}",
            ];
        } elseif (in_array((int) $ball->runs, [4, 6], true)) {
            $batter = collect($now['batters'])->firstWhere('id', (int) $ball->batsman_id);
            $word = (int) $ball->runs === 6 ? 'SIX' : 'FOUR';
            $out[] = [
                'panel' => 'boundary',
                'payload' => ['runs' => (int) $ball->runs, 'batter' => $batter ? ['name' => $batter['name'], 'runs' => $batter['runs'], 'balls' => $batter['balls']] : null, 'score' => $score],
                'summary' => "{$word} — " . ($batter['name'] ?? 'Batter') . " now {$batter['runs']} ({$batter['balls']}) · {$score}",
            ];
        }

        if ($now['over_complete'] && $now['legal_balls'] > $was['legal_balls'] && ! $now['complete'] && $state['last_over']) {
            $o = $state['last_over'];
            $out[] = [
                'panel' => 'last_over',
                'payload' => ['over' => $o, 'bowler' => $state['bowler'] ?? null, 'score' => $score],
                'summary' => "End of over {$o['over']}: {$o['runs']} runs, {$o['wickets']} wkt" . ($o['bowler'] ? " ({$o['bowler']})" : '') . " · {$score}",
            ];
        }

        if ($now['complete'] && ! $was['complete'] && ! $after['match_complete']) {
            $out[] = [
                'panel' => 'innings_summary',
                'payload' => ['innings' => $state['innings'][$n]],
                'summary' => 'Innings complete — ' . ($state['innings'][$n]['team']['name'] ?? 'Batting side') . " {$score}",
            ];
        }

        if ($after['match_complete'] && ! $before['match_complete'] && $state['result']) {
            $out[] = [
                'panel' => 'result',
                'payload' => ['result' => $state['result'], 'innings' => $state['innings']],
                'summary' => 'RESULT — ' . $state['result']['text'],
            ];
        }

        return $out;
    }

    private function changed(LedDisplay $display): void
    {
        LedDisplayChanged::announce($display->id);
    }
}
