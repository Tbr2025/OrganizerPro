@extends('backend.layouts.app')

@section('title')
    {{ $user->name }} | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-4xl md:p-6">

        {{-- Breadcrumbs & Actions --}}
        <div class="flex justify-between items-center mb-4">
            <x-breadcrumbs :breadcrumbs="$breadcrumbs" />
            <div class="flex items-center gap-2">
                @if (auth()->user()->canBeModified($user))
                    <a href="{{ route('admin.users.edit', $user->id) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15.232 5.232l3.536 3.536M9 11l6.536-6.536a2 2 0 012.828 0l1.172 1.172a2 2 0 010 2.828L13 15l-4 1 1-4z" />
                        </svg>
                        Edit
                    </a>
                @endif
                @if($player)
                    <a href="{{ route('admin.players.show', $player->id) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-blue-300 dark:border-blue-600 text-blue-700 dark:text-blue-200 hover:bg-blue-50 dark:hover:bg-blue-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Player Profile
                    </a>
                @endif
            </div>
        </div>

        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl overflow-hidden">

            {{-- Gradient Header --}}
            <div class="p-6 {{ $player ? 'bg-gradient-to-r from-blue-600 to-cyan-700' : 'bg-gradient-to-r from-indigo-600 to-purple-700' }}">
                <div class="flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        @if($player && $player->image_path)
                            <img src="{{ Storage::url($player->image_path) }}" alt="{{ $user->name }}" class="w-16 h-16 rounded-full object-cover border-2 border-white/30">
                        @else
                            <img src="{{ $user->getGravatarUrl(64) }}" alt="{{ $user->name }}" class="w-16 h-16 rounded-full object-cover border-2 border-white/30">
                        @endif
                        <div>
                            <h2 class="text-2xl font-bold text-white">{{ $user->name }}</h2>
                            <p class="text-white/80 text-sm">{{ '@' . $user->username }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                @foreach($user->roles as $role)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-white/20 text-white">
                                        {{ $role->name }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @if($player)
                        <div class="flex flex-wrap items-center gap-2">
                            @if($verifiedProfile)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-400 text-green-900">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Fields Verified
                                </span>
                            @endif

                            {{-- Per-tournament registration status (source of truth) --}}
                            @if($registrations->count())
                                @foreach($registrations as $reg)
                                    @php
                                        $regStatusColor = match($reg->status) {
                                            'approved' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                            'queued' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                                            default => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $regStatusColor }}">
                                        {{ $reg->tournament?->name ?? 'Tournament' }}: {{ ucfirst($reg->status) }}
                                    </span>
                                @endforeach
                            @else
                                {{-- Fallback to player.status when no registrations exist --}}
                                @php
                                    $statusColor = match($player->status) {
                                        'approved' => 'bg-green-400 text-green-900',
                                        'rejected' => 'bg-red-400 text-red-900',
                                        default => 'bg-yellow-400 text-yellow-900',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusColor }}">
                                    Player: {{ ucfirst($player->status ?? 'pending') }}
                                </span>
                            @endif

                            {{-- Role tags --}}
                            @foreach($user->roles as $role)
                                @if(in_array($role->name, ['Team Manager', 'Admin', 'Superadmin']))
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400">
                                        {{ $role->name }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Details Body --}}
            <div class="p-6 space-y-6">

                {{-- ═══════════════════════════════════════════════════════ --}}
                {{-- Section: Account Information (always shown)           --}}
                {{-- ═══════════════════════════════════════════════════════ --}}
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Account Information</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</h4>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $user->name }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Username</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $user->username }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $user->email }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Roles</h4>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @forelse($user->roles as $role)
                                    @php
                                        $roleColors = [
                                            'Superadmin' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                            'Admin' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
                                            'Organizer' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                            'Team Manager' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
                                            'Player' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                        ];
                                        $colors = $roleColors[$role->name] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $colors }}">{{ $role->name }}</span>
                                @empty
                                    <p class="text-sm italic text-gray-400 dark:text-gray-500">No roles assigned</p>
                                @endforelse
                            </div>
                        </div>
                        @if($user->organization)
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Organization</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $user->organization->name }}</p>
                        </div>
                        @endif
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email Verified</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $user->email_verified_at ? $user->email_verified_at->format('d M Y, H:i') : 'Not verified' }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Registered</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $user->created_at->format('d M Y, H:i') }}</p>
                        </div>
                    </div>
                </div>

                @if($player)
                    {{-- ═══════════════════════════════════════════════════════ --}}
                    {{-- PLAYER PROFILE SECTIONS                               --}}
                    {{-- ═══════════════════════════════════════════════════════ --}}

                    @php
                        $countries = config('countries.list', []);
                        $visaList = config('registration.visa_statuses', []);
                    @endphp

                    {{-- Player Photo --}}
                    @if($player->image_path)
                    <div>
                        <img src="{{ Storage::url($player->image_path) }}" alt="{{ $player->name }}" class="w-28 h-36 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                    </div>
                    @endif

                    {{-- Section: Basic Information --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Basic Information</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @php
                                $basicFields = [
                                    ['label' => 'First Name', 'value' => $player->first_name, 'verified' => $player->verified_name ?? false],
                                    ['label' => 'Last Name', 'value' => $player->last_name, 'verified' => $player->verified_name ?? false],
                                    ['label' => 'Email', 'value' => $player->email, 'verified' => $player->verified_email ?? false],
                                    ['label' => 'Date of Birth', 'value' => $player->date_of_birth ? \Carbon\Carbon::parse($player->date_of_birth)->format('d M Y') : null],
                                    ['label' => 'Nationality', 'value' => $player->country ? ($countries[$player->country] ?? $player->country) : null, 'verified' => $player->verified_country ?? false],
                                    ['label' => 'State / Province', 'value' => $player->state],
                                    ['label' => 'Mobile Number', 'value' => $player->mobile_number_full, 'verified' => $player->verified_mobile_number_full ?? false],
                                    ['label' => 'CricHeroes Number', 'value' => $player->cricheroes_number_full, 'verified' => $player->verified_cricheroes_number_full ?? false],
                                    ['label' => 'CricHeroes Profile URL', 'value' => $player->cricheroes_profile_url, 'verified' => $player->verified_cricheroes_profile_url ?? false, 'link' => true],
                                    ['label' => 'Location', 'value' => $player->location?->name],
                                    ['label' => 'Registration Team', 'value' => $player->team?->name === 'Others' ? ($player->team_name_ref ?? 'Others') : $player->team?->name, 'verified' => $player->verified_team_id ?? false],
                                    ['label' => 'Playing Team', 'value' => $player->actualTeam?->name],
                                ];
                            @endphp
                            @foreach($basicFields as $field)
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ ($field['verified'] ?? false) ? 'border-green-400 dark:border-green-600' : 'border-transparent' }}">
                                    <div class="flex items-start justify-between gap-2">
                                        <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $field['label'] }}</h4>
                                        @if($field['verified'] ?? false)
                                            <span class="text-green-500" title="Verified">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        @endif
                                    </div>
                                    @if(empty($field['value']))
                                        <p class="mt-1 text-sm italic text-gray-400 dark:text-gray-500">Not provided</p>
                                    @elseif($field['link'] ?? false)
                                        <a href="{{ $field['value'] }}" target="_blank" class="mt-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline break-all">{{ $field['value'] }}</a>
                                    @else
                                        <p class="mt-1 text-sm text-gray-900 dark:text-white break-words">{{ $field['value'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Section: Visa & Employment --}}
                    @if($player->visa_status)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Visa & Employment</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                                <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Visa Status</h4>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $visaList[$player->visa_status] ?? $player->visa_status }}</p>
                            </div>
                            @if($player->visa_status === 'visit_visa' && $player->visa_expiry)
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                                <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Visa Expiry</h4>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($player->visa_expiry)->format('d M Y') }}</p>
                            </div>
                            @endif
                            @if($player->visa_status === 'work_visa')
                                @foreach([
                                    'Employer Name' => $player->employer_name,
                                    'Position' => $player->employer_position,
                                    'Employer Address' => $player->employer_address,
                                ] as $label => $value)
                                    @if($value)
                                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                                        <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $label }}</h4>
                                        <p class="mt-1 text-sm text-gray-900 dark:text-white break-words">{{ $value }}</p>
                                    </div>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- Section: Availability --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Availability</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                                <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Available Saturdays</h4>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ is_null($player->available_saturday) ? 'Not provided' : ($player->available_saturday ? 'Yes' : 'No') }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                                <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Available Sundays</h4>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ is_null($player->available_sunday) ? 'Not provided' : ($player->available_sunday ? 'Yes' : 'No') }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                                <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Played YS IPL Season 1</h4>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ is_null($player->played_ys_ipl_s1) ? 'Not provided' : ($player->played_ys_ipl_s1 ? 'Yes' : 'No') }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Section: Jersey Information --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Jersey Information</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @php
                                $jerseyFields = [
                                    ['label' => 'Jersey Name', 'value' => $player->jersey_name, 'verified' => $player->verified_jersey_name ?? false],
                                    ['label' => 'Jersey Number', 'value' => $player->jersey_number, 'verified' => $player->verified_jersey_number ?? false],
                                    ['label' => 'T-Shirt Size', 'value' => $player->tshirt_size],
                                    ['label' => 'Pant Size', 'value' => $player->pant_size],
                                    ['label' => 'Jersey Size', 'value' => $player->kitSize?->size, 'verified' => $player->verified_kit_size_id ?? false],
                                ];
                            @endphp
                            @foreach($jerseyFields as $field)
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ ($field['verified'] ?? false) ? 'border-green-400 dark:border-green-600' : 'border-transparent' }}">
                                    <div class="flex items-start justify-between gap-2">
                                        <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $field['label'] }}</h4>
                                        @if($field['verified'] ?? false)
                                            <span class="text-green-500" title="Verified">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        @endif
                                    </div>
                                    @if(empty($field['value']) && $field['value'] !== 0 && $field['value'] !== '0')
                                        <p class="mt-1 text-sm italic text-gray-400 dark:text-gray-500">Not provided</p>
                                    @else
                                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $field['value'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Section: Player Profile --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Player Profile</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @php
                                $profileFields = [
                                    ['label' => 'Player Type', 'value' => $player->playerType?->type, 'verified' => $player->verified_player_type_id ?? false],
                                    ['label' => 'Batting Profile', 'value' => $player->battingProfile?->style, 'verified' => $player->verified_batting_profile_id ?? false],
                                    ['label' => 'Batting Mode', 'value' => $player->batting_mode],
                                    ['label' => 'Bowling Profile', 'value' => $player->bowlingProfile?->style, 'verified' => $player->verified_bowling_profile_id ?? false],
                                    ['label' => 'Wicket Keeper', 'value' => is_null($player->is_wicket_keeper) ? null : ($player->is_wicket_keeper ? 'Yes' : 'No'), 'verified' => $player->verified_is_wicket_keeper ?? false],
                                ];
                            @endphp
                            @foreach($profileFields as $field)
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ ($field['verified'] ?? false) ? 'border-green-400 dark:border-green-600' : 'border-transparent' }}">
                                    <div class="flex items-start justify-between gap-2">
                                        <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $field['label'] }}</h4>
                                        @if($field['verified'] ?? false)
                                            <span class="text-green-500" title="Verified">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        @endif
                                    </div>
                                    @if(is_null($field['value']) || $field['value'] === '')
                                        <p class="mt-1 text-sm italic text-gray-400 dark:text-gray-500">Not provided</p>
                                    @else
                                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $field['value'] }}</p>
                                    @endif
                                </div>
                            @endforeach

                            {{-- Preferred Batting Positions --}}
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                                <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Preferred Batting Positions</h4>
                                @if(!empty($player->preferred_batting_positions) && is_array($player->preferred_batting_positions))
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach($player->preferred_batting_positions as $pos)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">{{ $pos }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="mt-1 text-sm italic text-gray-400 dark:text-gray-500">Not provided</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Section: Leather Ball Experience --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Leather Ball Experience</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach([
                                'Total Matches' => $player->total_matches,
                                'Total Runs' => $player->total_runs,
                                'Total Wickets' => $player->total_wickets,
                            ] as $label => $value)
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                                    <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $label }}</h4>
                                    @if(is_null($value))
                                        <p class="mt-1 text-sm italic text-gray-400 dark:text-gray-500">Not provided</p>
                                    @else
                                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Section: Travel & Transportation --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Travel & Transportation</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ ($player->verified_transportation_required ?? false) ? 'border-green-400 dark:border-green-600' : 'border-transparent' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transportation Required</h4>
                                    @if($player->verified_transportation_required ?? false)
                                        <span class="text-green-500" title="Verified">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $player->transportation_required ? 'Yes' : 'No' }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ ($player->verified_no_travel_plan ?? false) ? 'border-green-400 dark:border-green-600' : 'border-transparent' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">No Travel Plan</h4>
                                    @if($player->verified_no_travel_plan ?? false)
                                        <span class="text-green-500" title="Verified">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $player->no_travel_plan ? 'Yes' : 'No' }}</p>
                            </div>
                            @if($player->travel_date_from || $player->travel_date_to)
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                                <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Travel Dates</h4>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                    {{ $player->travel_date_from ? \Carbon\Carbon::parse($player->travel_date_from)->format('d M Y') : '—' }}
                                    &rarr;
                                    {{ $player->travel_date_to ? \Carbon\Carbon::parse($player->travel_date_to)->format('d M Y') : '—' }}
                                </p>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Section: Player Mode & Team (only for approved) --}}
                    @if($player->status === 'approved')
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Player Mode & Team</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                                <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Player Mode</h4>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ ucfirst($player->player_mode ?? 'Normal') }}</p>
                            </div>
                            @if($player->player_mode === 'retained')
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                                <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Retained Value</h4>
                                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $player->retained_value ? number_format($player->retained_value) : '—' }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                @else
                    {{-- ═══════════════════════════════════════════════════════ --}}
                    {{-- NON-PLAYER USER: Show relevant user data              --}}
                    {{-- ═══════════════════════════════════════════════════════ --}}

                    {{-- Assigned Tournaments (for Organizer role) --}}
                    @if($assignedTournaments->count() > 0)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Assigned Tournaments</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($assignedTournaments as $tournament)
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-transparent">
                                    <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tournament</h4>
                                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $tournament->name }}</p>
                                    @if($tournament->start_date)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ \Carbon\Carbon::parse($tournament->start_date)->format('d M Y') }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- TOURNAMENT STATISTICS (only if player)                 --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if($player && $tournamentAssignments->count() > 0)
            <div class="mt-8 space-y-6" x-data="{ openTab: {{ $tournamentAssignments->first()->tournament_id }} }">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white">Tournament Statistics</h2>

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

                @foreach($tournamentAssignments as $assignment)
                    @php $stats = $tournamentStats->get($assignment->tournament_id); @endphp
                    <div x-show="openTab === {{ $assignment->tournament_id }}"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-cloak>
                        @if($stats)
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
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Dismissals</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
@endsection
