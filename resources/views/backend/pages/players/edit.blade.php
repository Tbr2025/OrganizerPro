@extends('backend.layouts.app')

@section('title')
    Edit Player | {{ config('app.name') }}
@endsection

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

        <div class="space-y-6">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-5 space-y-6 sm:p-6">
                    <form action="{{ route('admin.players.update', $player->id) }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        @php
                            $canVerify = auth()->user()->hasAnyRole(['Superadmin', 'Admin']);
                            $skip = ['name', 'terms_and_conditions', 'image'];
                        @endphp

                        {{-- Dynamic sections from PlayerFormConfig --}}
                        @foreach($layout as $section)
                            @php
                                $keys = array_values(array_filter($section['fields'], fn ($k) => ! in_array($k, $skip, true)));
                                $isPhoto = ($section['key'] === 'Player Photo');
                            @endphp
                            @if(count($keys) || $isPhoto)
                            <div class="mb-6">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">{{ $section['title'] }}</h3>
                                @if(count($keys))
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    @foreach($keys as $key)
                                        @include('backend.pages.players.partials.field', ['key' => $key])
                                    @endforeach
                                </div>
                                @endif

                                {{-- Kit Size (admin-only field, not in PlayerFormConfig) --}}
                                @if($section['key'] === 'Jersey Information')
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ ($player->verified_kit_size_id ?? false) ? 'border-green-400 dark:border-green-600' : 'border-transparent' }}">
                                        <div class="flex items-start justify-between gap-2 mb-1">
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
                                <div class="mt-3 max-w-xs">
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
                            @endif
                        @endforeach

                        {{-- ═══════════════════════════════════════════════════════ --}}
                        {{-- Admin-only: Player Mode & Team                        --}}
                        {{-- ═══════════════════════════════════════════════════════ --}}
                        @if($player->status === 'approved')
                        <div class="mb-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">Player Mode & Team</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                                    <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Player Mode</h4>
                                    <select name="player_mode" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                        <option value="normal" {{ old('player_mode', $player->player_mode) !== 'retained' ? 'selected' : '' }}>Normal</option>
                                        <option value="retained" {{ old('player_mode', $player->player_mode) === 'retained' ? 'selected' : '' }}>Retained</option>
                                    </select>
                                    @error('player_mode')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                                    <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Retained Value</h4>
                                    <input type="number" name="retained_value" value="{{ old('retained_value', $player->retained_value) }}"
                                        min="0" step="any" placeholder="e.g. 500000"
                                        class="w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                    @error('retained_value')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                        @endif

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
                            @if ($hasWelcomeTemplate)
                                @if ($verifiedProfile)
                                    <input type="hidden" name="allverified" value="1">
                                    <button type="submit"
                                        onclick="document.getElementById('allverified').value = '{{ $verifiedProfile }}';"
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Welcome Player - Generate Image
                                    </button>
                                @endif
                            @else
                                <button type="button" disabled
                                    class="inline-flex items-center px-4 py-2 bg-gray-400 text-white text-sm font-medium rounded-md shadow-sm cursor-not-allowed">
                                    Welcome Player - Generate Image
                                </button>
                                <p class="text-sm text-red-600 mt-2">
                                    @if ($welcomeRegistration && $welcomeRegistration->tournament)
                                        No <strong>welcome_card</strong> template for {{ $welcomeRegistration->tournament->name }}.
                                        <a href="{{ route('admin.tournaments.templates.create', $welcomeRegistration->tournament) }}?type=welcome_card"
                                            class="underline text-blue-600 hover:text-blue-800">Create one now.</a>
                                    @else
                                        This player isn't linked to a tournament yet, so a welcome card can't be generated.
                                    @endif
                                </p>
                            @endif
                            @if (!$player->isApproved())
                                <input type="hidden" name="isapproved" value="1">
                                <button type="submit"
                                    onclick="document.getElementById('isApproved').value = '{{ $player->isApproved() }}';"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Approve
                                </button>
                            @else
                                <span class="text-gray-500">Player already approved</span>
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
                </div>
            </div>
        </div>

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
