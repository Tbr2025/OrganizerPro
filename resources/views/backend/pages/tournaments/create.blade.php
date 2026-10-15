@extends('backend.layouts.app')

@section('title', 'Create Tournament | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-2xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[['label' => 'Tournaments', 'url' => route('admin.tournaments.index')], ['label' => 'Create']]" />

        @if(!empty($creditsInfo) && $creditsInfo['fee'] > 0 && ! $creditsInfo['enough'])
            <div class="mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <div class="flex items-center gap-3">
                    <iconify-icon icon="lucide:alert-triangle" class="text-red-500 text-xl"></iconify-icon>
                    <div>
                        <p class="text-sm font-semibold text-red-800 dark:text-red-300">Not enough {{ app(\App\Services\Credits\CreditService::class)->term() }}</p>
                        <p class="text-xs text-red-600 dark:text-red-400">Creating a tournament uses {{ $creditsInfo['label'] }}. Your balance is {{ $creditsInfo['balance'] }}.
                            <a href="{{ route('admin.credits.index') }}" class="font-semibold underline">Top up</a></p>
                    </div>
                </div>
            </div>
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
                        oninput="if(!document.getElementById('slug').dataset.manual) document.getElementById('slug').value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300">URL Slug</label>
                    <input type="text" name="slug" id="slug" value="{{ old('slug') }}"
                        placeholder="auto-generated-from-name"
                        oninput="this.dataset.manual = this.value ? '1' : ''"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="text-xs text-gray-500 mt-1">URL: {{ url('/t') }}/<span id="slug-preview" class="font-medium">your-tournament-slug</span></p>
                    @error('slug')
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

                {{-- Starting templates: the presets ticked here are copied in --}}
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-2">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Start with these template presets</p>
                    <p class="text-xs text-gray-500">The ticked presets are copied into the tournament, ready to generate and edit. Changing the copies never changes the presets.</p>
                    @include('backend.pages.template-library.partials.picker', [
                        'presets' => app(\App\Services\Poster\TemplateLibrary::class)->pickerFor(auth()->user()),
                        'orgSelect' => '#organization_id',
                        'typeSelect' => '#type',
                    ])
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end space-x-3 pt-4">
                    <a href="{{ route('admin.tournaments.index') }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cancel
                    </a>

                    @if(!empty($creditsInfo))
                        <span class="text-xs text-gray-500 dark:text-gray-400">Balance: {{ $creditsInfo['balance'] }}</span>
                    @endif
                    <button type="submit" id="createTournamentBtn" @disabled(!empty($creditsInfo) && ! $creditsInfo['enough'])
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        Create Tournament
                        <span id="createTournamentFee" class="ml-1 font-normal opacity-90">@if(!empty($creditsInfo['label']))({{ $creditsInfo['label'] }})@endif</span>
                    </button>
                    @if(!empty($creditsInfo))
                        <script>
                            // The price on the button: the tournament fee plus the premium presets ticked.
                            document.addEventListener('presets-changed', function (e) {
                                const base = {{ (int) $creditsInfo['fee'] }}, balance = {{ (int) app(\App\Services\Credits\CreditService::class)->balance(\App\Models\Organization::find(auth()->user()->organization_id)) }};
                                const total = base + (e.detail.premium || 0);
                                document.getElementById('createTournamentFee').textContent = total > 0 ? '(' + total.toLocaleString() + ' ' + @json(app(\App\Services\Credits\CreditService::class)->term()) + ')' : '';
                                document.getElementById('createTournamentBtn').disabled = total > balance;
                            });
                        </script>
                    @endif
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
            // Slug preview
            const slugInput = document.getElementById('slug');
            const slugPreview = document.getElementById('slug-preview');
            slugInput.addEventListener('input', function() {
                slugPreview.textContent = this.value || 'your-tournament-slug';
            });
            // Also update preview when name auto-fills slug
            const nameInput = document.getElementById('name');
            const origNameHandler = nameInput.oninput;
            nameInput.addEventListener('input', function() {
                setTimeout(() => { slugPreview.textContent = slugInput.value || 'your-tournament-slug'; }, 0);
            });

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
