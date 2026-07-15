@php
    $fieldId = 'user_roles_' . $user->id;
    $userRoleNames = $user->roles->pluck('name')->toArray();
    $playerImage = $user->player?->image_path
        ? Storage::url($user->player->image_path)
        : null;
    $avatarUrl = $playerImage ?? 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&color=7F9CF5&background=EBF4FF';
    $hasPlayer = (bool) $user->player;
@endphp
<div id="available-user-{{ $user->id }}"
    class="flex items-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg parent{{ $hasPlayer ? ' cursor-grab active:cursor-grabbing' : '' }}"
    data-user-id="{{ $user->id }}"
    data-user-name="{{ $user->name }}" data-user-email="{{ $user->email }}"
    @if($hasPlayer)
        data-player-id="{{ $user->player->id }}"
        data-user-avatar="{{ $avatarUrl }}"
        draggable="true"
        ondragstart="event.dataTransfer.setData('application/json', JSON.stringify({
            playerId: {{ $user->player->id }},
            userId: {{ $user->id }},
            name: {!! json_encode($user->name) !!},
            email: {!! json_encode($user->email) !!},
            avatar: {!! json_encode($avatarUrl) !!}
        })); event.dataTransfer.effectAllowed = 'copy';"
    @endif
    x-data="{
        isRetained: @js(optional($user->player)->player_mode === 'retained'),
        showRolePopover: false,
    }">

{{-- Drag handle (only for users with player records) --}}
@if($hasPlayer)
<div class="flex-shrink-0 mr-2 text-gray-400 dark:text-gray-500" title="Drag to add to tournament">
    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
        <circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>
        <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
        <circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>
    </svg>
</div>
@endif

<img class="h-10 w-10 rounded-full object-cover mr-3"
     src="{{ $avatarUrl }}"
     alt="{{ $user->name }}">

   <div class="flex-1">
        <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $user->name }}</div>
        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</div>

        {{-- Role tags --}}
        <div class="flex flex-wrap gap-1 mt-1">
            @if($user->player?->playerType)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                    {{ $user->player->playerType->type }}
                </span>
            @endif
            @foreach($user->roles as $role)
                @if(!in_array($role->name, ['Superadmin', 'Admin']))
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                        @if($role->name === 'Manager') bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300
                        @elseif($role->name === 'Captain') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300
                        @else bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-300
                        @endif">
                        {{ $role->name }}
                    </span>
                @endif
            @endforeach
        </div>

        <div class="flex items-center gap-2 mt-1">
            <label class="flex items-center text-xs text-gray-600 dark:text-gray-300 cursor-pointer">
                <input type="checkbox" x-model="isRetained" class="mr-1 rounded border-gray-300 text-green-600 focus:ring-green-500">
                Retained
            </label>
        </div>
    </div>

{{-- Hidden input for retained status --}}
<input type="hidden" id="user_retained_{{ $user->id }}" name="user_retained[{{ $user->id }}]"
        :value="isRetained ? 'retained' : 'normal'">

    {{-- "+" button with role popover (staff roles only) --}}
    <div class="relative">
        <button type="button" class="text-green-500 hover:text-green-700 p-1" title="Add as Staff"
            @click="showRolePopover = !showRolePopover">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>

        {{-- Role selection popover (excludes Player — use drag-and-drop for players) --}}
        <div x-show="showRolePopover" x-transition
            @click.outside="showRolePopover = false"
            class="absolute right-0 top-8 z-50 w-48 rounded-lg border border-gray-200 dark:border-gray-600 bg-white/80 dark:bg-gray-800/80 backdrop-blur-md shadow-lg py-1">
            <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Add as...</div>
            @foreach($roles as $role)
                @if($role->name !== 'Player' || $hasPlayer)
                    <button type="button"
                        class="add-member-btn w-full text-left px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors"
                        data-user-id="{{ $user->id }}"
                        data-role="{{ $role->name }}">
                        {{ $role->name }}
                    </button>
                @endif
            @endforeach
        </div>
    </div>
</div>
