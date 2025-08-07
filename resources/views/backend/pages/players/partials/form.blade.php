<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    {{-- Player Name --}}
    <div class="space-y-1">
        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Player Name') }}
        </label>
        <input type="text" name="name" id="name" required value="{{ old('name', $player->name ?? '') }}"
            placeholder="Enter Player Name" class="form-control">
    </div>

    {{-- Team --}}
    <div class="space-y-1">
        <label for="team_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Select Team') }}
        </label>
        <select name="team_id" id="team_id" class="form-control">
            <option value="">-- Select Team --</option>
            @foreach ($teams as $team)
                <option value="{{ $team->id }}"
                    {{ old('team_id', $player->team_id ?? '') == $team->id ? 'selected' : '' }}>
                    {{ $team->name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Jersey Name --}}
    <div class="space-y-1">
        <label for="jersey_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Jersey Name') }}
        </label>
        <input type="text" name="jersey_name" id="jersey_name"
            value="{{ old('jersey_name', $player->jersey_name ?? '') }}" placeholder="Enter Jersey Name"
            class="form-control">
    </div>

    {{-- Jersey Size --}}
    <div class="space-y-1">
        <label for="kit_size_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Jersey Size') }}
        </label>
        <select name="kit_size_id" id="kit_size_id" class="form-control">
            <option value="">-- Select Jersey Size --</option>
            @foreach ($kitSizes as $kit)
                <option value="{{ $kit->id }}"
                    {{ old('kit_size_id', $player->kit_size_id ?? '') == $kit->id ? 'selected' : '' }}>
                    {{ $kit->size }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Cricheroes No --}}
    <div class="space-y-1">
        <label for="cricheroes_no" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Cricheroes No') }}
        </label>
        <input type="text" name="cricheroes_no" id="cricheroes_no"
            value="{{ old('cricheroes_no', $player->cricheroes_no ?? '') }}" placeholder="Enter Cricheroes Number"
            class="form-control">
    </div>

    {{-- Mobile No --}}
    <div class="space-y-1">
        <label for="mobile_no" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Mobile No.') }}
        </label>
        <input type="text" name="mobile_no" id="mobile_no" value="{{ old('mobile_no', $player->mobile_no ?? '') }}"
            placeholder="Enter Mobile Number" class="form-control">
    </div>

    {{-- Batting Profile --}}
    <div class="space-y-1">
        <label for="batting_profile_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Batting Profile') }}
        </label>
        <select name="batting_profile_id" id="batting_profile_id" class="form-control">
            <option value="">-- Select Batting Profile --</option>
            @foreach ($battingProfiles as $profile)
                <option value="{{ $profile->id }}"
                    {{ old('batting_profile_id', $player->batting_profile_id ?? '') == $profile->id ? 'selected' : '' }}>
                    {{ $profile->style }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Bowling Profile --}}
    <div class="space-y-1">
        <label for="bowling_profile_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Bowling Profile') }}
        </label>
        <select name="bowling_profile_id" id="bowling_profile_id" class="form-control">
            <option value="">-- Select Bowling Profile --</option>
            @foreach ($bowlingProfiles as $profile)
                <option value="{{ $profile->id }}"
                    {{ old('bowling_profile_id', $player->bowling_profile_id ?? '') == $profile->id ? 'selected' : '' }}>
                    {{ $profile->style }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Player Type --}}
    <div class="space-y-1">
        <label for="player_type_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Player Type') }}
        </label>
        <select name="player_type_id" id="player_type_id" class="form-control">
            <option value="">-- Select Player Type --</option>
            @foreach ($playerTypes as $type)
                <option value="{{ $type->id }}"
                    {{ old('player_type_id', $player->player_type_id ?? '') == $type->id ? 'selected' : '' }}>
                    {{ $type->type }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Wicket Keeper --}}
    <div class="space-y-1">
        <label for="is_wicket_keeper" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Wicket Keeper') }}
        </label>
        <select name="is_wicket_keeper" id="is_wicket_keeper" class="form-control">
            <option value="0"
                {{ old('is_wicket_keeper', $player->is_wicket_keeper ?? '0') == '0' ? 'selected' : '' }}>No</option>
            <option value="1"
                {{ old('is_wicket_keeper', $player->is_wicket_keeper ?? '0') == '1' ? 'selected' : '' }}>Yes</option>
        </select>
    </div>

    {{-- Transportation --}}
    <div class="space-y-1">
        <label for="transportation_required" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Transportation Required') }}
        </label>
        <select name="transportation_required" id="transportation_required" class="form-control">
            <option value="0"
                {{ old('transportation_required', $player->transportation_required ?? '0') == '0' ? 'selected' : '' }}>
                No</option>
            <option value="1"
                {{ old('transportation_required', $player->transportation_required ?? '0') == '1' ? 'selected' : '' }}>
                Yes</option>
        </select>
    </div>
</div>
