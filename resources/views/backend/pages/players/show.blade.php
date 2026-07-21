@extends('backend.layouts.app')

@section('title')
    {{ $player->name }} | Player Profile
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-4xl md:p-6">

        {{-- Breadcrumbs & Actions --}}
        <div class="flex justify-between items-center mb-4">
            <x-breadcrumbs :breadcrumbs="$breadcrumbs" />
            <div class="flex items-center gap-2 flex-wrap">
                @can('player.edit')
                    @if($player->status !== 'approved')
                        <form action="{{ route('admin.players.approve', $player->id) }}" method="POST"
                            onsubmit="return confirm('All fields will be marked as verified and approved. Do you want to proceed?');">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-green-600 hover:bg-green-700 text-white">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Approve
                            </button>
                        </form>
                    @endif
                    @if($player->status !== 'rejected')
                        <form action="{{ route('admin.players.reject', $player->id) }}" method="POST"
                            onsubmit="return confirm('Are you sure you want to reject this player?');">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-orange-600 hover:bg-orange-700 text-white">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Reject
                            </button>
                        </form>
                    @endif
                    @if($player->player_mode !== 'retained' && $player->status === 'approved')
                        <button type="button"
                            onclick="document.getElementById('show-retain-modal').classList.remove('hidden')"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-purple-600 hover:bg-purple-700 text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Retain
                        </button>
                    @endif
                    @if($player->player_mode === 'retained')
                        <form action="{{ route('admin.players.unretain', $player->id) }}" method="POST"
                            onsubmit="return confirm('Are you sure you want to unretain this player?');">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-orange-500 hover:bg-orange-600 text-white">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                                </svg>
                                Unretain
                            </button>
                        </form>
                    @endif
                @endcan
                @can('player.edit')
                    <a href="{{ route('admin.players.edit', $player->id) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15.232 5.232l3.536 3.536M9 11l6.536-6.536a2 2 0 012.828 0l1.172 1.172a2 2 0 010 2.828L13 15l-4 1 1-4z" />
                        </svg>
                        Edit
                    </a>
                @endcan
                @can('player.delete')
                    <form action="{{ route('admin.players.destroy', $player->id) }}" method="POST"
                        onsubmit="return confirm('Are you sure you want to delete this player? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-red-600 hover:bg-red-700 text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl overflow-hidden">

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- Gradient Header                                        --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="p-8 bg-gradient-to-r from-blue-600 to-cyan-700">
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
                    {{-- Player Photo --}}
                    <div class="relative flex-shrink-0">
                        @if($player->image_path)
                            <img src="{{ Storage::url($player->image_path) }}" alt="{{ $player->name }}" class="w-28 h-36 rounded-xl object-cover border-3 border-white/30 shadow-lg">
                        @else
                            <div class="w-28 h-36 rounded-xl bg-white/20 flex items-center justify-center">
                                <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                        @endif
                        @if($verifiedProfile)
                            <span class="absolute -bottom-1 -right-1 flex items-center justify-center w-7 h-7 bg-blue-500 rounded-full ring-2 ring-white shadow" title="Verified Player">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        @endif
                    </div>

                    {{-- Player Info --}}
                    <div class="flex-1 text-center sm:text-left">
                        <h2 class="text-3xl font-bold text-white">{{ $player->name }}</h2>
                        @if($player->jersey_name)
                            <p class="text-white/70 text-sm mt-0.5">{{ $player->jersey_name }}</p>
                        @endif
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mt-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium bg-blue-900/50 text-white">
                                {{ $player->playerType?->type ?? 'Player' }}
                            </span>
                            @if($player->actualTeam)
                                <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium bg-white/20 text-white">
                                    {{ $player->actualTeam->name }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Quick Stats --}}
                    <div class="hidden sm:flex items-center gap-6 ml-auto mr-4 text-center">
                        <div>
                            <div class="text-2xl font-bold text-white">{{ $player->total_matches ?? 0 }}</div>
                            <div class="text-[10px] text-white/60 uppercase tracking-wider">Matches</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-white">{{ $player->total_runs ?? 0 }}</div>
                            <div class="text-[10px] text-white/60 uppercase tracking-wider">Runs</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-white">{{ $player->total_wickets ?? 0 }}</div>
                            <div class="text-[10px] text-white/60 uppercase tracking-wider">Wickets</div>
                        </div>
                    </div>

                    {{-- Status Badge --}}
                    <div class="flex items-start gap-2 mt-2 sm:mt-0">
                        @if($player->status === 'approved')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-400 text-green-900">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Approved
                            </span>
                        @elseif($player->status === 'rejected')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-400 text-red-900">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Rejected
                            </span>
                        @elseif($player->status === 'queued')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-sky-400 text-sky-900">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                                In Queue
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-400 text-yellow-900">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Pending
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- Tournament Context Selector                             --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            @if($registrations->count() > 0)
            <div class="px-6 pt-5 pb-0">
                <div class="flex flex-wrap items-center gap-3">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tournament Context</label>
                    <select onchange="window.location.href='{{ route('admin.players.show', $player->id) }}?tournament=' + this.value"
                            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($registrations as $reg)
                            <option value="{{ $reg->tournament_id }}" @selected($selectedRegistration && $selectedRegistration->id === $reg->id)>
                                {{ $reg->tournament->name ?? 'N/A' }} &mdash; {{ ucfirst($reg->status) }}
                            </option>
                        @endforeach
                    </select>
                    @if($selectedRegistration)
                        <a href="{{ route('admin.tournaments.registrations.show', [$selectedRegistration->tournament_id, $selectedRegistration->id]) }}"
                           class="text-xs text-blue-600 dark:text-blue-400 hover:underline">View Registration &rarr;</a>
                    @endif
                </div>
            </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- Details Body (PlayerFormConfig-driven)                  --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="p-6 space-y-6">

                @php
                    $countries = config('countries.list', []);
                    $visaList = config('registration.visa_statuses', []);
                    $isTeamManagerView = auth()->user()?->hasAnyRole(['Team Manager', 'Team Owner']) && !auth()->user()?->hasAnyRole(['Superadmin', 'Admin', 'Organizer']);
                    $p = $player;

                    $valueFor = function ($key) use ($p, $countries, $visaList, $selectedRegistration) {
                        if (!$p) return null;
                        return match ($key) {
                            'first_name' => $p->first_name,
                            'last_name' => $p->last_name,
                            'email' => $p->email,
                            'date_of_birth' => $p->date_of_birth ? \Illuminate\Support\Carbon::parse($p->date_of_birth)->format('d M Y') : null,
                            'mobile_number' => $p->mobile_number_full,
                            'cricheroes_number' => $p->cricheroes_number_full,
                            'cricheroes_profile_url' => $p->cricheroes_profile_url,
                            'country' => $p->country ? ($countries[$p->country] ?? $p->country) : null,
                            'state' => $p->state,
                            'location' => $p->location?->name,
                            'registration_team' => $p->team?->name === 'Others' ? ($p->team_name_ref ?? 'Others') : $p->team?->name,
                            'playing_team' => $p->actualTeam?->name ?? $p->playing_team_name_ref,
                            'visa_status' => $p->visa_status ? ($visaList[$p->visa_status] ?? $p->visa_status) : null,
                            'visa_expiry' => $p->visa_expiry ? \Illuminate\Support\Carbon::parse($p->visa_expiry)->format('d M Y') : null,
                            'employer_name' => $p->employer_name,
                            'employer_address' => $p->employer_address,
                            'employer_position' => $p->employer_position,
                            'available_saturday' => is_null($p->available_saturday) ? null : ($p->available_saturday ? 'Yes' : 'No'),
                            'available_sunday' => is_null($p->available_sunday) ? null : ($p->available_sunday ? 'Yes' : 'No'),
                            'played_ys_ipl_s1' => is_null($p->played_ys_ipl_s1) ? null : ($p->played_ys_ipl_s1 ? 'Yes' : 'No'),
                            'jersey_name' => $p->jersey_name,
                            'jersey_number' => $p->jersey_number,
                            'tshirt_size' => $p->tshirt_size,
                            'pant_size' => $p->pant_size,
                            'player_type' => $p->playerType?->name ?? $p->playerType?->type,
                            'batting_profile' => $p->battingProfile?->name ?? $p->battingProfile?->style,
                            'batting_mode' => $p->batting_mode,
                            'preferred_batting_position' => is_array($p->preferred_batting_positions) ? implode(', ', $p->preferred_batting_positions) : $p->preferred_batting_positions,
                            'bowling_profile' => $p->bowlingProfile?->name ?? $p->bowlingProfile?->style,
                            'is_wicket_keeper' => is_null($p->is_wicket_keeper) ? null : ($p->is_wicket_keeper ? 'Yes' : 'No'),
                            'total_matches' => $p->total_matches,
                            'total_runs' => $p->total_runs,
                            'total_wickets' => $p->total_wickets,
                            'transportation' => is_null($p->transportation_required) ? null : ($p->transportation_required ? 'Transportation Required' : 'Self Transportation'),
                            'travel_plan' => $p->no_travel_plan ? 'No' : (($p->travel_date_from || $p->travel_date_to) ? 'Yes — ' . trim(($p->travel_date_from ? \Illuminate\Support\Carbon::parse($p->travel_date_from)->format('d M Y') : '') . ' – ' . ($p->travel_date_to ? \Illuminate\Support\Carbon::parse($p->travel_date_to)->format('d M Y') : ''), ' –') : (is_null($p->no_travel_plan) ? null : 'Yes')),
                            default => null,
                        };
                    };
                    $skip = ['name', 'image', 'terms_and_conditions'];

                    // --- Verification summary computation ---
                    $summaryTotal = 0;
                    $summaryVerified = 0;
                    $summarySections = [];

                    if ($p && $p->image_path) {
                        $summaryTotal++;
                        $imgV = in_array('image', $verifiedFields, true);
                        if ($imgV) $summaryVerified++;
                        $summarySections['Photo'] = $imgV ? ['pending' => []] : ['pending' => ['Photo']];
                    }

                    foreach ($layout as $sec) {
                        $secPending = [];
                        foreach ($sec['fields'] as $fk) {
                            if (in_array($fk, $skip, true)) continue;
                            $summaryTotal++;
                            if (in_array($fk, $verifiedFields, true)) {
                                $summaryVerified++;
                            } else {
                                $secPending[] = $fieldConfig[$fk]['label'] ?? $fk;
                            }
                        }
                        $secCustom = $customFields->where('section', $sec['key']);
                        foreach ($secCustom as $scf) {
                            $summaryTotal++;
                            if (in_array('cf_' . $scf->id, $verifiedFields, true)) {
                                $summaryVerified++;
                            } else {
                                $secPending[] = $scf->label;
                            }
                        }
                        if ($summaryTotal > 0 || count($secPending) > 0) {
                            $summarySections[$sec['title']] = ['pending' => $secPending];
                        }
                    }
                    $summaryPending = $summaryTotal - $summaryVerified;
                    $summaryPct = $summaryTotal > 0 ? round(($summaryVerified / $summaryTotal) * 100) : 0;
                @endphp

                {{-- Verification Summary Panel --}}
                @if($selectedRegistration && $summaryTotal > 0)
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden" x-data="{ open: false }">
                    <div class="px-4 py-3 flex items-center justify-between gap-3 cursor-pointer" @click="open = !open">
                        <div class="flex items-center gap-3 min-w-0">
                            @php
                                $svgRadius = 18;
                                $svgCircum = 2 * 3.14159 * $svgRadius;
                                $svgOffset = $svgCircum - ($summaryPct / 100) * $svgCircum;
                                $svgColor = $summaryPct === 100 ? '#22c55e' : ($summaryPct > 70 ? '#eab308' : ($summaryPct > 40 ? '#f97316' : '#ef4444'));
                            @endphp
                            <div class="flex-shrink-0">
                                <svg width="46" height="46" viewBox="0 0 46 46">
                                    <circle cx="23" cy="23" r="{{ $svgRadius }}" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                                    <circle cx="23" cy="23" r="{{ $svgRadius }}" fill="none" stroke="{{ $svgColor }}" stroke-width="3"
                                        stroke-dasharray="{{ $svgCircum }}" stroke-dashoffset="{{ $svgOffset }}"
                                        stroke-linecap="round" transform="rotate(-90 23 23)"/>
                                    <text x="23" y="23" text-anchor="middle" dominant-baseline="central"
                                        class="fill-gray-700 dark:fill-gray-200" style="font-size: 11px; font-weight: 600;">{{ $summaryPct }}%</text>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $summaryVerified }} of {{ $summaryTotal }} fields verified
                                </p>
                                @if($summaryPending > 0)
                                    <p class="text-xs text-amber-600 dark:text-amber-400">{{ $summaryPending }} field{{ $summaryPending > 1 ? 's' : '' }} pending</p>
                                @else
                                    <p class="text-xs text-green-600 dark:text-green-400">All fields verified</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="hidden sm:block w-32 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div class="h-full rounded-full {{ $summaryPct === 100 ? 'bg-green-500' : ($summaryPct > 70 ? 'bg-yellow-500' : ($summaryPct > 40 ? 'bg-orange-500' : 'bg-red-500')) }}" style="width: {{ $summaryPct }}%"></div>
                            </div>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    <div x-show="open" x-collapse x-cloak class="px-4 pb-4 border-t border-gray-100 dark:border-gray-800">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-3">
                            @foreach($summarySections as $secTitle => $secData)
                                <div class="flex items-start gap-2 text-xs">
                                    @if(empty($secData['pending']))
                                        <svg class="w-3.5 h-3.5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        <span class="text-green-700 dark:text-green-400 font-medium">{{ $secTitle }}</span>
                                    @else
                                        <svg class="w-3.5 h-3.5 text-amber-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                                        <div>
                                            <span class="text-amber-700 dark:text-amber-400 font-medium">{{ $secTitle }}</span>
                                            <span class="text-gray-400 dark:text-gray-500"> &mdash; {{ implode(', ', $secData['pending']) }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                {{-- Player Photo with verification status --}}
                @if($player->image_path)
                @php $photoVerified = in_array('image', $verifiedFields, true); @endphp
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ $selectedRegistration ? ($photoVerified ? 'border-green-400 dark:border-green-600' : 'border-orange-300 dark:border-orange-600') : 'border-transparent' }} inline-block">
                    <div class="flex items-start gap-4">
                        <img src="{{ Storage::url($player->image_path) }}" alt="{{ $player->name }}" class="w-28 h-36 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col gap-1">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Player Photo</h4>
                            @if($selectedRegistration)
                                @if($photoVerified)
                                    <span class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        Verified
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs text-orange-600 dark:text-orange-400">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                                        Pending
                                    </span>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                {{-- Dynamic Field Sections (driven by PlayerFormConfig) --}}
                @foreach($layout as $section)
                    @php
                        $rows = [];
                        foreach ($section['fields'] as $key) {
                            if (in_array($key, $skip, true)) continue;
                            if ($isTeamManagerView && $key === 'email') continue;
                            $rows[$key] = $valueFor($key);
                        }
                        $sectionCustom = $customFields->where('section', $section['key']);
                    @endphp
                    @if(count($rows) || $sectionCustom->count())
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">{{ $section['title'] }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($rows as $key => $value)
                            @php
                                $isVerified = in_array($key, $verifiedFields, true);
                                $isEmpty = ($value === null || $value === '');
                                $hasBorder = $selectedRegistration !== null;
                            @endphp
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ $hasBorder ? ($isVerified ? 'border-green-400 dark:border-green-600' : 'border-orange-300 dark:border-orange-600') : 'border-transparent' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        {{ $fieldConfig[$key]['label'] ?? $key }}
                                    </h4>
                                    @if($isVerified)
                                        <span class="text-green-500" title="Verified">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                                @if($isEmpty)
                                    <p class="mt-1 text-sm italic text-gray-400 dark:text-gray-500">Not provided</p>
                                @elseif($key === 'cricheroes_profile_url')
                                    <a href="{{ $value }}" target="_blank" class="mt-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline break-all">{{ $value }}</a>
                                @elseif($key === 'playing_team')
                                    <div class="mt-1">
                                        <p class="text-sm text-gray-900 dark:text-white break-words">{{ $value }}</p>
                                        @if($player->actualTeam?->tournament)
                                            <span class="inline-flex items-center mt-1 px-1.5 py-0.5 rounded text-[10px] font-medium {{ $player->actualTeam->tournament->isAuction() ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' }}">
                                                {{ ucfirst($player->actualTeam->tournament->type) }}
                                            </span>
                                        @elseif($player->playing_team_name_ref)
                                            <span class="inline-flex items-center mt-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">Others</span>
                                        @endif
                                    </div>
                                @else
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white break-words">{{ $value }}</p>
                                @endif
                            </div>
                            @endforeach

                            {{-- Custom fields for this section --}}
                            @foreach($sectionCustom as $cf)
                                @php
                                    $cfKey = 'cf_' . $cf->id;
                                    $cfVal = $customValues[$cfKey] ?? null;
                                    if ($cf->type === 'checkbox') { $cfVal = ($cfVal === '1' || $cfVal === 1) ? 'Yes' : (($cfVal === '0' || $cfVal === 0) ? 'No' : null); }
                                    $cfEmpty = ($cfVal === null || $cfVal === '');
                                    $cfVerified = in_array($cfKey, $verifiedFields, true);
                                @endphp
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ $cfVerified ? 'border-green-400 dark:border-green-600' : 'border-orange-300 dark:border-orange-600' }}">
                                    <div class="flex items-start justify-between gap-2">
                                        <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            {{ $cf->label }}
                                            <span class="ml-1 text-[9px] normal-case font-normal text-indigo-400">custom</span>
                                        </h4>
                                        @if($cfVerified)
                                            <span class="text-green-500" title="Verified">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        @endif
                                    </div>
                                    @if($cfEmpty)
                                        <p class="mt-1 text-sm italic text-gray-400 dark:text-gray-500">Not provided</p>
                                    @else
                                        <p class="mt-1 text-sm text-gray-900 dark:text-white break-words">{{ $cfVal }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                @endforeach

                {{-- Section: Player Mode & Team --}}
                @if($player->status === 'approved')
                @if($isTeamManagerView)
                @if($player->actualTeam)
                @php $tmIsAuction = $player->actualTeam->tournament?->isAuction(); @endphp
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">{{ $tmIsAuction ? 'Team & Retention' : 'Team' }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Team</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $player->actualTeam->name }}</p>
                        </div>
                        @if($tmIsAuction && $player->player_mode === 'retained' && $player->retained_value)
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Retained Value</h4>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($player->retained_value) }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
                @else
                @if($player->actualTeam)
                @php $isAuctionTournament = $player->actualTeam->tournament?->isAuction(); @endphp
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">{{ $isAuctionTournament ? 'Player Mode & Team' : 'Team' }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @if($isAuctionTournament)
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Player Mode</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ ucfirst($player->player_mode ?? 'Normal') }}</p>
                        </div>
                        @endif
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Team</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $player->actualTeam->name }}</p>
                        </div>
                        @if($isAuctionTournament && $player->player_mode === 'retained')
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Retained Value</h4>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $player->retained_value ? number_format($player->retained_value) : '—' }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
                @endif
                @endif

                {{-- Change History --}}
                @if(isset($changeLogs) && $changeLogs->count())
                <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Change History</h4>
                    <div class="space-y-3">
                        @foreach($changeLogs as $cl)
                            @php
                                $clColors = [
                                    'submitted' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                    'approved' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                    'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                    'admin_edit' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                    'verified' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
                                ];
                                $clFormatted = $cl->changes ? \App\Models\ProfileChangeLog::formatChangesForDisplay($cl->changes) : [];
                            @endphp
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700" x-data="{ open: false }">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $clColors[$cl->action] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ ucfirst(str_replace('_', ' ', $cl->action)) }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            by {{ $cl->changedBy?->name ?? 'System' }}
                                        </span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ $cl->created_at->format('d M Y, H:i') }}
                                        </span>
                                    </div>
                                    @if(count($clFormatted))
                                        <button type="button" @click="open = !open" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                                            <span x-text="open ? 'Hide' : '{{ count($clFormatted) }} field(s)'"></span>
                                        </button>
                                    @endif
                                </div>
                                @if($cl->notes)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 italic">{{ $cl->notes }}</p>
                                @endif
                                @if(count($clFormatted))
                                <div x-show="open" x-cloak class="mt-2 text-xs space-y-1">
                                    @foreach($clFormatted as $clLabel => $clVal)
                                        <div class="flex gap-2">
                                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $clLabel }}:</span>
                                            <span class="text-gray-500 dark:text-gray-400">{{ Str::limit($clVal, 60) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- TOURNAMENT STATISTICS (Full Width)                     --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if($tournamentAssignments->count() > 0)
            <div class="mt-8 space-y-6" x-data="{ openTab: {{ $tournamentAssignments->first()->tournament_id }} }">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white">Tournament Statistics</h2>

                {{-- Tournament Tabs --}}
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex flex-wrap gap-2 -mb-px">
                        @foreach($tournamentAssignments as $assignment)
                            <button type="button"
                                @click="openTab = {{ $assignment->tournament_id }}"
                                :class="openTab === {{ $assignment->tournament_id }}
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
                                class="inline-flex items-center gap-2 px-4 py-3 border-b-2 font-medium text-sm transition-colors">
                                {{ $assignment->tournament_name }}
                                <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                    {{ $assignment->team_name }}
                                </span>
                                @if($assignment->role)
                                    <span class="px-2 py-0.5 rounded-full text-xs bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-300">
                                        {{ ucfirst($assignment->role) }}
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </nav>
                </div>

                {{-- Tab Content --}}
                @foreach($tournamentAssignments as $assignment)
                    @php $stats = $tournamentStats->get($assignment->tournament_id); @endphp
                    <div x-show="openTab === {{ $assignment->tournament_id }}"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-cloak>
                        @if($stats)
                            {{-- Quick Stats Row --}}
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats->matches }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Matches</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats->runs }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Runs</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats->wickets }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Wickets</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats->catches + $stats->stumpings + $stats->run_outs }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Dismissals (Field)</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                {{-- Batting Stats --}}
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        <h3 class="font-semibold text-gray-800 dark:text-white">Batting</h3>
                                    </div>
                                    <div class="p-4">
                                        <table class="w-full text-sm">
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                @foreach([
                                                    'Innings' => $stats->innings_batted,
                                                    'Runs' => $stats->runs,
                                                    'Balls Faced' => $stats->balls_faced,
                                                    'Highest Score' => $stats->highest_score_display,
                                                    'Average' => $stats->batting_average,
                                                    'Strike Rate' => $stats->strike_rate,
                                                    '4s / 6s' => $stats->fours . ' / ' . $stats->sixes,
                                                    '50s / 100s' => $stats->fifties . ' / ' . $stats->hundreds,
                                                    'Not Outs' => $stats->not_outs,
                                                    'Ducks' => $stats->ducks,
                                                ] as $label => $value)
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $label }}</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $value }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {{-- Bowling Stats --}}
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <h3 class="font-semibold text-gray-800 dark:text-white">Bowling</h3>
                                    </div>
                                    <div class="p-4">
                                        <table class="w-full text-sm">
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                @foreach([
                                                    'Innings' => $stats->innings_bowled,
                                                    'Overs' => $stats->overs_bowled,
                                                    'Wickets' => $stats->wickets,
                                                    'Runs Conceded' => $stats->runs_conceded,
                                                    'Best Bowling' => $stats->best_bowling ?? '-',
                                                    'Average' => $stats->bowling_average,
                                                    'Economy' => $stats->economy_rate,
                                                    'Maidens' => $stats->maidens,
                                                    '4W / 5W' => $stats->four_wickets . ' / ' . $stats->five_wickets,
                                                ] as $label => $value)
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $label }}</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $value }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {{-- Fielding Stats --}}
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg lg:col-span-2">
                                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"/>
                                        </svg>
                                        <h3 class="font-semibold text-gray-800 dark:text-white">Fielding</h3>
                                    </div>
                                    <div class="p-4">
                                        <div class="grid grid-cols-3 gap-6 text-center">
                                            <div>
                                                <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ $stats->catches }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Catches</p>
                                            </div>
                                            <div>
                                                <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ $stats->stumpings }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Stumpings</p>
                                            </div>
                                            <div>
                                                <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ $stats->run_outs }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Run Outs</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 text-center">
                                <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <p class="text-gray-500 dark:text-gray-400">No statistics recorded for this tournament yet.</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Retain Player Modal --}}
    @if($player->player_mode !== 'retained' && $player->status === 'approved')
        <div id="show-retain-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('show-retain-modal').classList.add('hidden')"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md z-10">
                <form method="POST" action="{{ route('admin.players.retain', $player->id) }}">
                    @csrf
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Retain Player
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Retaining <span class="font-medium text-gray-700 dark:text-gray-300">{{ $player->name }}</span></p>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Team <span class="text-red-500">*</span></label>
                            <select name="actual_team_id" required class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                                <option value="">-- Select Team --</option>
                                @foreach($actualTeams as $at)
                                    <option value="{{ $at->id }}" @selected($player->actual_team_id == $at->id)>{{ $at->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Retained Value <span class="text-red-500">*</span></label>
                            <input type="number" name="retained_value" required min="0" step="any" placeholder="e.g. 500000" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                        <button type="button" onclick="document.getElementById('show-retain-modal').classList.add('hidden')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                            Retain Player
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
