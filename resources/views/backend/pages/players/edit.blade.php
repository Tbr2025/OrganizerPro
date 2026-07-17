@extends('backend.layouts.app')

@section('title')
    Edit Player | {{ config('app.name') }}
@endsection

@push('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
@endpush

@section('admin-content')
    <div class="p-4 mx-auto md:p-6">
        <div class="flex justify-between items-center mb-4">
            <x-breadcrumbs :breadcrumbs="$breadcrumbs" />
            @can('player.delete')
                <form action="{{ route('admin.players.destroy', $player->id) }}" method="POST"
                    onsubmit="return confirm('Are you sure you want to delete this player? This action cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Delete Player
                    </button>
                </form>
            @endcan
        </div>

        {{-- Tournament Context Selector --}}
        @if($tournamentRegistrations->count() > 0)
        <div class="mb-4">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tournament Context</label>
            <select onchange="window.location.href='{{ route('admin.players.edit', $player->id) }}?tournament=' + this.value"
                    class="mt-1 block w-full sm:w-auto text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach($tournamentRegistrations as $reg)
                    <option value="{{ $reg->tournament_id }}"
                        @selected($reg->tournament_id == ($selectedTournament->id ?? null))>
                        {{ $reg->tournament->name ?? 'N/A' }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif

        @php
            $canVerify = auth()->user()->hasAnyRole(['Superadmin', 'Admin']);
            $skip = ['name', 'terms_and_conditions', 'image'];

            $sectionMeta = [
                'Basic Information'       => ['icon' => 'fa-id-card',        'sub' => 'Who you are and how to reach you'],
                'Visa & Employment'       => ['icon' => 'fa-passport',       'sub' => 'Your residency and work details'],
                'Availability'            => ['icon' => 'fa-calendar-check', 'sub' => 'When and where you can play'],
                'Jersey Information'      => ['icon' => 'fa-tshirt',         'sub' => 'What goes on your kit'],
                'Player Profile'          => ['icon' => 'fa-baseball-ball',  'sub' => 'Your playing style'],
                'Leather Ball Experience' => ['icon' => 'fa-chart-line',     'sub' => 'Your career numbers'],
                'Travel & Transportation' => ['icon' => 'fa-bus',            'sub' => 'Help us plan logistics'],
                'Player Photo'            => ['icon' => 'fa-camera',         'sub' => 'A clear, front-facing headshot'],
            ];
        @endphp

        <form action="{{ route('admin.players.update', $player->id) }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="tournament_context" value="{{ $selectedTournament->id ?? '' }}">

            <div class="space-y-6">
                {{-- Dynamic sections from PlayerFormConfig --}}
                @foreach($layout as $section)
                    @php
                        $keys = array_values(array_filter($section['fields'], fn ($k) => ! in_array($k, $skip, true)));
                        $isPhoto = ($section['key'] === 'Player Photo');
                        $meta = $sectionMeta[$section['title']] ?? ['icon' => 'fa-circle', 'sub' => ''];
                    @endphp
                    @if(count($keys) || $isPhoto)
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/50 shadow-sm overflow-hidden">
                        <div class="flex items-center gap-3 px-5 pt-5 pb-4">
                            <div class="w-10 h-10 rounded-lg flex-shrink-0 flex items-center justify-center bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800/50">
                                <i class="fas {{ $meta['icon'] }} text-sm"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white leading-tight">{{ $section['title'] }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $meta['sub'] }}</p>
                            </div>
                        </div>
                        <div class="px-5 pb-5">
                            @if(count($keys))
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                @foreach($keys as $key)
                                    @include('backend.pages.players.partials.field', ['key' => $key])
                                @endforeach
                            </div>
                            @endif

                            {{-- Kit Size (admin-only field, not in PlayerFormConfig) --}}
                            @if($section['key'] === 'Jersey Information')
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border {{ ($player->verified_kit_size_id ?? false) ? 'border-green-400 dark:border-green-600' : 'border-gray-200 dark:border-gray-700' }}">
                                    <div class="flex items-start justify-between gap-2 mb-1.5">
                                        <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jersey Size</h4>
                                        <label class="relative inline-flex items-center flex-shrink-0 {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                            <input type="checkbox" name="verified_kit_size_id" value="1"
                                                class="sr-only peer"
                                                {{ old('verified_kit_size_id', $player->verified_kit_size_id ?? false) ? 'checked' : '' }}
                                                @unless($canVerify) disabled @endunless>
                                            <div class="w-9 h-5 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                            <div class="absolute left-0.5 top-0.5 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-4"></div>
                                            <span class="ml-2 text-[10px] font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Verified</span>
                                        </label>
                                    </div>
                                    <select name="kit_size_id" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                        <option value="">-- Select --</option>
                                        @foreach($kitSizes as $ks)
                                            <option value="{{ $ks->id }}" {{ old('kit_size_id', $player->kit_size_id) == $ks->id ? 'selected' : '' }}>{{ $ks->size }}</option>
                                        @endforeach
                                    </select>
                                    @error('kit_size_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            @endif

                            {{-- Player Photo section --}}
                            @if($isPhoto)
                            <div class="max-w-xs">
                                <x-player-image-upload name="image_path" :existing-image="$player->image_path" />
                                @if($player->image_path)
                                    <label class="inline-flex items-center mt-2 space-x-2 text-sm text-gray-600 dark:text-gray-300">
                                        <input type="checkbox" name="clear_image" value="1"> <span>Remove existing image</span>
                                    </label>
                                @endif
                                <div class="mt-3">
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_image_path" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_image_path', $player->verified_image_path ?? false) ? 'checked' : '' }}
                                            @unless($canVerify) disabled @endunless>
                                        <div class="w-9 h-5 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-4"></div>
                                        <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                @endforeach

                {{-- ═══════════════════════════════════════════════════════ --}}
                {{-- Admin-only: Player Mode & Team (auction only)         --}}
                {{-- ═══════════════════════════════════════════════════════ --}}
                @if($player->status === 'approved' && ($selectedTournament?->isAuction() ?? false))
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/50 shadow-sm overflow-hidden">
                    <div class="flex items-center gap-3 px-5 pt-5 pb-4">
                        <div class="w-10 h-10 rounded-lg flex-shrink-0 flex items-center justify-center bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400 border border-purple-100 dark:border-purple-800/50">
                            <i class="fas fa-gavel text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white leading-tight">Player Mode & Team</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Auction-specific player settings</p>
                        </div>
                    </div>
                    <div class="px-5 pb-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Player Mode</h4>
                                <select name="player_mode" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                    <option value="normal" {{ old('player_mode', $player->player_mode) !== 'retained' ? 'selected' : '' }}>Normal</option>
                                    <option value="retained" {{ old('player_mode', $player->player_mode) === 'retained' ? 'selected' : '' }}>Retained</option>
                                </select>
                                @error('player_mode')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Retained Value</h4>
                                <input type="number" name="retained_value" value="{{ old('retained_value', $player->retained_value) }}"
                                    min="0" step="any" placeholder="e.g. 500000"
                                    class="w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                @error('retained_value')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <input type="hidden" name="intimate" id="intimate" value="0">

            {{-- Submit Buttons --}}
            <div class="mt-6">
                <x-buttons.submit-buttons cancelUrl="{{ route('admin.players.index') }}" />
            </div>
            <div class="mt-5 mb-5">
                <button type="submit" onclick="document.getElementById('intimate').value = 1;"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Intimate Player
                </button>
                @if ($hasWelcomeTemplate && $verifiedProfile)
                    <input type="hidden" name="allverified" value="1">
                    <button type="submit"
                        onclick="document.getElementById('allverified').value = '{{ $verifiedProfile }}';"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Welcome Player - Generate Image
                    </button>
                @endif
                @if (!$player->isApproved())
                    <input type="hidden" name="isapproved" value="1">
                    <button type="submit"
                        onclick="document.getElementById('isApproved').value = '{{ $player->isApproved() }}';"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Approve
                    </button>
                @endif
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-blue-800 text-sm space-y-2 col-span-2">
                <div class="flex items-start">
                    <span class="material-icons text-blue-400 mr-2">info</span>
                    <p>
                        <strong>Intimate Player:</strong> Sends an email to the player listing all missing
                        or unverified details.
                    </p>
                </div>
                <div class="flex items-start">
                    <span class="material-icons text-blue-400 mr-2">info</span>
                    <p>
                        <strong>Welcome Player - Generate Image:</strong> Creates a welcome image using the
                        selected template and sends it via email. Need to verify all the details to send
                        welcome message.
                    </p>
                </div>
                <div class="flex items-start">
                    <span class="material-icons text-blue-400 mr-2">info</span>
                    <p>
                        <strong>Active Player</strong> So that player can edit their information from their
                        profile page.
                    </p>
                </div>
            </div>

        </form>

        {{-- Tournament Registrations --}}
        @if(isset($tournamentRegistrations) && $tournamentRegistrations->count() > 0)
        <div class="col-span-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Tournament Registrations</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Tournament</th>
                                    <th>Status</th>
                                    <th>Registered At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tournamentRegistrations as $reg)
                                    <tr>
                                        <td>{{ $reg->tournament->name ?? 'N/A' }}</td>
                                        <td>
                                            @php
                                                $badgeClass = match($reg->status) {
                                                    'approved' => 'badge bg-success',
                                                    'rejected' => 'badge bg-danger',
                                                    default => 'badge bg-warning text-dark',
                                                };
                                            @endphp
                                            <span class="{{ $badgeClass }}">{{ ucfirst($reg->status) }}</span>
                                        </td>
                                        <td>{{ $reg->created_at?->format('d M Y, h:i A') ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    function updateMobileFullNumber() {
        const code = document.getElementById('mobile_country_code_display').value.replace('+', '');
        const number = document.getElementById('mobile_national_display').value.replace(/\D/g, '');
        document.getElementById('mobile_number_full').value = code + number;
    }
    function updateCricheroesFullNumber() {
        const code = document.getElementById('cricheroes_country_code_display').value.replace('+', '');
        const number = document.getElementById('cricheroes_national_display').value.replace(/\D/g, '');
        document.getElementById('cricheroes_number_full').value = code + number;
    }
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateMobileFullNumber();
        updateCricheroesFullNumber();
    });
</script>
@endpush
