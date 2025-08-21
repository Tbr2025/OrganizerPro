<div id="member-card-{{ $member->id }}"
    class="flex items-center p-2 bg-white dark:bg-gray-800 rounded shadow-sm border mb-2"
    data-user-id="{{ $member->id }}">

    <img class="h-10 w-10 rounded-full object-cover mr-3"
        src="https://ui-avatars.com/api/?name={{ urlencode($member->name) }}&color=7F9CF5&background=EBF4FF"
        alt="{{ $member->name }}">

    <div class="flex-1">
        <div class="flex items-center gap-2">
            <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $member->name }}</div>
            @if ($member->player && $member->player->player_mode === 'retained')
                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800">
                    Retained
                </span>
            @endif

         
        </div>

        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $member->email }}</div>

        {{-- Roles --}}
        <div class="text-xs text-gray-400">
            Roles: {{ $member->roles->pluck('name')->implode(', ') }}
        </div>
    </div>

    {{-- Remove button --}}
    <button type="button" class="remove-member-btn text-red-500 hover:text-red-700 p-1"
        data-user-id="{{ $member->id }}" title="Remove Member">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </button>

    {{-- Hidden inputs --}}
    <input type="hidden" name="members[]" value="{{ $member->id }}">
    <input type="hidden" name="user_roles[{{ $member->id }}]"
        value="{{ $member->roles->pluck('name')->implode(',') }}">
</div>
