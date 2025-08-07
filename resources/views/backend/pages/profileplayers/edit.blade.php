@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="space-y-6">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-5 space-y-6 border-t border-gray-100 dark:border-gray-800 sm:p-6">
                    {{-- Added enctype="multipart/form-data" for file upload --}}
                    <form method="POST" action="{{ route('profileplayers.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Player Name --}}
                            <div class="space-y-1">
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Player Name') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" id="name" required
                                    value="{{ old('name', $player->name) }}" placeholder="Enter Player Name"
                                    class="form-control @error('name') border-red-500 @enderror">
                                @error('name')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Player Email --}}
                            <div class="space-y-1">
                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Email Address') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="email" name="email" id="email" required
                                    value="{{ old('email', $player->email) }}" placeholder="Enter Player Email"
                                    class="form-control @error('email') border-red-500 @enderror">
                                @error('email')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Mobile No --}}
                            <div class="space-y-1 sm:col-span-2">
                                <label for="mobile_national_number"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Mobile Number') }} <span class="text-red-500">*</span>
                                </label>
                                <div class="flex items-center space-x-2">
                                    {{-- Country Code --}}
                                    <div class="w-1/4">
                                        <input type="text" name="mobile_country_code" id="mobile_country_code" required
                                            value="{{ old('mobile_country_code', $player->mobile_country_code) }}"
                                            placeholder="+91"
                                            class="form-control @error('mobile_country_code') border-red-500 @enderror">
                                        @error('mobile_country_code')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    {{-- National Number --}}
                                    <div class="w-3/4">
                                        <input type="text" name="mobile_national_number" id="mobile_national_number"
                                            required
                                            value="{{ old('mobile_national_number', $player->mobile_national_number) }}"
                                            placeholder="Enter Mobile Number"
                                            class="form-control @error('mobile_national_number') border-red-500 @enderror">
                                        @error('mobile_national_number')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Cricheroes Number --}}
                            <div class="space-y-1 sm:col-span-2">
                                <label for="cricheroes_national_number"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Cricheroes Number') }}
                                </label>
                                <div class="flex items-center space-x-2">
                                    {{-- Country Code --}}
                                    <div class="w-1/4">
                                        <input type="text" name="cricheroes_country_code" id="cricheroes_country_code"
                                            value="{{ old('cricheroes_country_code', $player->cricheroes_country_code) }}"
                                            placeholder="+91"
                                            class="form-control @error('cricheroes_country_code') border-red-500 @enderror">
                                        @error('cricheroes_country_code')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    {{-- National Number --}}
                                    <div class="w-3/4">
                                        <input type="text" name="cricheroes_national_number"
                                            id="cricheroes_national_number"
                                            value="{{ old('cricheroes_national_number', $player->cricheroes_national_number) }}"
                                            placeholder="Enter Cricheroes Number"
                                            class="form-control @error('cricheroes_national_number') border-red-500 @enderror">
                                        @error('cricheroes_national_number')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Team --}}
                            <div x-data="{
                                selectedTeam: '{{ old('team_id', $player->team_id) }}',
                                newTeamName: '{{ old('team_name_ref', $player->team_name_ref) }}'
                            }">
                                 <label for="team_name_ref"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Team ') }}
                                </label>
                                <select name="team_id" id="team_id" class="form-control @error('team_id') border-red-500 @enderror"
                                    x-model="selectedTeam" @change="newTeamName = ''" required>
                                    <option value="">Select your team</option>
                                    @foreach ($teams as $team)
                                        <option value="{{ $team->id }}" @selected(old('team_id', $player->team_id) == $team->id)>
                                            {{ $team->name }}
                                        </option>
                                    @endforeach
                                    <option value="1" @selected(old('team_id', $player->team_id) == '1')>Others</option>
                                </select>
                                @error('team_id')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror

                                <div x-show="selectedTeam === '1'" x-transition class="mt-4">
                                    <label for="team_name_ref" class="block mb-1">Team Name</label>
                                    <input type="text" name="team_name_ref" id="team_name_ref" x-model="newTeamName"
                                        class="form-control @error('team_name_ref') border-red-500 @enderror"
                                        x-bind:required="selectedTeam === '1'" placeholder="Enter your team name">
                                    @error('team_name_ref')
                                        <p class="text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>


                            {{-- Jersey Name --}}
                            <div class="space-y-1">
                                <label for="jersey_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Jersey Name') }}
                                </label>
                                <input type="text" name="jersey_name" id="jersey_name"
                                    value="{{ old('jersey_name', $player->jersey_name) }}"
                                    placeholder="Enter Jersey Name"
                                    class="form-control @error('jersey_name') border-red-500 @enderror">
                                @error('jersey_name')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Jersey Size --}}
                            <div class="space-y-1">
                                <label for="kit_size_id"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Jersey Size') }}
                                </label>
                                <select name="kit_size_id" id="kit_size_id"
                                    class="form-control @error('kit_size_id') border-red-500 @enderror">
                                    <option value="">-- Select Jersey Size --</option>
                                    @foreach ($kitSizes as $kit)
                                        <option value="{{ $kit->id }}"
                                            {{ old('kit_size_id', $player->kit_size_id) == $kit->id ? 'selected' : '' }}>
                                            {{ $kit->size }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('kit_size_id')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Batting Profile --}}
                            <div class="space-y-1">
                                <label for="batting_profile_id"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Batting Profile') }}
                                </label>
                                <select name="batting_profile_id" id="batting_profile_id"
                                    class="form-control @error('batting_profile_id') border-red-500 @enderror">
                                    <option value="">-- Select Batting Profile --</option>
                                    @foreach ($battingProfiles as $profile)
                                        <option value="{{ $profile->id }}"
                                            {{ old('batting_profile_id', $player->batting_profile_id) == $profile->id ? 'selected' : '' }}>
                                            {{ $profile->style }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('batting_profile_id')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Bowling Profile --}}
                            <div class="space-y-1">
                                <label for="bowling_profile_id"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Bowling Profile') }}
                                </label>
                                <select name="bowling_profile_id" id="bowling_profile_id"
                                    class="form-control @error('bowling_profile_id') border-red-500 @enderror">
                                    <option value="">-- Select Bowling Profile --</option>
                                    @foreach ($bowlingProfiles as $profile)
                                        <option value="{{ $profile->id }}"
                                            {{ old('bowling_profile_id', $player->bowling_profile_id) == $profile->id ? 'selected' : '' }}>
                                            {{ $profile->style }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('bowling_profile_id')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Player Type --}}
                            <div class="space-y-1">
                                <label for="player_type_id"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Player Type') }}
                                </label>
                                <select name="player_type_id" id="player_type_id"
                                    class="form-control @error('player_type_id') border-red-500 @enderror">
                                    <option value="">-- Select Player Type --</option>
                                    @foreach ($playerTypes as $type)
                                        <option value="{{ $type->id }}"
                                            {{ old('player_type_id', $player->player_type_id) == $type->id ? 'selected' : '' }}>
                                            {{ $type->type }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('player_type_id')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Player Image Upload --}}
                            <div class="space-y-1 sm:col-span-2">
                                <label for="image_path"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Player Image') }}
                                </label>
                                @if ($player->image_path)
                                    <div class="mb-3">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Current Image:</p>
                                        <img src="{{ Storage::url($player->image_path) }}" alt="{{ $player->name }}"
                                            class="w-32 h-32 object-cover rounded-md mt-2">
                                        <div class="flex items-center mt-2">
                                            <input type="checkbox" name="clear_image" id="clear_image" value="1"
                                                class="mr-2">
                                            <label for="clear_image" class="text-sm text-gray-700 dark:text-gray-300">
                                                {{ __('Remove current image') }}
                                            </label>
                                        </div>
                                    </div>
                                @endif
                                <input type="file" name="image_path" id="image_path"
                                    class="form-control @error('image_path') border-red-500 @enderror">
                                @error('image_path')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Wicket Keeper Checkbox --}}
                            <div class="space-y-1">
                                <label for="is_wicket_keeper"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="is_wicket_keeper" id="is_wicket_keeper" value="1"
                                        class="mr-2"
                                        {{ old('is_wicket_keeper', $player->is_wicket_keeper) ? 'checked' : '' }}>
                                    {{ __('Is Wicket Keeper?') }}
                                </label>
                                @error('is_wicket_keeper')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Transportation Required Checkbox --}}
                            <div class="space-y-1">
                                <label for="transportation_required"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="transportation_required" id="transportation_required"
                                        value="1" class="mr-2"
                                        {{ old('transportation_required', $player->transportation_required) ? 'checked' : '' }}>
                                    {{ __('Transportation Required?') }}
                                </label>
                                @error('transportation_required')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                        </div>

                        <div class="mt-6">
                            <x-buttons.submit-buttons cancelUrl="{{ route('admin.players.index') }}" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
