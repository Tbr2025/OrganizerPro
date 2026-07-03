@extends('backend.layouts.app')

@section('title', 'Registration Details | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-4xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[
            ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
            ['label' => $tournament->name],
            ['label' => 'Registrations', 'url' => route('admin.tournaments.registrations.index', $tournament)],
            ['label' => 'Details']
        ]" />

        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl overflow-hidden">
            {{-- Header --}}
            <div class="p-6 {{ $registration->type == 'team' ? 'bg-gradient-to-r from-purple-600 to-indigo-700' : 'bg-gradient-to-r from-blue-600 to-cyan-700' }}">
                <div class="flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        @if($registration->type == 'team')
                            @if($registration->team_logo)
                                <img src="{{ Storage::url($registration->team_logo) }}" alt="Team Logo" class="w-16 h-16 rounded-xl object-cover border-2 border-white/30">
                            @else
                                <div class="w-16 h-16 rounded-xl bg-white/20 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            @endif
                            <div>
                                <h2 class="text-2xl font-bold text-white">{{ $registration->team_name }}</h2>
                                @if($registration->team_short_name)
                                    <p class="text-white/80 text-sm">({{ $registration->team_short_name }})</p>
                                @endif
                                <span class="inline-flex items-center px-2 py-0.5 mt-1 rounded text-xs font-medium bg-purple-900/50 text-white">
                                    Team Registration
                                </span>
                            </div>
                        @else
                            @if($registration->player?->image_path)
                                <img src="{{ Storage::url($registration->player->image_path) }}" alt="Player" class="w-16 h-16 rounded-full object-cover border-2 border-white/30">
                            @else
                                <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                            @endif
                            <div>
                                <h2 class="text-2xl font-bold text-white">{{ $registration->player->name ?? 'N/A' }}</h2>
                                <span class="inline-flex items-center px-2 py-0.5 mt-1 rounded text-xs font-medium bg-blue-900/50 text-white">
                                    Player Registration
                                </span>
                            </div>
                        @endif
                    </div>

                    @if($registration->status == 'pending')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-400 text-yellow-900">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Pending
                        </span>
                    @elseif($registration->status == 'approved')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-400 text-green-900">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Approved
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-400 text-red-900">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Rejected
                        </span>
                    @endif
                </div>
            </div>

            {{-- Details --}}
            <div class="p-6">
                @if($registration->type == 'team')
                    {{-- Team Registration Details --}}
                    <div class="space-y-6">
                        {{-- Team Manager Information --}}
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Team Manager Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</h4>
                                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $registration->captain_name }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</h4>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->captain_email }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</h4>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->captain_phone }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Team Owner Information --}}
                        @if($registration->vice_captain_name || $registration->vice_captain_email)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Team Owner Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                @if($registration->vice_captain_name)
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</h4>
                                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $registration->vice_captain_name }}</p>
                                </div>
                                @endif
                                @if($registration->vice_captain_email)
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</h4>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->vice_captain_email }}</p>
                                </div>
                                @endif
                                @if($registration->vice_captain_phone)
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</h4>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->vice_captain_phone }}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- Team Description --}}
                        @if($registration->team_description)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Team Description</h3>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $registration->team_description }}</p>
                            </div>
                        </div>
                        @endif
                    </div>
                @else
                    {{-- Player Registration Details — grouped to match the registration form sections --}}
                    @php
                        $p = $registration->player;
                        $regSettings = $tournament->settings;
                        $fieldConfig = \App\Helpers\PlayerFormConfig::getFieldConfig($regSettings);
                        // Only the fields that are visible on the public form (match the form exactly).
                        $layout = \App\Helpers\PlayerFormConfig::getFormLayout($regSettings, true);
                        $countries = config('countries.list', []);
                        $visaList = config('registration.visa_statuses', []);
                        $valueFor = function ($key) use ($p, $countries, $visaList, $registration) {
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
                                'registration_team' => $registration->actualTeam?->name ?? $p->team_name_ref,
                                'playing_team' => $p->actualTeam?->name,
                                'visa_status' => $p->visa_status ? ($visaList[$p->visa_status] ?? $p->visa_status) : null,
                                'visa_expiry' => optional($p->visa_expiry)->format('d M Y'),
                                'employer_name' => $p->employer_name,
                                'employer_address' => $p->employer_address,
                                'employer_position' => $p->employer_position,
                                'available_saturday' => is_null($p->available_saturday) ? null : ($p->available_saturday ? 'Yes' : 'No'),
                                'available_sunday' => is_null($p->available_sunday) ? null : ($p->available_sunday ? 'Yes' : 'No'),
                                'played_ys_ipl_s1' => is_null($p->played_ys_ipl_s1) ? null : ($p->played_ys_ipl_s1 ? 'Yes' : 'No'),
                                'jersey_name' => $p->jersey_name,
                                'jersey_number' => $p->jersey_number,
                                'kit_size' => $p->kitSize?->size ?? $p->kitSize?->name,
                                'player_type' => $p->playerType?->name ?? $p->playerType?->type,
                                'batting_profile' => $p->battingProfile?->name ?? $p->battingProfile?->style,
                                'bowling_profile' => $p->bowlingProfile?->name ?? $p->bowlingProfile?->style,
                                'is_wicket_keeper' => is_null($p->is_wicket_keeper) ? null : ($p->is_wicket_keeper ? 'Yes' : 'No'),
                                'total_matches' => $p->total_matches,
                                'total_runs' => $p->total_runs,
                                'total_wickets' => $p->total_wickets,
                                default => null,
                            };
                        };
                        $skip = ['name', 'image', 'terms_and_conditions'];
                        $verifiedFields = (array) ($registration->verified_fields ?? []);
                    @endphp
                    <form method="POST" action="{{ route('admin.tournaments.registrations.verification', [$tournament, $registration]) }}" class="space-y-6">
                        @csrf
                        @if($p && $p->image_path)
                        <div>
                            <img src="{{ Storage::url($p->image_path) }}" alt="{{ $p->name }}" class="w-28 h-36 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                        </div>
                        @endif

                        @foreach($layout as $section)
                            @php
                                // Show EVERY field that is visible on the public form — even when the
                                // applicant left it blank (optional fields), so the admin sees the full form.
                                $rows = [];
                                foreach ($section['fields'] as $key) {
                                    if (in_array($key, $skip, true)) continue;
                                    $rows[$key] = $valueFor($key); // may be null/empty
                                }
                            @endphp
                            @if(count($rows))
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">{{ $section['title'] }}</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    @foreach($rows as $key => $value)
                                    @php
                                        $isVerified = in_array($key, $verifiedFields, true);
                                        $isEmpty = ($value === null || $value === '');
                                        $isRequired = $fieldConfig[$key]['required'] ?? false;
                                    @endphp
                                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ $isVerified ? 'border-green-400 dark:border-green-600' : 'border-transparent' }}">
                                        <input type="hidden" name="all_fields[]" value="{{ $key }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                {{ $fieldConfig[$key]['label'] ?? $key }}
                                                @if($isRequired)
                                                    <span class="text-red-500" title="Required field">*</span>
                                                @else
                                                    <span class="ml-1 text-[9px] normal-case font-normal text-gray-400 dark:text-gray-500">(optional)</span>
                                                @endif
                                            </h4>
                                            <label class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400 whitespace-nowrap cursor-pointer" title="Mark this field as verified">
                                                <input type="checkbox" name="verified[]" value="{{ $key }}" {{ $isVerified ? 'checked' : '' }} class="h-3.5 w-3.5 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                                <span>Verified</span>
                                            </label>
                                        </div>
                                        @if($isEmpty)
                                            <p class="mt-1 text-sm italic text-gray-400 dark:text-gray-500">Not provided</p>
                                        @elseif($key === 'cricheroes_profile_url')
                                            <a href="{{ $value }}" target="_blank" class="mt-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline break-all">{{ $value }}</a>
                                        @else
                                            <p class="mt-1 text-sm text-gray-900 dark:text-white break-words">{{ $value }}</p>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        @endforeach

                        @if($registration->actualTeam)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Assigned Team</h3>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $registration->actualTeam->name }}</p>
                            </div>
                        </div>
                        @endif

                        {{-- Verification actions: save verified state, or email a correction request --}}
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                            <label for="verify_note" class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Note to applicant (optional, included in correction email)</label>
                            <textarea name="note" id="verify_note" rows="2" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" placeholder="e.g. Please upload a clearer photo and confirm your jersey number."></textarea>
                            <div class="flex flex-wrap items-center gap-3 mt-3">
                                <button type="submit" name="action" value="save"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                                    Save Verification
                                </button>
                                <button type="submit" name="action" value="send"
                                    onclick="return confirm('Save verification and email the applicant a correction request for the unverified fields?')"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-amber-500 text-white hover:bg-amber-600">
                                    Save &amp; Send Correction Request
                                </button>
                                @if($registration->consent_signed_at)
                                    <a href="{{ route('admin.tournaments.registrations.consent-pdf', [$tournament, $registration]) }}"
                                       class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                                        Download Consent PDF
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                @endif

                {{-- Consent download for team registrations (players have it in the form above) --}}
                @if($registration->isTeamRegistration() && $registration->consent_signed_at)
                <div class="mt-6">
                    <a href="{{ route('admin.tournaments.registrations.consent-pdf', [$tournament, $registration]) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                        Download Consent PDF
                    </a>
                </div>
                @endif

                {{-- Registration Meta --}}
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Registration Date</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->created_at->format('M d, Y') }}</p>
                            <p class="text-xs text-gray-500">{{ $registration->created_at->format('h:i A') }}</p>
                        </div>
                        @if($registration->processed_at)
                        <div>
                            <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Processed Date</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->processed_at->format('M d, Y') }}</p>
                            <p class="text-xs text-gray-500">{{ $registration->processed_at->format('h:i A') }}</p>
                        </div>
                        @endif
                        @if($registration->processedBy)
                        <div>
                            <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Processed By</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->processedBy->name }}</p>
                        </div>
                        @endif
                    </div>

                    @if($registration->remarks)
                    <div class="mt-4">
                        <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Remarks</h4>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $registration->remarks }}</p>
                    </div>
                    @endif
                </div>

                {{-- Actions --}}
                @if($registration->status == 'pending')
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <a href="{{ route('admin.tournaments.registrations.index', $tournament) }}"
                           class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to List
                        </a>
                        <div class="flex gap-3">
                            <form action="{{ route('admin.tournaments.registrations.reject', [$tournament, $registration]) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Reject Registration
                                </button>
                            </form>
                            <form action="{{ route('admin.tournaments.registrations.approve', [$tournament, $registration]) }}" method="POST" class="inline-flex items-center gap-3">
                                @csrf
                                @if($registration->isTeamRegistration() && $approvedPlayerUsers->count())
                                    <div>
                                        <select name="captain_user_id" class="form-control text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                            <option value="">-- Captain from registration form --</option>
                                            @foreach($approvedPlayerUsers as $playerUser)
                                                <option value="{{ $playerUser->id }}">{{ $playerUser->name }} ({{ $playerUser->email }})</option>
                                            @endforeach
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">Assign Captain from Registered Players (Optional)</p>
                                    </div>
                                @endif
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Approve Registration
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <a href="{{ route('admin.tournaments.registrations.index', $tournament) }}"
                           class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to List
                        </a>
                        <form action="{{ route('admin.tournaments.registrations.force-delete', [$tournament, $registration]) }}" method="POST" class="inline"
                              onsubmit="return confirm('{{ $registration->type == 'team' && $registration->status == 'approved' ? 'WARNING: This will also delete the team created from this registration. Are you sure?' : 'Are you sure you want to delete this registration?' }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-800 border border-transparent rounded-md shadow-sm hover:bg-red-900">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Force Delete
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
