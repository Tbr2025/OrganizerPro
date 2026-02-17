@extends('backend.layouts.app')

@section('title', 'Edit Tournament | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-2xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[
            ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
            ['label' => 'Edit']
        ]" />

        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl p-6">
            <form method="POST" action="{{ route('admin.tournaments.update', $tournament->id) }}" class="space-y-6">
                @csrf
                @method('PUT')

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
                        placeholder="Enter location" required
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

                {{-- Actions --}}
                <div class="flex items-center justify-end space-x-3 pt-4">
                    <a href="{{ route('admin.tournaments.index') }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cancel
                    </a>

                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Update Tournament
                    </button>
                </div>
            </form>
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
            const today = new Date().toISOString().split("T")[0];

            flatpickr("#start_date", {
                minDate: "today",
                dateFormat: "Y-m-d",
                defaultDate: "{{ old('start_date', $tournament->start_date->format('Y-m-d')) }}",
                onChange: function(selectedDates, dateStr, instance) {
                    endPicker.set('minDate', dateStr);
                }
            });

            const endPicker = flatpickr("#end_date", {
                minDate: "today",
                dateFormat: "Y-m-d",
                defaultDate: "{{ old('end_date', $tournament->end_date->format('Y-m-d')) }}",
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
