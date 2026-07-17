@php
    $playerImage = $member->player?->image_path 
        ? Storage::url($member->player->image_path)
        : null;
@endphp

<div id="member-card-{{ $member->id }}"
    class="flex items-center p-2 bg-white dark:bg-gray-800 rounded shadow-sm border mb-2"
    data-user-id="{{ $member->id }}">

 <img class="h-10 w-10 rounded-full object-cover mr-3"
     src="{{ $playerImage ?? 'https://ui-avatars.com/api/?name=' . urlencode($member->name) . '&color=7F9CF5&background=EBF4FF' }}"
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

        {{-- Team Role --}}
        @php
            $pivotRole = $member->pivot?->role;
            $roleLabel = $pivotRole ? ucfirst($pivotRole) : 'Player';
            $roleColors = match(strtolower($roleLabel)) {
                'owner' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                'manager' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
                'captain' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                default => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            };
        @endphp
        @if($member->player)
            <button type="button"
                onclick="toggleApprove({{ $member->player->id }}, this)"
                class="inline-flex items-center px-1.5 py-0.5 text-xs font-medium rounded cursor-pointer transition-colors {{ $member->player->status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' }}"
                data-status="{{ $member->player->status }}"
                title="Click to {{ $member->player->status === 'approved' ? 'unapprove' : 'approve' }}">
                {{ ucfirst($member->player->status) }}
            </button>
        @endif
        <span class="inline-flex items-center px-1.5 py-0.5 text-xs font-medium rounded {{ $roleColors }}">
            {{ $roleLabel }}
        </span>
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
