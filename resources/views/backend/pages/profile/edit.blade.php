@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        {!! ld_apply_filters('profile_edit_breadcrumbs', '') !!}

        <div class="space-y-6">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="px-5 py-2.5 sm:px-6 sm:py-5">
                    <h3 class="text-base font-medium text-gray-700 dark:text-white">{{ __('Edit Profile') }} -
                        {{ $user->name }}</h3>
                </div>
                <div class="p-5 space-y-6 border-t border-gray-100 dark:border-gray-800 sm:p-6">
                    <x-messages />
                    <form action="{{ route('profile.update') }}" method="POST" class="space-y-6">
                        @method('PUT')
                        @csrf
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div class="space-y-1">
                                <label for="name"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Name') }}</label>
                                <input type="text" name="name" id="name" required value="{{ $user->name }}"
                                    class="form-control">
                            </div>
                            <div class="space-y-1">
                                <label for="email"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Email') }}</label>
                                <input type="email" name="email" id="email" required value="{{ $user->email }}"
                                    class="form-control">
                            </div>
                            <x-inputs.password name="password" label="{{ __('Password (Optional)') }}" />
                            <x-inputs.password name="password_confirmation" label="{{ __('Confirm Password (Optional)') }}" />
                            {!! ld_apply_filters('profile_edit_fields', '', $user) !!}
                        </div>
                        {!! ld_apply_filters('profile_edit_after_fields', '', $user) !!}
                        <div class="mt-6 flex justify-start gap-4">
                            <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-md hover:bg-brand-600">{{ __('Save') }}</button>
                            <a href="{{ route('admin.dashboard') }}"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 dark:bg-gray-700 dark:text-white">
                                <iconify-icon icon="lucide:x-circle" class="mr-1"></iconify-icon>
                                {{ __('Cancel') }}</a>
                        </div>
                        {!! ld_apply_filters('profile_edit_fields', '', $user) !!}
                    </div>
                    {!! ld_apply_filters('profile_edit_after_fields', '', $user) !!}
                </form>
            </div>

            @if(!empty($approvedRegistrations) && $approvedRegistrations->count())
                <div class="rounded-md border border-green-300 dark:border-green-800 bg-green-50 dark:bg-green-900/20 px-5 py-4">
                    <p class="text-sm font-semibold text-green-800 dark:text-green-300">✔ You're accepted!</p>
                    <p class="text-sm text-green-700 dark:text-green-400 mt-1">
                        Your registration has been approved for
                        <strong>{{ $approvedRegistrations->map(fn($r) => $r->tournament->name ?? 'a tournament')->join(', ') }}</strong>.
                        Your registration details are locked — you can still update your email or password on this page.
                    </p>
                </div>
            @endif

            @if(!empty($player))
                @php
                    $countries = config('countries.list', []);
                    $visaList = config('registration.visa_statuses', []);
                    $yn = fn ($v) => is_null($v) ? null : ($v ? 'Yes' : 'No');
                    $details = [
                        'Basic Information' => [
                            'Full Name' => trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? '')) ?: $player->name,
                            'Email' => $player->email,
                            'Date of Birth' => $player->date_of_birth ? \Illuminate\Support\Carbon::parse($player->date_of_birth)->format('d M Y') : null,
                            'Nationality' => $player->country ? ($countries[$player->country] ?? $player->country) : null,
                            'State / Province' => $player->state,
                            'Mobile Number' => $player->mobile_number_full,
                            'CricHeroes Number' => $player->cricheroes_number_full,
                            'CricHeroes Profile' => $player->cricheroes_profile_url,
                            'Location' => $player->location?->name,
                            'Registration Team' => $player->team_name_ref,
                            'Playing Team' => $player->actualTeam?->name,
                        ],
                        'Visa & Employment' => [
                            'Visa Status' => $player->visa_status ? ($visaList[$player->visa_status] ?? $player->visa_status) : null,
                            'Visa Validity' => $player->visa_expiry ? \Illuminate\Support\Carbon::parse($player->visa_expiry)->format('d M Y') : null,
                            'Employer Name' => $player->employer_name,
                            'Employer Position' => $player->employer_position,
                            'Employer Address' => $player->employer_address,
                        ],
                        'Availability & Profile' => [
                            'Available Saturday' => $yn($player->available_saturday),
                            'Available Sunday' => $yn($player->available_sunday),
                            'Played YS IPL S1' => $yn($player->played_ys_ipl_s1),
                            'Jersey Name' => $player->jersey_name,
                            'Jersey Number' => $player->jersey_number,
                            'T-Shirt Size' => $player->tshirt_size,
                            'Pant Size' => $player->pant_size,
                            'Player Type' => $player->playerType?->name ?? $player->playerType?->type,
                            'Batting Profile' => $player->battingProfile?->name ?? $player->battingProfile?->style,
                            'Bowling Profile' => $player->bowlingProfile?->name ?? $player->bowlingProfile?->style,
                            'Wicket Keeper' => $yn($player->is_wicket_keeper),
                        ],
                        'Statistics' => [
                            'Total Matches' => $player->total_matches,
                            'Total Runs' => $player->total_runs,
                            'Total Wickets' => $player->total_wickets,
                        ],
                    ];
                @endphp
                <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="px-5 py-2.5 sm:px-6 sm:py-5 flex items-center justify-between">
                        <h3 class="text-base font-medium text-gray-700 dark:text-white">{{ __('My Registration Details') }}</h3>
                        @if($user->hasRole('Player'))
                            <a href="{{ route('profileplayers.edit') }}" class="text-sm font-medium text-brand-500 hover:text-brand-600">{{ __('Edit details') }}</a>
                        @endif
                    </div>
                    <div class="p-5 border-t border-gray-100 dark:border-gray-800 sm:p-6">
                        <div class="flex flex-col sm:flex-row gap-6">
                            {{-- Profile photo chosen at registration --}}
                            <div class="flex-shrink-0">
                                @if($player->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($player->image_path))
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($player->image_path) }}" alt="{{ $player->name }}"
                                         class="w-32 h-40 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                                @else
                                    <div class="w-32 h-40 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 flex items-center justify-center text-gray-400 text-xs text-center px-2">No photo uploaded</div>
                                @endif
                            </div>
                            <div class="flex-1 space-y-6">
                                @foreach($details as $section => $rows)
                                    @php $rows = array_filter($rows, fn ($v) => $v !== null && $v !== ''); @endphp
                                    @if(count($rows))
                                        <div>
                                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">{{ $section }}</h4>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                                @foreach($rows as $label => $value)
                                                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                                                        <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $label }}</div>
                                                        @if($label === 'CricHeroes Profile')
                                                            <a href="{{ $value }}" target="_blank" class="mt-1 block text-sm text-brand-500 hover:underline break-all">{{ $value }}</a>
                                                        @else
                                                            <div class="mt-1 text-sm text-gray-900 dark:text-white break-words">{{ $value }}</div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection