{{--
    A player's travel plan, as one line with a flight icon.

    The LABEL comes from Player::getTravelPlanLabelAttribute() rather than being assembled here,
    so the pools screen, the organizer panel, the LED wall, the squad views and the downloaded
    card cannot answer the same question five different ways. That accessor also settles the
    awkward cases: `no_travel_plan` wins over dates left behind by an earlier edit, one date is a
    legitimate answer ("from 12 Mar" — somebody who knows when they arrive but not when they
    leave), and a player who asked for transport but gave no dates still says something, because
    that is the fact an organizer is chasing.

    Renders nothing at all when there is nothing to say, so it is safe to drop into a row.

    @param  \App\Models\Player|null  $player
    @param  string  $size  'xs' in dense lists, 'sm' elsewhere
--}}
@props([
    'player' => null,
    'size' => 'xs',
])

@php
    $label = $player?->travel_plan_label;
@endphp

@if ($label)
    <span {{ $attributes->merge([
        'class' => 'inline-flex items-center gap-1 whitespace-nowrap text-sky-600 dark:text-sky-400 '
            . ($size === 'sm' ? 'text-sm' : 'text-[10px]'),
        'title' => 'Travel plan: ' . $label,
    ]) }}>
        {{-- The same paper-plane the panel and the wall use. --}}
        <svg class="{{ $size === 'sm' ? 'w-4 h-4' : 'w-3 h-3' }} shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
        </svg>
        <span>{{ $label }}</span>
    </span>
@endif
