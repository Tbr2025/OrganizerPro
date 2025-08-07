@extends('backend.layouts.app')

@section('title')
    View Player | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-5 space-y-6">
            <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <x-display.label-value label="Name" :value="$player->name" />
                <x-display.label-value label="Team" :value="$player->team->name ?? '—'" />
                <x-display.label-value label="Jersey Name" :value="$player->jersey_name" />
                <x-display.label-value label="Jersey Size" :value="$player->kitSize->size ?? '—'" />
                <x-display.label-value label="Cricheroes No" :value="$player->cricheroes_no" />
                <x-display.label-value label="Mobile No" :value="$player->mobile_no" />
                <x-display.label-value label="Batting Profile" :value="$player->battingProfile->style ?? '—'" />
                <x-display.label-value label="Bowling Profile" :value="$player->bowlingProfile->style ?? '—'" />
                <x-display.label-value label="Player Type" :value="$player->playerType->type ?? '—'" />
                <x-display.label-value label="Wicket Keeper" :value="$player->is_wicket_keeper ? 'Yes' : 'No'" />
                <x-display.label-value label="Transportation Required" :value="$player->transportation_required ? 'Yes' : 'No'" />
                <x-display.label-value label="Created By" :value="$player->creator->name ?? '—'" />
            </dl>

            <div class="mt-6">
                <a href="{{ route('admin.players.edit', $player->id) }}" class="btn btn-primary">Edit</a>
                <a href="{{ route('admin.players.index') }}" class="btn btn-secondary">Back</a>
            </div>
        </div>
    </div>
@endsection
