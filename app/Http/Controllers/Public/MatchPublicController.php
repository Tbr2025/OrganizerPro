<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Matches;
use Illuminate\View\View;

class MatchPublicController extends Controller
{
    /**
     * Show match detail page
     */
    public function show(Matches $match): View
    {
        $match->load([
            'tournament.settings',
            'teamA.players.player',
            'teamB.players.player',
            'ground',
            'group',
            'result',
            'matchAwards.player',
            'matchAwards.tournamentAward',
            'appreciations.player',
        ]);

        $tournament = $match->tournament;

        // Get other matches in the tournament for navigation
        $otherMatches = $tournament->matches()
            ->with(['teamA', 'teamB'])
            ->where('id', '!=', $match->id)
            ->where('is_cancelled', false)
            ->orderBy('match_date')
            ->limit(5)
            ->get();

        return view('public.match.show', [
            'match' => $match,
            'tournament' => $tournament,
            'otherMatches' => $otherMatches,
        ]);
    }

    /**
     * Show match poster/flyer page (shareable)
     */
    public function poster(Matches $match): View
    {
        $match->load([
            'tournament.settings',
            'teamA',
            'teamB',
            'ground',
        ]);

        return view('public.match.poster', [
            'match' => $match,
            'tournament' => $match->tournament,
        ]);
    }

    /**
     * Show match summary page (shareable)
     */
    public function summary(Matches $match): View
    {
        $match->load([
            'tournament.settings',
            'teamA',
            'teamB',
            'result',
            'matchAwards.player',
            'matchAwards.tournamentAward',
        ]);

        if (!$match->result) {
            abort(404, 'Match result not available yet.');
        }

        return view('public.match.summary', [
            'match' => $match,
            'tournament' => $match->tournament,
            'result' => $match->result,
        ]);
    }

    /**
     * Show scorecard page
     */
    public function scorecard(Matches $match): View
    {
        $match->load([
            'tournament.settings',
            'teamA.players.player',
            'teamB.players.player',
            'result',
            'balls.bowler.player',
            'balls.batsman.player',
        ]);

        // Group balls by innings/team
        $balls = $match->balls()->with(['bowler.player', 'batsman.player'])->get();

        return view('public.match.scorecard', [
            'match' => $match,
            'tournament' => $match->tournament,
            'balls' => $balls,
        ]);
    }

    /**
     * Live Match Ticker Display (1920x1080 Broadcast Overlay)
     */
    public function liveTicker(Matches $match): View
    {
        $match->load([
            'tournament.organization',
            'teamA.players.player',
            'teamB.players.player',
        ]);

        return view('public.match.live-ticker', compact('match'));
    }
}
