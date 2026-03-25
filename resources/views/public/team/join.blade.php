<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join {{ $team->name }} - {{ $team->tournament->name ?? 'Team Registration' }}</title>
    <meta name="description" content="Join {{ $team->name }} for {{ $team->tournament->name ?? 'the tournament' }}">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    @php
        $primaryColor = $team->tournament?->settings?->primary_color ?? '#f59e0b';
        $secondaryColor = $team->tournament?->settings?->secondary_color ?? '#1f2937';
    @endphp
    <style>
        body { font-family: 'Roboto', sans-serif; }
        h1, h2, h3 { font-family: 'Oswald', sans-serif; }
        [x-cloak] { display: none !important; }
        .brand-gradient { background: linear-gradient(135deg, {{ $primaryColor }}, {{ $secondaryColor }}); }
        .brand-ring:focus { --tw-ring-color: {{ $primaryColor }}; border-color: {{ $primaryColor }}; }
        .brand-text { color: {{ $primaryColor }}; }
        .brand-bg { background-color: {{ $primaryColor }}; }
        .brand-btn {
            background: linear-gradient(135deg, {{ $primaryColor }}, {{ $secondaryColor }});
            transition: all 0.3s ease;
        }
        .brand-btn:hover { opacity: 0.9; transform: scale(1.01); }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-3xl mx-auto px-4 py-8">
        <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700">
            {{-- Header --}}
            <div class="brand-gradient p-6 text-center">
                @if($team->team_logo)
                    <img src="{{ asset('storage/' . $team->team_logo) }}" alt="{{ $team->name }}"
                         class="w-20 h-20 rounded-full mx-auto mb-3 object-contain bg-white p-1 border-2 border-white">
                @elseif($team->tournament?->settings?->logo)
                    <img src="{{ Storage::url($team->tournament->settings->logo) }}" alt="{{ $team->tournament->name }}"
                         class="w-20 h-20 rounded-full mx-auto mb-3 object-contain bg-white p-1 border-2 border-white">
                @endif
                <h1 class="text-2xl font-bold text-white">Join {{ $team->name }}</h1>
                @if($team->tournament)
                    <p class="text-gray-200 mt-1 opacity-90">{{ $team->tournament->name }}</p>
                @endif
            </div>

            {{-- Validation Errors --}}
            @if($errors->any())
                <div class="mx-6 mt-4 bg-red-900 border border-red-700 rounded-lg p-4">
                    <p class="text-red-300 text-sm font-medium mb-2">Please fix the following errors:</p>
                    <ul class="text-red-400 text-sm list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('public.team.join.store', $team->invite_code) }}"
                  enctype="multipart/form-data" class="p-6 space-y-6" x-data="{
                      noTravel: {{ old('no_travel_plan') ? 'true' : 'false' }},
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
                    <h3 class="text-lg font-semibold brand-text mb-4">Basic Information</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Name --}}
                        <div>
                            <label for="name" class="block text-sm font-medium mb-2">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none"
                                   placeholder="Enter your full name">
                            @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Email --}}
                        <div>
                            <label for="email" class="block text-sm font-medium mb-2">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none"
                                   placeholder="your@email.com">
                            @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Mobile Number --}}
                        <div>
                            <label for="mobile_number_full" class="block text-sm font-medium mb-2">Mobile Number <span class="text-red-500">*</span></label>
                            <input type="tel" name="mobile_number_full" id="mobile_number_full" value="{{ old('mobile_number_full') }}" required
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none"
                                   placeholder="971501234567 (without +)">
                            <p class="text-gray-400 text-xs mt-1">Enter with country code without + sign</p>
                            @error('mobile_number_full') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- CricHeroes Number --}}
                        <div>
                            <label for="cricheroes_number_full" class="block text-sm font-medium mb-2">CricHeroes Number</label>
                            <input type="tel" name="cricheroes_number_full" id="cricheroes_number_full" value="{{ old('cricheroes_number_full') }}"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none"
                                   placeholder="971501234567 (without +)">
                            <p class="text-gray-400 text-xs mt-1">Your CricHeroes registered number</p>
                            @error('cricheroes_number_full') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- CricHeroes Profile URL --}}
                        <div class="md:col-span-2">
                            <label for="cricheroes_profile_url" class="block text-sm font-medium mb-2">CricHeroes Profile URL</label>
                            <input type="url" name="cricheroes_profile_url" id="cricheroes_profile_url" value="{{ old('cricheroes_profile_url') }}"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none"
                                   placeholder="https://cricheroes.com/player-profile/12345678/your-name/matches">
                            @error('cricheroes_profile_url') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Location --}}
                        @if($locations->count() > 0)
                        <div>
                            <label for="location_id" class="block text-sm font-medium mb-2">Location</label>
                            <select name="location_id" id="location_id"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none">
                                <option value="">Select your location</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>
                                        {{ $location->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('location_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Jersey Information Section --}}
                <div class="border-b border-gray-700 pb-4 mb-4">
                    <h3 class="text-lg font-semibold brand-text mb-4">Jersey Information</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="jersey_name" class="block text-sm font-medium mb-2">Jersey Name</label>
                            <input type="text" name="jersey_name" id="jersey_name" value="{{ old('jersey_name') }}"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none"
                                   placeholder="Name on jersey">
                            @error('jersey_name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="jersey_number" class="block text-sm font-medium mb-2">Jersey Number</label>
                            <input type="number" name="jersey_number" id="jersey_number" value="{{ old('jersey_number') }}" min="0" max="999"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none"
                                   placeholder="e.g., 7">
                            @error('jersey_number') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        @if($kitSizes->count() > 0)
                        <div>
                            <label for="kit_size_id" class="block text-sm font-medium mb-2">Jersey Size</label>
                            <select name="kit_size_id" id="kit_size_id"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none">
                                <option value="">Select size</option>
                                @foreach($kitSizes as $size)
                                    <option value="{{ $size->id }}" {{ old('kit_size_id') == $size->id ? 'selected' : '' }}>
                                        {{ $size->size ?? $size->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('kit_size_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Player Profile Section --}}
                <div class="border-b border-gray-700 pb-4 mb-4">
                    <h3 class="text-lg font-semibold brand-text mb-4">Player Profile</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @if($playerTypes->count() > 0)
                        <div>
                            <label for="player_type_id" class="block text-sm font-medium mb-2">Player Type</label>
                            <select name="player_type_id" id="player_type_id"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none">
                                <option value="">Select type</option>
                                @foreach($playerTypes as $type)
                                    <option value="{{ $type->id }}" {{ old('player_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->name ?? $type->type }}
                                    </option>
                                @endforeach
                            </select>
                            @error('player_type_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        @endif

                        @if($battingProfiles->count() > 0)
                        <div>
                            <label for="batting_profile_id" class="block text-sm font-medium mb-2">Batting Style</label>
                            <select name="batting_profile_id" id="batting_profile_id"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none">
                                <option value="">Select batting style</option>
                                @foreach($battingProfiles as $profile)
                                    <option value="{{ $profile->id }}" {{ old('batting_profile_id') == $profile->id ? 'selected' : '' }}>
                                        {{ $profile->name ?? $profile->style }}
                                    </option>
                                @endforeach
                            </select>
                            @error('batting_profile_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        @endif

                        @if($bowlingProfiles->count() > 0)
                        <div>
                            <label for="bowling_profile_id" class="block text-sm font-medium mb-2">Bowling Style</label>
                            <select name="bowling_profile_id" id="bowling_profile_id"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none">
                                <option value="">Select bowling style</option>
                                @foreach($bowlingProfiles as $profile)
                                    <option value="{{ $profile->id }}" {{ old('bowling_profile_id') == $profile->id ? 'selected' : '' }}>
                                        {{ $profile->name ?? $profile->style }}
                                    </option>
                                @endforeach
                            </select>
                            @error('bowling_profile_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        @endif
                    </div>

                    <div class="mt-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="is_wicket_keeper" value="1"
                                   class="w-5 h-5 bg-gray-700 border-gray-600 rounded focus:ring-2 brand-ring"
                                   {{ old('is_wicket_keeper') ? 'checked' : '' }}>
                            <span class="ml-3 text-sm">I am a wicket keeper</span>
                        </label>
                    </div>
                </div>

                {{-- Leather Ball Experience Section --}}
                <div class="border-b border-gray-700 pb-4 mb-4">
                    <h3 class="text-lg font-semibold brand-text mb-4">Leather Ball Experience</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="total_matches" class="block text-sm font-medium mb-2">Total Matches</label>
                            <input type="number" name="total_matches" id="total_matches" value="{{ old('total_matches', 0) }}" min="0"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none"
                                   placeholder="0">
                            @error('total_matches') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="total_runs" class="block text-sm font-medium mb-2">Total Runs</label>
                            <input type="number" name="total_runs" id="total_runs" value="{{ old('total_runs', 0) }}" min="0"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none"
                                   placeholder="0">
                            @error('total_runs') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="total_wickets" class="block text-sm font-medium mb-2">Total Wickets</label>
                            <input type="number" name="total_wickets" id="total_wickets" value="{{ old('total_wickets', 0) }}" min="0"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none"
                                   placeholder="0">
                            @error('total_wickets') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Travel & Transportation Section --}}
                <div class="border-b border-gray-700 pb-4 mb-4">
                    <h3 class="text-lg font-semibold brand-text mb-4">Travel & Transportation</h3>

                    <div class="space-y-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="transportation_required" value="1"
                                   class="w-5 h-5 bg-gray-700 border-gray-600 rounded focus:ring-2 brand-ring"
                                   {{ old('transportation_required') ? 'checked' : '' }}>
                            <span class="ml-3 text-sm">I need transportation to the venue</span>
                        </label>

                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="no_travel_plan" value="1" x-model="noTravel"
                                   class="w-5 h-5 bg-gray-700 border-gray-600 rounded focus:ring-2 brand-ring"
                                   {{ old('no_travel_plan') ? 'checked' : '' }}>
                            <span class="ml-3 text-sm">I have no travel plans (available throughout)</span>
                        </label>

                        <div x-show="!noTravel" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label for="travel_date_from" class="block text-sm font-medium mb-2">Travel From Date</label>
                                <input type="date" name="travel_date_from" id="travel_date_from" value="{{ old('travel_date_from') }}"
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none">
                                @error('travel_date_from') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="travel_date_to" class="block text-sm font-medium mb-2">Travel To Date</label>
                                <input type="date" name="travel_date_to" id="travel_date_to" value="{{ old('travel_date_to') }}"
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 brand-ring focus:outline-none">
                                @error('travel_date_to') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Player Image Section --}}
                <div class="border-b border-gray-700 pb-4 mb-4">
                    <h3 class="text-lg font-semibold brand-text mb-4">Player Photo</h3>

                    <div class="border-2 border-dashed border-gray-600 rounded-lg p-6 text-center hover:border-gray-500 transition cursor-pointer"
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
                    @error('image') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Submit Button --}}
                <div class="pt-4">
                    <button type="submit"
                            class="w-full brand-btn text-white font-bold py-4 px-6 rounded-lg">
                        Submit Registration
                    </button>
                </div>
            </form>
        </div>

        {{-- Footer --}}
        <div class="text-center mt-6">
            <p class="text-gray-600 text-sm">
                Powered by <span class="brand-text">{{ config('app.name') }}</span>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
