@php
    $fieldId = 'user_roles_' . $user->id;
@endphp
<div id="available-user-{{ $user->id }}"
    class="flex items-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg parent" data-user-id="{{ $user->id }}"
    data-user-name="{{ $user->name }}" data-user-email="{{ $user->email }}" x-data="{
        selectedRoles: @js($user->roles->pluck('name')->toArray()),
        allAvailableRoles: @js(collect($roles)->pluck('name')->toArray()),
        combinedRoles: [],
        isRetained: @js(optional($user->player)->player_mode === 'retained'),
    
        sync() {
            document.getElementById('user_roles_selected_{{ $user->id }}').value = this.selectedRoles.join(',');
            this.updateCombinedRoles();
        },
    
        updateCombinedRoles() {
            const combined = new Set([...this.selectedRoles, ...this.allAvailableRoles]);
            this.combinedRoles = Array.from(combined);
        },
    
        initSync() {
            this.sync();
            this.updateCombinedRoles();
        },
    
        handleComboboxSelection(event) {
            if (event.detail && event.detail.newSelectedRoles !== undefined) {
                this.selectedRoles = event.detail.newSelectedRoles;
                this.sync();
            }
        }
    }"
    x-init="initSync()" @selection-updated.window="handleComboboxSelection($event)" {{-- Listen for the custom event --}}>

@php
    $playerImage = $user->player?->image_path 
        ? Storage::url($user->player->image_path)
        : null;
@endphp
<img class="h-10 w-10 rounded-full object-cover mr-3"
     src="{{ $playerImage ?? 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&color=7F9CF5&background=EBF4FF' }}"
     alt="{{ $user->name }}">

   <div class="flex-1">
        <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $user->name }}</div>
        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
        <div class="flex items-center gap-2 mt-1">
            <label class="flex items-center text-xs text-gray-600 dark:text-gray-300 cursor-pointer">
                {{-- Add @change="sync()" here --}}
                <input type="checkbox" x-model="isRetained" @change="sync()" class="mr-1 rounded border-gray-300 text-green-600 focus:ring-green-500">
                Retained
            </label>
        </div>
    </div>
<input type="hidden" id="user_retained_{{ $user->id }}" name="user_retained[{{ $user->id }}]"
        :value="isRetained ? 'retained' : 'normal'">


    <div class="w-40 mr-2">
        {{--
            *** IMPORTANT MODIFICATION ***
            The x-inputs.combobox component should NOT use x-model or x-on:change
            that directly tie into the outer scope's selectedRoles and sync().
            Instead, it should manage its internal selections and DISPATCH an event.
            The parent listens for this event and updates its own selectedRoles.
        --}}
        <x-inputs.combobox id="user_roles_{{ $user->id }}" name="user_roles[{{ $user->id }}][]"
            label="Assign Roles" placeholder="Select Roles" :options="collect($roles)
                ->map(fn($role) => ['value' => $role->name, 'label' => ucfirst($role->name)])
                ->values()
                ->toArray()" :selected="$user->roles->pluck('name')->toArray()" {{-- This might be used by the combobox for initial display --}}
            :multiple="true" :searchable="false" />
        {{-- The hidden input managed by the outer scope --}}
        <input type="hidden" id="user_roles_selected_{{ $user->id }}"
            name="user_roles_selected[{{ $user->id }}]" {{-- Name for the comma-separated string --}} :value="selectedRoles.join(',')">

        {{-- The combined roles hidden input --}}
        <input type="hidden" id="user_roles_combined_{{ $user->id }}"
            name="user_roles_combined[{{ $user->id }}]" :value="combinedRoles.join(',')">
    </div>

    <button type="button" class="add-member-btn text-green-500 hover:text-green-700 p-1" title="Add Member"
        data-user-id="{{ $user->id }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </button>
</div>
