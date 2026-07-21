@extends('backend.layouts.app')

@section('title', 'Requested Changes')

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('admin.dashboard')],
    ['name' => 'Players', 'route' => route('admin.players.index')],
    ['name' => 'Requested Changes']
]" />

<div class="p-4 mx-auto max-w-7xl md:p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Requested Changes</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Players whose fields were flagged for correction by admin</p>
        </div>
        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300">
            {{ $totalRequested }} pending
        </span>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalReviewed }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Reviewed</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $totalApproved }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Approved</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $totalFullyVerified }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">100% Verified</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $totalPartial }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Pending Fields</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mb-6 border border-gray-100 dark:border-gray-700">
        <form method="GET" action="{{ route('admin.requested-changes.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px] max-w-xs">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Search Player</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Player name or email..."
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="min-w-[180px]">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Tournament</label>
                <select name="tournament_id" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Tournaments</option>
                    @foreach($tournaments as $t)
                        <option value="{{ $t->id }}" {{ (string) request('tournament_id') === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Filter
            </button>
            @if(request()->hasAny(['search', 'tournament_id']))
                <a href="{{ route('admin.requested-changes.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Clear</a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden border border-gray-100 dark:border-gray-700">
        @if($registrations->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Player</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tournament</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Verified</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden md:table-cell">Reviewed</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden lg:table-cell">Fields to Correct</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($registrations as $reg)
                            @php
                                $player = $reg->player;
                                $verifiedFields = (array) ($reg->verified_fields ?? []);

                                // Get tournament settings for field config
                                $settings = $reg->tournament?->settings;
                                $fieldConfig = \App\Helpers\PlayerFormConfig::getFieldConfig($settings);
                                $layout = \App\Helpers\PlayerFormConfig::getFormLayout($settings, false);

                                // Collect all form fields and compute verification counts
                                $skip = ['name', 'image', 'terms_and_conditions'];
                                $allFields = [];
                                $vTotal = 0; $vDone = 0;
                                if ($player?->image_path) { $vTotal++; if (in_array('image', $verifiedFields, true)) $vDone++; }
                                foreach ($layout as $sec) {
                                    foreach ($sec['fields'] as $fk) {
                                        if (in_array($fk, $skip)) continue;
                                        $allFields[] = $fk;
                                        $vTotal++;
                                        if (in_array($fk, $verifiedFields, true)) $vDone++;
                                    }
                                    $secCustom = $reg->tournament?->customFields?->where('form', 'player')->where('visible', true)->where('section', $sec['key']) ?? collect();
                                    foreach ($secCustom as $scf) {
                                        $vTotal++;
                                        if (in_array('cf_' . $scf->id, $verifiedFields, true)) $vDone++;
                                    }
                                }
                                $vPct = $vTotal > 0 ? round(($vDone / $vTotal) * 100) : 0;
                                $unverifiedFields = array_filter($allFields, fn($f) => !in_array($f, $verifiedFields));
                                $unverifiedLabels = array_map(fn($f) => $fieldConfig[$f]['label'] ?? ucwords(str_replace('_', ' ', $f)), $unverifiedFields);
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        @if($player?->image_path)
                                            <img src="{{ asset('storage/' . $player->image_path) }}" alt="" class="w-9 h-9 rounded-full object-cover">
                                        @else
                                            <div class="w-9 h-9 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-sm font-bold text-gray-500">
                                                {{ strtoupper(substr($player?->name ?? '?', 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $player?->name ?? 'Unknown' }}</div>
                                            <div class="flex flex-wrap gap-1 mt-0.5">
                                                @if($player?->playerType?->type)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ $player->playerType->type }}</span>
                                                @endif
                                                @if($player?->is_wicket_keeper)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">WK</span>
                                                @endif
                                                @if($player?->battingProfile?->style)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ $player->battingProfile->style }}</span>
                                                @endif
                                                @if($player?->bowlingProfile?->style)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ $player->bowlingProfile->style }}</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $player?->email ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ $reg->tournament?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @php
                                        $radius = 16;
                                        $circumference = 2 * 3.14159 * $radius;
                                        $offset = $circumference - ($vPct / 100) * $circumference;
                                        $color = $vPct === 100 ? '#22c55e' : ($vPct > 70 ? '#eab308' : ($vPct > 40 ? '#f97316' : '#ef4444'));
                                    @endphp
                                    <div class="flex items-center justify-center">
                                        <svg width="42" height="42" viewBox="0 0 42 42">
                                            <circle cx="21" cy="21" r="{{ $radius }}" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                                            <circle cx="21" cy="21" r="{{ $radius }}" fill="none" stroke="{{ $color }}" stroke-width="3"
                                                stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}"
                                                stroke-linecap="round" transform="rotate(-90 21 21)"/>
                                            <text x="21" y="21" text-anchor="middle" dominant-baseline="central"
                                                class="fill-gray-700 dark:fill-gray-200" style="font-size: 10px; font-weight: 600;">{{ $vPct }}%</text>
                                        </svg>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 hidden md:table-cell">
                                    {{ $reg->updated_at->format('d M Y') }}
                                    <div class="text-xs">{{ $reg->updated_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($unverifiedLabels as $label)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300">
                                                {{ $label }}
                                            </span>
                                        @endforeach
                                        @if(empty($unverifiedLabels))
                                            <span class="text-xs text-green-600 dark:text-green-400">All verified</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <a href="{{ route('admin.tournaments.registrations.show', [$reg->tournament_id, $reg->id]) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No requested changes</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No players have been asked to correct their fields.</p>
            </div>
        @endif
    </div>

    @if($registrations->hasPages())
        <div class="mt-4">
            {{ $registrations->links() }}
        </div>
    @endif
</div>
@endsection
