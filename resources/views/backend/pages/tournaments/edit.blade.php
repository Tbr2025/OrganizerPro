@extends('backend.layouts.app')

@section('title', (auth()->user()->can('tournament.edit') ? 'Edit' : 'View') . ' Tournament | ' . config('app.name'))

@php $canEdit = auth()->user()->can('tournament.edit'); @endphp

@section('admin-content')
    <div class="p-4 mx-auto max-w-2xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[
            ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
            ['label' => $canEdit ? 'Edit' : 'View']
        ]" />

        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl p-6">
            <form method="POST" action="{{ route('admin.tournaments.update', $tournament->id) }}" class="space-y-6" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <fieldset {{ $canEdit ? '' : 'disabled' }}>

                {{-- Logo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tournament Logo</label>
                    @if($canEdit)
                        <x-logo-cropper name="logo" :existingImage="$tournament->logo" :circular="false" :ratios="[
                            ['label' => 'Square 1:1', 'value' => 1],
                            ['label' => 'Wide 16:9', 'value' => 16/9],
                            ['label' => 'Free', 'value' => 'free'],
                        ]" />
                        <p class="text-xs text-gray-500 mt-1">Recommended: 512x512px, PNG or JPG</p>
                    @elseif($tournament->logo)
                        <img src="{{ Storage::url($tournament->logo) }}" alt="{{ $tournament->name }}" class="w-24 h-24 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                    @else
                        <p class="text-sm text-gray-500">No logo uploaded</p>
                    @endif
                    @error('logo')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Organization --}}
                <div>
                    <label for="organization_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organization <span class="text-red-500">*</span></label>
                    <select id="organization_id" name="organization_id" required class="mt-1 block w-full border rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="">Select Organization</option>
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization->id }}"
                                {{ old('organization_id', $tournament->organization_id) == $organization->id ? 'selected' : '' }}>
                                {{ $organization->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('organization_id')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Zone --}}
                <div>
                    <label for="zone_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Zone</label>
                    <select id="zone_id" name="zone_id" class="mt-1 block w-full border rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="">No Zone (General)</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id', $tournament->zone_id) == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Optional: Assign this tournament to a zone</p>
                    @error('zone_id')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Location --}}
                <div class="mt-4">
                    <label for="location" class="block text-sm font-medium">Location</label>
                    <input type="text" name="location" id="location"
                        value="{{ old('location', $tournament->location) }}"
                        placeholder="Enter location"
                        class="mt-1 block w-full rounded border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('location')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    <input type="text" name="name" id="name"
                        value="{{ old('name', $tournament->name) }}" required
                        placeholder="Enter tournament name"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300">URL Slug</label>
                    <input type="text" name="slug" id="slug"
                        value="{{ old('slug', $tournament->slug) }}"
                        placeholder="tournament-url-slug"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="text-xs text-gray-500 mt-1">URL: {{ url('/t') }}/<span id="slug-preview" class="font-medium">{{ $tournament->slug }}</span></p>
                    @error('slug')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Start Date --}}
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                    <input type="text" name="start_date" id="start_date" 
                        value="{{ old('start_date', $tournament->start_date->format('Y-m-d')) }}" required
                        placeholder="Select start date"
                        class="flatpickr-input mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('start_date')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- End Date --}}
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                    <input type="text" name="end_date" id="end_date"
                        value="{{ old('end_date', $tournament->end_date->format('Y-m-d')) }}" required
                        placeholder="Select end date"
                        class="flatpickr-input mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('end_date')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Status --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select id="status" name="status" class="mt-1 block w-full border rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="draft" {{ old('status', $tournament->status) == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="registration" {{ old('status', $tournament->status) == 'registration' ? 'selected' : '' }}>Registration Open</option>
                        <option value="active" {{ old('status', $tournament->status) == 'active' ? 'selected' : '' }}>Active/Ongoing</option>
                        <option value="completed" {{ old('status', $tournament->status) == 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Current status: <span class="font-medium">{{ ucfirst($tournament->status) }}</span></p>
                    @error('status')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Type --}}
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tournament Type</label>
                    <select id="type" name="type" class="mt-1 block w-full border rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="open" {{ old('type', $tournament->type) == 'open' ? 'selected' : '' }}>Open — team & player registration only</option>
                        <option value="auction" {{ old('type', $tournament->type) == 'auction' ? 'selected' : '' }}>Auction — retained players, pools & live auction</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Auction unlocks retained players, pools and the auction module.</p>
                    @error('type')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Budget Per Team --}}
                <div class="mt-4">
                    <label for="budget_display" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Budget Per Team</label>
                    @php
                        $budgetRaw = old('max_budget_per_team', $auction->max_budget_per_team ?? 100000000);
                        $budgetDisplay = $budgetRaw ? $budgetRaw / 1000000 : 100;
                    @endphp
                    <input type="number" id="budget_display"
                        value="{{ $budgetDisplay }}"
                        placeholder="e.g. 100"
                        min="0" step="any"
                        oninput="document.getElementById('budget_raw').value = this.value ? Math.round(this.value * 1000000) : ''"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <input type="hidden" name="max_budget_per_team" id="budget_raw"
                        value="{{ $budgetRaw }}">
                    <p class="text-xs text-gray-500 mt-1">Enter in millions (e.g. 100 = 10,00,00,000). Default: 100M.</p>
                    @error('max_budget_per_team')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror

                    {{-- Global Update Default --}}
                    @if($auction && $auction->exists)
                    <div class="mt-3">
                        <button type="button" id="global-budget-btn"
                            onclick="if(confirm('This will update all teams\' auction budget to ' + document.getElementById('budget_display').value + 'M. Continue?')) { document.getElementById('global-budget-form').submit(); }"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 dark:text-indigo-300 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20 rounded-lg transition">
                            <iconify-icon icon="lucide:refresh-cw" width="14"></iconify-icon>
                            Global Update Default
                        </button>
                        <p class="text-xs text-gray-400 mt-1">Apply this budget to all teams in this tournament.</p>
                    </div>
                    {{-- Moved outside main form to avoid nested forms --}}
                    <script>
                        document.getElementById('budget_display').addEventListener('input', function() {
                            document.getElementById('global_budget_value').value = this.value ? Math.round(this.value * 1000000) : '';
                        });
                    </script>
                    @endif
                </div>

                {{-- Squad Size --}}
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label for="max_players_per_team" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Max Players Per Team</label>
                        <input type="number" name="max_players_per_team" id="max_players_per_team"
                            value="{{ old('max_players_per_team', $settings->max_players_per_team ?? 20) }}"
                            min="1" max="50"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('max_players_per_team')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="min_players_per_team" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Min Players Per Team</label>
                        <input type="number" name="min_players_per_team" id="min_players_per_team"
                            value="{{ old('min_players_per_team', $settings->min_players_per_team ?? 11) }}"
                            min="1" max="50"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('min_players_per_team')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Auction rules the tournament owns.
                     Squad size, how many icon players a team keeps and what they cost, and the
                     price a player starts at are facts about the competition rather than about
                     one auction evening. Every auction in this tournament inherits them unless
                     it is explicitly set to override — see Auction::rule(). --}}
                <div class="mt-6 pt-5 border-t border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Auction rules</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-4">
                        Inherited by every auction in this tournament. An individual auction can
                        override them from its own edit screen.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="icon_players_per_team" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Icon Players Per Team</label>
                            <input type="number" name="icon_players_per_team" id="icon_players_per_team"
                                value="{{ old('icon_players_per_team', $settings->icon_players_per_team) }}"
                                min="0" max="50" placeholder="0"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            {{-- The arithmetic that follows from it, said out loud: this is the
                                 number that decides how many places are left to auction. --}}
                            <p class="text-xs text-gray-500 mt-1">
                                Kept by the team before the auction. A squad of
                                {{ $settings->max_players_per_team ?? $settings->min_players_per_team ?? 11 }}
                                with {{ $settings->icon_players_per_team ?: 0 }} icons leaves
                                <strong>{{ max(0, ($settings->max_players_per_team ?? $settings->min_players_per_team ?? 11) - (int) ($settings->icon_players_per_team ?: 0)) }}</strong>
                                to auction.
                            </p>
                            @error('icon_players_per_team')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="icon_player_value" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Icon Player Value</label>
                            <input type="number" step="0.01" name="icon_player_value" id="icon_player_value"
                                value="{{ old('icon_player_value', $settings->icon_player_value) }}"
                                min="0" placeholder="e.g. 5000000"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p class="text-xs text-gray-500 mt-1">What an icon player costs their team's purse when no price is entered for them.</p>
                            @error('icon_player_value')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="player_base_value" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Player Base Value</label>
                            <input type="number" step="0.01" name="player_base_value" id="player_base_value"
                                value="{{ old('player_base_value', $settings->player_base_value) }}"
                                min="0" placeholder="e.g. 1000000"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p class="text-xs text-gray-500 mt-1">The price a player starts at when they come up.</p>
                            @error('player_base_value')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- The amounts switch. Never hides figures from the organizer's own panel:
                         that is the working tool and needs every number. --}}
                    <label class="mt-4 flex items-start gap-2.5 cursor-pointer">
                        <input type="hidden" name="show_amounts" value="0">
                        <input type="checkbox" name="show_amounts" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600"
                               {{ old('show_amounts', $settings->show_amounts ?? true) ? 'checked' : '' }}>
                        <span>
                            <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Show amounts on public screens</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                Prices on the LED wall, posters, team screens and squad lists. Turn
                                it off and the Icon Player badge stays but the money is withheld.
                                The organizer's panel always shows every figure.
                            </span>
                        </span>
                    </label>
                </div>

                </fieldset>

                {{-- Actions --}}
                <div class="flex items-center justify-end space-x-3 pt-4">
                    <a href="{{ route('admin.tournaments.index') }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                        {{ $canEdit ? 'Cancel' : 'Back' }}
                    </a>

                    @if($canEdit)
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Update Tournament
                    </button>
                    @endif
                </div>
            </form>

            @if(isset($auction) && $auction && $auction->exists)
            <form id="global-budget-form" action="{{ route('admin.tournaments.global-budget', $tournament) }}" method="POST" class="hidden">
                @csrf
                <input type="hidden" name="max_budget_per_team" id="global_budget_value" value="{{ $budgetRaw ?? '' }}">
            </form>
            @endif
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Slug preview
            const slugInput = document.getElementById('slug');
            const slugPreview = document.getElementById('slug-preview');
            if (slugInput && slugPreview) {
                slugInput.addEventListener('input', function() {
                    slugPreview.textContent = this.value || '{{ $tournament->slug }}';
                });
            }

            const today = new Date().toISOString().split("T")[0];

            const startDate = "{{ old('start_date', $tournament->start_date->format('Y-m-d')) }}";
            const endDate = "{{ old('end_date', $tournament->end_date->format('Y-m-d')) }}";

            flatpickr("#start_date", {
                dateFormat: "Y-m-d",
                defaultDate: startDate,
                onChange: function(selectedDates, dateStr, instance) {
                    endPicker.set('minDate', dateStr);
                }
            });

            const endPicker = flatpickr("#end_date", {
                dateFormat: "Y-m-d",
                defaultDate: endDate,
                minDate: startDate,
            });

            // Fetch zones when organization changes
            const organizationSelect = document.getElementById('organization_id');
            const zoneSelect = document.getElementById('zone_id');
            const currentZoneId = "{{ old('zone_id', $tournament->zone_id) }}";

            organizationSelect.addEventListener('change', function() {
                const organizationId = this.value;
                zoneSelect.innerHTML = '<option value="">Loading...</option>';

                if (!organizationId) {
                    zoneSelect.innerHTML = '<option value="">No Zone (General)</option>';
                    return;
                }

                fetch(`{{ url('admin/zones/by-organization') }}?organization_id=${organizationId}`)
                    .then(response => response.json())
                    .then(zones => {
                        zoneSelect.innerHTML = '<option value="">No Zone (General)</option>';
                        zones.forEach(zone => {
                            const option = document.createElement('option');
                            option.value = zone.id;
                            option.textContent = zone.name;
                            if (zone.id == currentZoneId) {
                                option.selected = true;
                            }
                            zoneSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching zones:', error);
                        zoneSelect.innerHTML = '<option value="">No Zone (General)</option>';
                    });
            });
        });
    </script>
@endpush
