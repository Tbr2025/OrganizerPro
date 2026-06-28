@extends('backend.layouts.app')

@section('title', 'Create Tournament | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-2xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[['label' => 'Tournaments', 'url' => route('admin.tournaments.index')], ['label' => 'Create']]" />

        @if(!empty($organizationLimits))
            @if($organizationLimits['reached'])
                <div class="mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <iconify-icon icon="lucide:alert-triangle" class="text-red-500 text-xl"></iconify-icon>
                        <div>
                            <p class="text-sm font-semibold text-red-800 dark:text-red-300">Tournament limit reached</p>
                            <p class="text-xs text-red-600 dark:text-red-400">Your organization has used all {{ $organizationLimits['max'] }} tournament slots. Contact your administrator to upgrade.</p>
                        </div>
                    </div>
                </div>
            @elseif($organizationLimits['remaining'] !== null && $organizationLimits['remaining'] <= 2)
                <div class="mt-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <iconify-icon icon="lucide:alert-circle" class="text-yellow-500 text-xl"></iconify-icon>
                        <div>
                            <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-300">Approaching tournament limit</p>
                            <p class="text-xs text-yellow-600 dark:text-yellow-400">{{ $organizationLimits['remaining'] }} of {{ $organizationLimits['max'] }} slots remaining.</p>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl p-6">
            <form method="POST" action="{{ route('admin.tournaments.store') }}" class="space-y-6" enctype="multipart/form-data">
                @csrf

                {{-- Logo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tournament Logo</label>
                    <x-logo-cropper name="logo" :circular="false" :ratios="[
                        ['label' => 'Square 1:1', 'value' => 1],
                        ['label' => 'Wide 16:9', 'value' => 16/9],
                        ['label' => 'Free', 'value' => 'free'],
                    ]" />
                    <p class="text-xs text-gray-500 mt-1">Recommended: 512x512px, PNG or JPG</p>
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
                            <option value="{{ $organization->id }}" {{ old('organization_id') == $organization->id ? 'selected' : '' }}>{{ $organization->name }}</option>
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
                            <option value="{{ $zone->id }}" {{ old('zone_id') == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Optional: Assign this tournament to a zone</p>
                    @error('zone_id')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Location --}}
                {{-- Location --}}
                <div class="mt-4">
                    <label for="location" class="block text-sm font-medium">Location</label>
                    <input type="text" name="location" id="location" value="{{ old('location') }}"
                        placeholder="Enter location" required
                        class="mt-1 block w-full rounded border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('location')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>


                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                        placeholder="Enter tournament name"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Start Date --}}
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start
                        Date</label>
                    <input type="text" name="start_date" id="start_date" value="{{ old('start_date') }}" required
                        placeholder="Select start date"
                        class="flatpickr-input mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('start_date')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- End Date --}}
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">End
                        Date</label>
                    <input type="text" name="end_date" id="end_date" value="{{ old('end_date') }}" required
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
                        <option value="draft" {{ old('status', 'draft') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="registration" {{ old('status') == 'registration' ? 'selected' : '' }}>Registration Open</option>
                        <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active/Ongoing</option>
                        <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Set the current status of the tournament</p>
                    @error('status')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Type --}}
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tournament Type</label>
                    <select id="type" name="type" class="mt-1 block w-full border rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="open" {{ old('type', 'open') == 'open' ? 'selected' : '' }}>Open — team & player registration only</option>
                        <option value="auction" {{ old('type') == 'auction' ? 'selected' : '' }}>Auction — retained players, pools & live auction</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Auction unlocks retained players, pools and the auction module.</p>
                    @error('type')
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
                        Create Tournament
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
                onChange: function(selectedDates, dateStr, instance) {
                    endPicker.set('minDate', dateStr);
                }
            });

            const endPicker = flatpickr("#end_date", {
                minDate: "today",
                dateFormat: "Y-m-d",
            });

            // Fetch zones when organization changes
            const organizationSelect = document.getElementById('organization_id');
            const zoneSelect = document.getElementById('zone_id');

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
