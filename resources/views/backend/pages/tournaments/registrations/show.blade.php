@extends('backend.layouts.app')

@section('title', 'Registration Details | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-4xl md:p-6" x-data="{
        imageModal: false,
        imageSrc: '',
        imageInfo: { size: '', width: 0, height: 0, ratio: '' },
        openImage(url) {
            this.imageSrc = url;
            this.imageInfo = { size: 'Loading\u2026', width: 0, height: 0, ratio: '' };
            this.imageModal = true;
            const img = new Image();
            img.onload = () => {
                this.imageInfo.width = img.naturalWidth;
                this.imageInfo.height = img.naturalHeight;
                const gcd = (a, b) => b ? gcd(b, a % b) : a;
                const d = gcd(img.naturalWidth, img.naturalHeight);
                this.imageInfo.ratio = (img.naturalWidth / d) + ':' + (img.naturalHeight / d);
            };
            img.src = url;
            fetch(url, { method: 'HEAD' }).then(r => {
                const len = r.headers.get('Content-Length');
                if (len) {
                    const kb = parseInt(len) / 1024;
                    this.imageInfo.size = kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : Math.round(kb) + ' KB';
                } else { this.imageInfo.size = '\u2014'; }
            }).catch(() => { this.imageInfo.size = '\u2014'; });
        }
    }">
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
                                <img src="{{ Storage::url($registration->team_logo) }}" alt="Team Logo" class="w-16 h-16 rounded-xl object-cover border-2 border-white/30 cursor-pointer" @click="openImage('{{ Storage::url($registration->team_logo) }}')">
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
                                <img src="{{ Storage::url($registration->player->image_path) }}" alt="Player" class="w-16 h-16 rounded-full object-cover border-2 border-white/30 cursor-pointer" @click="openImage('{{ Storage::url($registration->player->image_path) }}')">
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

                        {{-- Custom fields (team) --}}
                        @php $teamCustom = $tournament->customFields->where('form', 'team'); $teamCfVals = (array) $registration->custom_field_values; @endphp
                        @if($teamCustom->count())
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Additional Details</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($teamCustom as $cf)
                                    @php
                                        $v = $teamCfVals['cf_' . $cf->id] ?? null;
                                        if ($cf->type === 'checkbox') { $v = ($v === '1' || $v === 1) ? 'Yes' : (($v === '0' || $v === 0) ? 'No' : null); }
                                    @endphp
                                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                                        <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $cf->label }}</h4>
                                        @if($v === null || $v === '')
                                            <p class="mt-1 text-sm italic text-gray-400 dark:text-gray-500">Not provided</p>
                                        @else
                                            <p class="mt-1 text-sm text-gray-900 dark:text-white break-words">{{ $v }}</p>
                                        @endif
                                    </div>
                                @endforeach
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
                                'playing_team' => $p->actualTeam?->name ?? $p->playing_team_name_ref,
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
                                'transportation' => is_null($p->transportation_required) ? null : ($p->transportation_required ? 'Yes' : 'No'),
                                'travel_plan' => $p->no_travel_plan ? 'No travel plan' : (($p->travel_date_from || $p->travel_date_to) ? trim(($p->travel_date_from ? optional($p->travel_date_from)->format('d M Y') : '') . ' – ' . ($p->travel_date_to ? optional($p->travel_date_to)->format('d M Y') : ''), ' –') : null),
                                default => null,
                            };
                        };
                        $skip = ['name', 'image', 'terms_and_conditions'];
                        $verifiedFields = (array) ($registration->verified_fields ?? []);
                    @endphp
                    <form method="POST" action="{{ route('admin.tournaments.registrations.verification', [$tournament, $registration]) }}" class="space-y-6">
                        @csrf
                        {{-- Verify-all toggle: ticks every field's Verified box at once --}}
                        <div class="flex items-center justify-between gap-2 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-900/40 rounded-lg px-4 py-3">
                            <label class="flex items-center gap-2 text-sm font-semibold text-indigo-700 dark:text-indigo-300 cursor-pointer">
                                <input type="checkbox" id="verifyAllToggle" class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                Verify all fields
                            </label>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Tick to mark every field below as verified.</span>
                        </div>
                        @if($p && $p->image_path)
                        @php $photoVerified = in_array('image_path', $verifiedFields, true); @endphp
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ $photoVerified ? 'border-green-400 dark:border-green-600' : 'border-transparent' }} inline-block">
                            <div class="flex items-start gap-4">
                                <img src="{{ Storage::url($p->image_path) }}" alt="{{ $p->name }}" class="w-28 h-36 object-cover rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer" @click="openImage('{{ Storage::url($p->image_path) }}')">
                                <label class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400 whitespace-nowrap cursor-pointer" title="Mark this field as verified">
                                    <input type="checkbox" name="verified[]" value="image_path" {{ $photoVerified ? 'checked' : '' }} class="h-3.5 w-3.5 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                    <span>Verified</span>
                                </label>
                            </div>
                        </div>
                        @endif

                        @php $regCustom = $tournament->customFields->where('form', 'player'); $cfVals = (array) $registration->custom_field_values; @endphp
                        @foreach($layout as $section)
                            @php
                                // Show EVERY field that is visible on the public form — even when the
                                // applicant left it blank (optional fields), so the admin sees the full form.
                                $rows = [];
                                foreach ($section['fields'] as $key) {
                                    if (in_array($key, $skip, true)) continue;
                                    $rows[$key] = $valueFor($key); // may be null/empty
                                }
                                $sectionCustom = $regCustom->where('visible', true)->where('section', $section['key']);
                            @endphp
                            @if(count($rows) || $sectionCustom->count())
                            <div data-verify-section>
                                <div class="flex items-center justify-between gap-2 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $section['title'] }}</h3>
                                    <label class="flex items-center gap-1.5 text-[11px] font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap cursor-pointer" title="Verify all fields in this group">
                                        <input type="checkbox" class="section-verify-toggle h-3.5 w-3.5 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                        <span>Verify group</span>
                                    </label>
                                </div>
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

                                    {{-- Custom fields for this section --}}
                                    @foreach($sectionCustom as $cf)
                                        @php
                                            $cfKey = 'cf_' . $cf->id;
                                            $cfVal = $cfVals[$cfKey] ?? null;
                                            if ($cf->type === 'checkbox') { $cfVal = ($cfVal === '1' || $cfVal === 1) ? 'Yes' : (($cfVal === '0' || $cfVal === 0) ? 'No' : null); }
                                            $cfEmpty = ($cfVal === null || $cfVal === '');
                                            $cfVerified = in_array($cfKey, $verifiedFields, true);
                                        @endphp
                                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ $cfVerified ? 'border-green-400 dark:border-green-600' : 'border-transparent' }}">
                                            <input type="hidden" name="all_fields[]" value="{{ $cfKey }}">
                                            <div class="flex items-start justify-between gap-2">
                                                <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                    {{ $cf->label }}
                                                    @if($cf->required)<span class="text-red-500">*</span>@else<span class="ml-1 text-[9px] normal-case font-normal text-gray-400 dark:text-gray-500">(optional)</span>@endif
                                                    <span class="ml-1 text-[9px] normal-case font-normal text-indigo-400">custom</span>
                                                </h4>
                                                <label class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400 whitespace-nowrap cursor-pointer">
                                                    <input type="checkbox" name="verified[]" value="{{ $cfKey }}" {{ $cfVerified ? 'checked' : '' }} class="h-3.5 w-3.5 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                                    <span>Verified</span>
                                                </label>
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
                                    onclick="return confirm('Save verification and email the applicant their section-by-section review status (accepted vs pending groups)?')"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-amber-500 text-white hover:bg-amber-600">
                                    Save &amp; Email Review Status
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

                {{-- Player-requested profile changes awaiting approval --}}
                @if(!empty($registration->pending_changes))
                    @php
                        $pc = (array) $registration->pending_changes;
                        $pcPlayer = $registration->player;
                        $pcLabels = [
                            'name' => 'Name', 'mobile_number_full' => 'Mobile Number', 'jersey_name' => 'Jersey Name',
                            'cricheroes_number_full' => 'CricHeroes Number', 'cricheroes_profile_url' => 'CricHeroes Profile',
                            'jersey_number' => 'Jersey Number', 'team_name_ref' => 'Registration Team', 'location_id' => 'Location',
                            'total_matches' => 'Total Matches', 'total_runs' => 'Total Runs', 'total_wickets' => 'Total Wickets',
                            'travel_date_from' => 'Travel From', 'travel_date_to' => 'Travel To', 'no_travel_plan' => 'No Travel Plan',
                            'tshirt_size' => 'T-Shirt Size', 'pant_size' => 'Pant Size', 'batting_profile_id' => 'Batting Profile', 'bowling_profile_id' => 'Bowling Profile',
                            'player_type_id' => 'Player Type', 'is_wicket_keeper' => 'Wicket Keeper', 'transportation_required' => 'Transportation', 'image_path' => 'Profile Photo',
                        ];
                        $pcFmt = function ($field, $val) {
                            if (is_null($val) || $val === '') return '—';
                            return match ($field) {
                                'is_wicket_keeper', 'transportation_required', 'no_travel_plan' => $val ? 'Yes' : 'No',
                                'batting_profile_id' => optional(\App\Models\BattingProfile::find($val))->name ?? $val,
                                'bowling_profile_id' => optional(\App\Models\BowlingProfile::find($val))->name ?? $val,
                                'player_type_id' => optional(\App\Models\PlayerType::find($val))->name ?? $val,
                                'location_id' => optional(\App\Models\PlayerLocation::find($val))->name ?? $val,
                                default => (string) $val,
                            };
                        };
                    @endphp
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <div class="rounded-lg border border-amber-300 dark:border-amber-700 overflow-hidden">
                            <div class="bg-amber-50 dark:bg-amber-900/20 px-4 py-3 flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-300">Pending Profile Changes</h3>
                                <span class="text-xs text-amber-700 dark:text-amber-400">
                                    @if($registration->pending_changes_submitted_at) Submitted {{ $registration->pending_changes_submitted_at->format('d M Y, H:i') }} @endif
                                </span>
                            </div>
                            <div class="p-4 overflow-x-auto">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">The player requested these changes. They will only apply to the profile after you approve.</p>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-[11px] uppercase tracking-wider text-gray-400">
                                            <th class="py-2 pr-4">Field</th>
                                            <th class="py-2 pr-4">Current</th>
                                            <th class="py-2">Requested</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach($pc as $field => $val)
                                        <tr>
                                            <td class="py-2 pr-4 font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $pcLabels[$field] ?? $field }}</td>
                                            @if($field === 'image_path')
                                                <td class="py-2 pr-4">
                                                    @if($pcPlayer?->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($pcPlayer->image_path))
                                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($pcPlayer->image_path) }}" class="w-12 h-14 object-cover rounded border border-gray-200 dark:border-gray-700 cursor-pointer" @click="openImage('{{ \Illuminate\Support\Facades\Storage::url($pcPlayer->image_path) }}')">
                                                    @else — @endif
                                                </td>
                                                <td class="py-2">
                                                    @if($val && \Illuminate\Support\Facades\Storage::disk('public')->exists($val))
                                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($val) }}" class="w-12 h-14 object-cover rounded border-2 border-amber-400 cursor-pointer" @click="openImage('{{ \Illuminate\Support\Facades\Storage::url($val) }}')">
                                                    @else <span class="text-gray-400">Removed</span> @endif
                                                </td>
                                            @else
                                                <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $pcFmt($field, $pcPlayer?->{$field}) }}</td>
                                                <td class="py-2 font-semibold text-amber-700 dark:text-amber-300">{{ $pcFmt($field, $val) }}</td>
                                            @endif
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="flex flex-wrap gap-3 mt-4">
                                    <form action="{{ route('admin.tournaments.registrations.pending-changes.approve', [$tournament, $registration]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700">Approve changes</button>
                                    </form>
                                    <form action="{{ route('admin.tournaments.registrations.pending-changes.reject', [$tournament, $registration]) }}" method="POST"
                                          onsubmit="return confirm('Reject and discard these requested changes?')">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-red-300 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">Reject</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Welcome Card Preview & Download (player registrations) --}}
                @if($registration->isPlayerRegistration() && $registration->player)
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700"
                     x-data="{ loading: false, imageUrl: null, error: null }">
                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Welcome Card</h4>
                    <div class="flex flex-wrap items-center gap-3 mb-3">
                        <button type="button"
                                @click="loading = true; error = null;
                                    fetch('{{ route('admin.tournaments.registrations.welcome-card.preview', [$tournament, $registration]) }}')
                                    .then(r => { if (!r.ok) throw r; return r.json(); })
                                    .then(d => { imageUrl = d.image; loading = false; })
                                    .catch(e => { error = 'Could not generate preview. Ensure a welcome card template exists.'; loading = false; })"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-green-300 dark:border-green-700 text-green-700 dark:text-green-300 hover:bg-green-50 dark:hover:bg-green-900/30"
                                :disabled="loading">
                            <template x-if="!loading">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </template>
                            <template x-if="loading">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </template>
                            <span x-text="loading ? 'Generating...' : 'Preview Welcome Card'"></span>
                        </button>
                        <a href="{{ route('admin.tournaments.registrations.welcome-card.download', [$tournament, $registration]) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-indigo-300 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download Welcome Card
                        </a>
                    </div>
                    <p x-show="error" x-text="error" class="text-sm text-red-500 mb-3" x-cloak></p>
                    <div x-show="imageUrl" x-cloak class="mt-3 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 inline-block">
                        <img :src="imageUrl" alt="Welcome Card Preview" class="max-w-full max-h-[500px] object-contain">
                    </div>
                </div>
                @endif

                {{-- Email actions: resend welcome card / confirmation --}}
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Email Actions</h4>
                    <div class="flex flex-wrap gap-3">
                        <form action="{{ route('admin.tournaments.registrations.resend-confirmation', [$tournament, $registration]) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                Resend Confirmation Email
                            </button>
                        </form>
                        @if($registration->isPlayerRegistration())
                        <form action="{{ route('admin.tournaments.registrations.resend-welcome', [$tournament, $registration]) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-indigo-300 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                Resend Welcome Card
                            </button>
                        </form>
                        @endif
                        <form action="{{ route('admin.tournaments.registrations.send-temp-password', [$tournament, $registration]) }}" method="POST"
                              onsubmit="return confirm('Reset this applicant\'s password and email them a new temporary one so they can log in and correct their details?')">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-amber-300 dark:border-amber-700 text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                Send Temp Password
                            </button>
                        </form>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Confirmation sends the approval email when approved, otherwise the "application received" email. Welcome card requires a welcome-card template. "Send Temp Password" resets the applicant's login and emails them a new temporary password so they can sign in and update their details (pending your approval).</p>
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
        {{-- Image Lightbox Modal --}}
        <div x-show="imageModal" x-transition.opacity x-cloak
             class="fixed inset-0 z-[9999] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
             @click.self="imageModal = false" @keydown.escape.window="imageModal = false">
            <div class="relative max-w-4xl w-full flex flex-col items-center">
                <button @click="imageModal = false" class="absolute -top-10 right-0 text-white/70 hover:text-white transition">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <img :src="imageSrc" alt="Full size" class="max-w-full max-h-[85vh] rounded-xl shadow-2xl object-contain">
                <div class="flex items-center gap-4 mt-3 text-sm text-white/80">
                    <span x-show="imageInfo.width" x-text="imageInfo.width + ' \u00d7 ' + imageInfo.height + ' px'"></span>
                    <span x-show="imageInfo.ratio" x-text="imageInfo.ratio"></span>
                    <span x-text="imageInfo.size"></span>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const master = document.getElementById('verifyAllToggle');
        const getBoxes = () => Array.from(document.querySelectorAll('input[name="verified[]"]'));

        // Global "Verify all" toggle.
        if (master) {
            master.addEventListener('change', function () {
                getBoxes().forEach(function (cb) { cb.checked = master.checked; });
                syncSections();
            });
        }
        const syncMaster = function () {
            if (!master) return;
            const boxes = getBoxes();
            master.checked = boxes.length > 0 && boxes.every(function (cb) { return cb.checked; });
        };

        // Per-section "Verify group" toggles — one click verifies the whole group.
        const sections = Array.from(document.querySelectorAll('[data-verify-section]'));
        const sectionBoxes = (sec) => Array.from(sec.querySelectorAll('input[name="verified[]"]'));
        const syncSection = function (sec) {
            const toggle = sec.querySelector('.section-verify-toggle');
            const boxes = sectionBoxes(sec);
            if (toggle) toggle.checked = boxes.length > 0 && boxes.every(function (cb) { return cb.checked; });
        };
        const syncSections = function () { sections.forEach(syncSection); };

        sections.forEach(function (sec) {
            const toggle = sec.querySelector('.section-verify-toggle');
            if (toggle) {
                toggle.addEventListener('change', function () {
                    sectionBoxes(sec).forEach(function (cb) { cb.checked = toggle.checked; });
                    syncMaster();
                });
            }
        });

        // Keep all toggles in sync when individual boxes change.
        getBoxes().forEach(function (cb) {
            cb.addEventListener('change', function () { syncMaster(); syncSections(); });
        });
        syncMaster();
        syncSections();
    });
</script>
@endpush
