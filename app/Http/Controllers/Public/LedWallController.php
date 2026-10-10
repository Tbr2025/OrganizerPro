<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LedDisplay;
use App\Models\Matches;
use App\Services\LedWall\LedQueueService;
use App\Services\LedWall\WallState;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * The LED wall's public side. No login: the private token in the URL is the
 * key, and a revoked or unknown token is a plain 404. Everything served here is
 * names and scores — see WallState.
 */
class LedWallController extends Controller
{
    public function __construct(
        private readonly LedQueueService $queue,
        private readonly WallState $wall,
    ) {}

    public function show(string $token): View
    {
        $display = $this->display($token);

        return view('public.led.wall', [
            'display' => $display,
            'boot' => $this->payload($display),
            'stateUrl' => route('public.led.state', $token),
        ]);
    }

    public function state(string $token): JsonResponse
    {
        return response()->json($this->payload($this->display($token)))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Public state for one match — what the broadcast ticker reads. It used to
     * poll /admin/matches/{id}/state, which a logged-out screen cannot reach,
     * so the ticker sat at 0/0 with no visible error.
     */
    public function matchState(Matches $match): JsonResponse
    {
        abort_if($match->is_published === false && ! auth()->check(), 404);

        return response()->json($this->wall->forMatch($match))->header('Cache-Control', 'no-store');
    }

    private function themeVars(string $theme): ?array
    {
        $t = \App\Services\Poster\Presets\PresetThemes::find($theme);
        if (! $t) {
            return null; // plain dark / light
        }

        return [
            'bg' => $t['from'],
            'panel' => $t['light'] ? '#ffffff' : $t['rowA'],
            'line' => $t['rowB'],
            'ink' => $t['ink'],
            'muted' => $t['muted'],
            'accent' => $t['accent'],
            'image' => \App\Services\Poster\Presets\PresetThemes::publicUrl($theme, 'wide'),
        ];
    }

    private function display(string $token): LedDisplay
    {
        $display = LedDisplay::with(['match', 'tournament'])->where('token', $token)->first();
        abort_if(! $display || $display->isRevoked(), 404);

        return $display;
    }

    private function payload(LedDisplay $display): array
    {
        $now = $this->queue->tick($display);
        $state = $display->match ? $this->wall->forMatch($display->match) : null;

        $secondsLeft = $now?->ends_at ? max(0, now()->diffInMilliseconds($now->ends_at, false)) : null;

        return [
            'display' => [
                'id' => $display->id,
                'name' => $display->name,
                'mode' => $display->mode,
                'frozen' => $display->frozen,
                'theme' => $display->setting('theme'),
                // A design theme (Midnight Gold, Stadium Green…): its colours and wide background.
                'theme_vars' => $this->themeVars((string) $display->setting('theme')),
                'reduced_motion' => (bool) $display->setting('reduced_motion'),
            ],
            'now' => $now ? [
                'item_id' => $now->id,
                'panel' => $now->panel,
                'payload' => $now->payload,
                'ends_in_ms' => $secondsLeft,
            ] : ['item_id' => null, 'panel' => 'scoreboard', 'payload' => null, 'ends_in_ms' => null],
            'state' => $state,
            'tournament' => [
                'name' => $display->tournament?->name,
                'logo' => $display->tournament?->settings?->logo_url ?? $display->tournament?->logo_url,
            ],
            // When to ask again even without a push: when the panel ends, else 15 s.
            'next_check_ms' => $display->frozen ? 15000 : (int) min(15000, max(1000, ($secondsLeft ?? 15000) + 150)),
        ];
    }
}
