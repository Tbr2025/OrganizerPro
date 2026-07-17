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
            @if($member->player)
                <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-mono font-medium rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">#{{ $member->player->id }}</span>
                <a href="{{ route('admin.players.show', $member->player->id) }}" target="_blank"
                    class="text-indigo-500 hover:text-indigo-700" title="View details">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            @endif
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
        <div class="flex items-center gap-1.5 mt-0.5">
            @if($member->player)
                @php
                    $mStatusConfig = match($member->player->status) {
                        'approved' => ['bg' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', 'label' => 'Approved'],
                        'rejected' => ['bg' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200', 'label' => 'Rejected'],
                        default => ['bg' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200', 'label' => 'Pending'],
                    };
                @endphp
                <span class="inline-flex items-center px-1.5 py-0.5 text-xs font-medium rounded {{ $mStatusConfig['bg'] }} status-badge-{{ $member->player->id }}">
                    {{ $mStatusConfig['label'] }}
                </span>
                <div class="flex items-center gap-0.5 status-actions-{{ $member->player->id }}">
                    @if($member->player->status !== 'approved')
                        <button type="button" onclick="setPlayerStatus({{ $member->player->id }}, 'approved', this)"
                            class="p-0.5 text-green-600 hover:bg-green-100 dark:hover:bg-green-900/50 rounded" title="Approve">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </button>
                    @endif
                    @if($member->player->status !== 'rejected')
                        <button type="button" onclick="setPlayerStatus({{ $member->player->id }}, 'rejected', this)"
                            class="p-0.5 text-red-600 hover:bg-red-100 dark:hover:bg-red-900/50 rounded" title="Reject">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                    @if($member->player->status !== 'pending')
                        <button type="button" onclick="setPlayerStatus({{ $member->player->id }}, 'pending', this)"
                            class="p-0.5 text-amber-600 hover:bg-amber-100 dark:hover:bg-amber-900/50 rounded" title="Set Pending">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </button>
                    @endif
                </div>
            @endif
            <span class="inline-flex items-center px-1.5 py-0.5 text-xs font-medium rounded {{ $roleColors }}">
                {{ $roleLabel }}
            </span>
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
