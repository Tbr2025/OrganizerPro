@extends('backend.layouts.app')

@section('title', 'Create Tournament | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-2xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[['label' => 'Tournaments', 'url' => route('admin.tournaments.index')], ['label' => 'Create']]" />

        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl p-6">
            <form method="POST" action="{{ route('admin.tournaments.store') }}" class="space-y-6">
                @csrf
                {{-- Organization --}}
                {{-- Organization --}}
                <div>
                    <label for="organization_id" class="block text-sm font-medium">Organization</label>
                    <select id="organization_id" name="organization_id" required class="mt-1 block w-full border rounded">
                        <option value="">Select Organization</option>
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                        @endforeach
                    </select>
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
        });
    </script>
@endpush
