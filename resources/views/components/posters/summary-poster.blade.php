@props([
    'match',
    'template' => 'classic',
    'scale' => 0.4,
])

@php
    $result = $match->result;
    $tournament = $match->tournament;
    $settings = $tournament?->settings;
    $primaryColor = $settings?->primary_color ?? '#1a1a2e';
    $secondaryColor = $settings?->secondary_color ?? '#fbbf24';
    $teamA = $match->teamA;
    $teamB = $match->teamB;
    $teamAWinner = $match->winner_team_id === $match->team_a_id;
    $teamBWinner = $match->winner_team_id === $match->team_b_id;

    $motmAward = $match->matchAwards->first(function($award) {
        return $award->tournamentAward && str_contains(strtolower($award->tournamentAward->name), 'man of the match');
    });

    // For full size (scale=1), set explicit dimensions for html2canvas
    // For scaled preview, use CSS transform with explicit container dimensions for proper layout
    $scaleStyle = $scale == 1
        ? 'width: 1080px; height: 1350px; overflow: hidden;'
        : "transform: scale({$scale}); transform-origin: top left;";
@endphp

<div class="poster-container" style="{{ $scaleStyle }}">
    @switch($template)
        @case('modern')
            @include('components.posters.templates.summary-modern', compact('match', 'result', 'tournament', 'settings', 'primaryColor', 'secondaryColor', 'teamA', 'teamB', 'teamAWinner', 'teamBWinner', 'motmAward'))
            @break
        @case('minimal')
            @include('components.posters.templates.summary-minimal', compact('match', 'result', 'tournament', 'settings', 'primaryColor', 'secondaryColor', 'teamA', 'teamB', 'teamAWinner', 'teamBWinner', 'motmAward'))
            @break
        @case('gradient')
            @include('components.posters.templates.summary-gradient', compact('match', 'result', 'tournament', 'settings', 'primaryColor', 'secondaryColor', 'teamA', 'teamB', 'teamAWinner', 'teamBWinner', 'motmAward'))
            @break
        @case('dark')
            @include('components.posters.templates.summary-dark', compact('match', 'result', 'tournament', 'settings', 'primaryColor', 'secondaryColor', 'teamA', 'teamB', 'teamAWinner', 'teamBWinner', 'motmAward'))
            @break
        @default
            @include('components.posters.templates.summary-classic', compact('match', 'result', 'tournament', 'settings', 'primaryColor', 'secondaryColor', 'teamA', 'teamB', 'teamAWinner', 'teamBWinner', 'motmAward'))
    @endswitch
</div>
