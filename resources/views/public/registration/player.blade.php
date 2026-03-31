@extends('public.tournament.layouts.app')

@section('title', 'Player Registration - ' . $tournament->name)

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-8">
        <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700">
            {{-- Header --}}
            <div class="p-6 bg-gradient-to-r from-yellow-500 to-orange-500 text-center">
                <h1 class="text-2xl font-bold text-gray-900">Player Registration</h1>
                <p class="text-gray-800 mt-2">Join {{ $tournament->name }}</p>
            </div>

            {{-- Form --}}
            <form method="POST" action="{{ route('public.tournament.registration.player.store', $tournament->slug) }}"
                  enctype="multipart/form-data" class="p-6 space-y-6" x-data="{
                      noTravel: {{ old('no_travel_plan') ? 'true' : 'false' }},
                      selectedTeam: '{{ old('team_id') }}',
                      previewUrl: '',
                      handleFileChange(event) {
                          const file = event.target.files[0];
                          if (file && file.type.startsWith('image/')) {
                              this.previewUrl = URL.createObjectURL(file);
                          }
                      }
                  }">
                @csrf

                {{-- Basic Information Section --}}
                <div class="border-b border-gray-700 pb-4 mb-4">
                    <h3 class="text-lg font-semibold text-yellow-500 mb-4">Basic Information</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Name --}}
                        <div>
                            <label for="name" class="block text-sm font-medium mb-2">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                   placeholder="Enter your full name">
                            @error('name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Email --}}
                        <div>
                            <label for="email" class="block text-sm font-medium mb-2">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                   placeholder="your@email.com">
                            @error('email')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Mobile Number --}}
                        <div>
                            <label for="mobile_number_full" class="block text-sm font-medium mb-2">Mobile Number <span class="text-red-500">*</span></label>
                            <input type="tel" name="mobile_number_full" id="mobile_number_full" value="{{ old('mobile_number_full') }}" required
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                   placeholder="971501234567 (without +)">
                            <p class="text-gray-400 text-xs mt-1">Enter with country code without + sign</p>
                            @error('mobile_number_full')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- CricHeroes Number --}}
                        <div>
                            <label for="cricheroes_number_full" class="block text-sm font-medium mb-2">CricHeroes Number</label>
                            <input type="tel" name="cricheroes_number_full" id="cricheroes_number_full" value="{{ old('cricheroes_number_full') }}"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                   placeholder="971501234567 (without +)">
                            <p class="text-gray-400 text-xs mt-1">Your CricHeroes registered number</p>
                            @error('cricheroes_number_full')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- CricHeroes Profile URL --}}
                        <div class="md:col-span-2">
                            <label for="cricheroes_profile_url" class="block text-sm font-medium mb-2">CricHeroes Profile URL</label>
                            <input type="url" name="cricheroes_profile_url" id="cricheroes_profile_url" value="{{ old('cricheroes_profile_url') }}"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                   placeholder="https://cricheroes.com/player-profile/12345678/your-name/matches">
                            <p class="text-gray-400 text-xs mt-1">Paste your CricHeroes profile link (optional)</p>
                            @error('cricheroes_profile_url')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Country --}}
                        <div>
                            <label for="country" class="block text-sm font-medium mb-2">Country</label>
                            <select name="country" id="country"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                <option value="">Select your country</option>
                                @foreach (config('countries.list', []) as $code => $name)
                                    <option value="{{ $code }}" {{ old('country', $defaultCountry ?? '') == $code ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('country')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Location --}}
                        @if($locations->count() > 0)
                        <div>
                            <label for="location_id" class="block text-sm font-medium mb-2">Location</label>
                            <select name="location_id" id="location_id"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                <option value="">Select your location</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>
                                        {{ $location->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('location_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        @endif

                        {{-- Team Selection --}}
                        @if($teams->count() > 0)
                        <div>
                            <label for="team_id" class="block text-sm font-medium mb-2">Team</label>
                            <select name="team_id" id="team_id" x-model="selectedTeam"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                <option value="">Select your team</option>
                                @foreach($teams as $team)
                                    <option value="{{ $team->id }}" {{ old('team_id') == $team->id ? 'selected' : '' }}>
                                        {{ $team->name }}
                                    </option>
                                @endforeach
                                <option value="other">Other (specify below)</option>
                            </select>
                            @error('team_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Team Name Reference (for "Other" team) --}}
                        <div x-show="selectedTeam === 'other'" x-cloak>
                            <label for="team_name_ref" class="block text-sm font-medium mb-2">Team Name</label>
                            <input type="text" name="team_name_ref" id="team_name_ref" value="{{ old('team_name_ref') }}"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                   placeholder="Enter your team name">
                            @error('team_name_ref')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Jersey Information Section --}}
                <div class="border-b border-gray-700 pb-4 mb-4">
                    <h3 class="text-lg font-semibold text-yellow-500 mb-4">Jersey Information</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- Jersey Name --}}
                        <div>
                            <label for="jersey_name" class="block text-sm font-medium mb-2">Jersey Name</label>
                            <input type="text" name="jersey_name" id="jersey_name" value="{{ old('jersey_name') }}"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                   placeholder="Name on jersey">
                            @error('jersey_name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Jersey Number --}}
                        <div>
                            <label for="jersey_number" class="block text-sm font-medium mb-2">Jersey Number</label>
                            <input type="number" name="jersey_number" id="jersey_number" value="{{ old('jersey_number') }}" min="0" max="999"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                   placeholder="e.g., 7">
                            @error('jersey_number')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Kit Size --}}
                        @if($kitSizes->count() > 0)
                        <div>
                            <label for="kit_size_id" class="block text-sm font-medium mb-2">Jersey Size</label>
                            <select name="kit_size_id" id="kit_size_id"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                <option value="">Select size</option>
                                @foreach($kitSizes as $size)
                                    <option value="{{ $size->id }}" {{ old('kit_size_id') == $size->id ? 'selected' : '' }}>
                                        {{ $size->size ?? $size->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('kit_size_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Player Profile Section --}}
                <div class="border-b border-gray-700 pb-4 mb-4">
                    <h3 class="text-lg font-semibold text-yellow-500 mb-4">Player Profile</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- Player Type --}}
                        @if($playerTypes->count() > 0)
                        <div>
                            <label for="player_type_id" class="block text-sm font-medium mb-2">Player Type</label>
                            <select name="player_type_id" id="player_type_id"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                <option value="">Select type</option>
                                @foreach($playerTypes as $type)
                                    <option value="{{ $type->id }}" {{ old('player_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->name ?? $type->type }}
                                    </option>
                                @endforeach
                            </select>
                            @error('player_type_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        @endif

                        {{-- Batting Style --}}
                        @if($battingProfiles->count() > 0)
                        <div>
                            <label for="batting_profile_id" class="block text-sm font-medium mb-2">Batting Style</label>
                            <select name="batting_profile_id" id="batting_profile_id"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                <option value="">Select batting style</option>
                                @foreach($battingProfiles as $profile)
                                    <option value="{{ $profile->id }}" {{ old('batting_profile_id') == $profile->id ? 'selected' : '' }}>
                                        {{ $profile->name ?? $profile->style }}
                                    </option>
                                @endforeach
                            </select>
                            @error('batting_profile_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        @endif

                        {{-- Bowling Style --}}
                        @if($bowlingProfiles->count() > 0)
                        <div>
                            <label for="bowling_profile_id" class="block text-sm font-medium mb-2">Bowling Style</label>
                            <select name="bowling_profile_id" id="bowling_profile_id"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                <option value="">Select bowling style</option>
                                @foreach($bowlingProfiles as $profile)
                                    <option value="{{ $profile->id }}" {{ old('bowling_profile_id') == $profile->id ? 'selected' : '' }}>
                                        {{ $profile->name ?? $profile->style }}
                                    </option>
                                @endforeach
                            </select>
                            @error('bowling_profile_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        @endif
                    </div>

                    {{-- Wicket Keeper Checkbox --}}
                    <div class="mt-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="is_wicket_keeper" id="is_wicket_keeper" value="1"
                                   class="w-5 h-5 bg-gray-700 border-gray-600 rounded text-yellow-500 focus:ring-yellow-500"
                                   {{ old('is_wicket_keeper') ? 'checked' : '' }}>
                            <span class="ml-3 text-sm">I am a wicket keeper</span>
                        </label>
                    </div>
                </div>

                {{-- Leather Ball Experience Section --}}
                <div class="border-b border-gray-700 pb-4 mb-4">
                    <h3 class="text-lg font-semibold text-yellow-500 mb-4">Leather Ball Experience</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="total_matches" class="block text-sm font-medium mb-2">Total Matches</label>
                            <input type="number" name="total_matches" id="total_matches" value="{{ old('total_matches', 0) }}" min="0"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                   placeholder="0">
                            @error('total_matches')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="total_runs" class="block text-sm font-medium mb-2">Total Runs</label>
                            <input type="number" name="total_runs" id="total_runs" value="{{ old('total_runs', 0) }}" min="0"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                   placeholder="0">
                            @error('total_runs')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="total_wickets" class="block text-sm font-medium mb-2">Total Wickets</label>
                            <input type="number" name="total_wickets" id="total_wickets" value="{{ old('total_wickets', 0) }}" min="0"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                   placeholder="0">
                            @error('total_wickets')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Travel & Transportation Section --}}
                <div class="border-b border-gray-700 pb-4 mb-4">
                    <h3 class="text-lg font-semibold text-yellow-500 mb-4">Travel & Transportation</h3>

                    <div class="space-y-4">
                        {{-- Transportation Required --}}
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="transportation_required" id="transportation_required" value="1"
                                   class="w-5 h-5 bg-gray-700 border-gray-600 rounded text-yellow-500 focus:ring-yellow-500"
                                   {{ old('transportation_required') ? 'checked' : '' }}>
                            <span class="ml-3 text-sm">I need transportation to the venue</span>
                        </label>

                        {{-- No Travel Plan --}}
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="no_travel_plan" id="no_travel_plan" value="1" x-model="noTravel"
                                   class="w-5 h-5 bg-gray-700 border-gray-600 rounded text-yellow-500 focus:ring-yellow-500"
                                   {{ old('no_travel_plan') ? 'checked' : '' }}>
                            <span class="ml-3 text-sm">I have no travel plans (available throughout)</span>
                        </label>

                        {{-- Travel Dates --}}
                        <div x-show="!noTravel" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label for="travel_date_from" class="block text-sm font-medium mb-2">Travel From Date</label>
                                <input type="date" name="travel_date_from" id="travel_date_from" value="{{ old('travel_date_from') }}"
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                @error('travel_date_from')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="travel_date_to" class="block text-sm font-medium mb-2">Travel To Date</label>
                                <input type="date" name="travel_date_to" id="travel_date_to" value="{{ old('travel_date_to') }}"
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                @error('travel_date_to')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Player Image Section --}}
                <div class="border-b border-gray-700 pb-4 mb-4">
                    <h3 class="text-lg font-semibold text-yellow-500 mb-4">Player Photo</h3>

                    <div class="border-2 border-dashed border-gray-600 rounded-lg p-6 text-center hover:border-yellow-500 transition cursor-pointer"
                         @click="$refs.imageInput.click()">
                        <input type="file" name="image" id="image" accept="image/png,image/jpeg" class="hidden"
                               x-ref="imageInput" @change="handleFileChange">

                        <template x-if="previewUrl">
                            <img :src="previewUrl" class="mx-auto mb-4 h-48 object-contain rounded-lg border border-gray-600" />
                        </template>

                        <div x-show="!previewUrl" class="text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-sm">Click to upload your photo</p>
                            <p class="text-xs mt-1">PNG or JPG (max 6MB)</p>
                        </div>
                    </div>
                    @error('image')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit Button --}}
                <div class="pt-4">
                    <button type="submit"
                            class="w-full bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-gray-900 font-bold py-4 px-6 rounded-lg transition transform hover:scale-[1.02]">
                        Submit Registration
                    </button>
                </div>
            </form>
        </div>

        {{-- Back Link --}}
        <div class="text-center mt-6">
            <a href="{{ route('public.tournament.show', $tournament->slug) }}" class="text-gray-400 hover:text-white transition">
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Tournament
            </a>
        </div>
    </div>
@endsection
