@extends('public.tournament.layouts.app')

@section('title', 'Player Registration - ' . $tournament->name)

@section('content')
    <div class="max-w-2xl mx-auto px-4 py-8">
        <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700">
            {{-- Header --}}
            <div class="p-6 bg-gray-700 text-center">
                <h1 class="text-2xl font-bold">Player Registration</h1>
                <p class="text-gray-400 mt-2">Join {{ $tournament->name }}</p>
            </div>

            {{-- Form --}}
            <form method="POST" action="{{ route('public.tournament.register.player.store', $tournament->slug) }}" class="p-6 space-y-6">
                @csrf

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

                {{-- Mobile --}}
                <div>
                    <label for="mobile_number" class="block text-sm font-medium mb-2">Mobile Number <span class="text-red-500">*</span></label>
                    <input type="tel" name="mobile_number" id="mobile_number" value="{{ old('mobile_number') }}" required
                           class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                           placeholder="+971 50 123 4567">
                    @error('mobile_number')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Jersey Name --}}
                <div>
                    <label for="jersey_name" class="block text-sm font-medium mb-2">Jersey Name</label>
                    <input type="text" name="jersey_name" id="jersey_name" value="{{ old('jersey_name') }}"
                           class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                           placeholder="Name on your jersey (optional)">
                    @error('jersey_name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Player Type --}}
                @if($playerTypes->count() > 0)
                    <div>
                        <label for="player_type_id" class="block text-sm font-medium mb-2">Player Type</label>
                        <select name="player_type_id" id="player_type_id"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                            <option value="">Select player type</option>
                            @foreach($playerTypes as $type)
                                <option value="{{ $type->id }}" {{ old('player_type_id') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('player_type_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- Batting Profile --}}
                @if($battingProfiles->count() > 0)
                    <div>
                        <label for="batting_profile_id" class="block text-sm font-medium mb-2">Batting Style</label>
                        <select name="batting_profile_id" id="batting_profile_id"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                            <option value="">Select batting style</option>
                            @foreach($battingProfiles as $profile)
                                <option value="{{ $profile->id }}" {{ old('batting_profile_id') == $profile->id ? 'selected' : '' }}>
                                    {{ $profile->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('batting_profile_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- Bowling Profile --}}
                @if($bowlingProfiles->count() > 0)
                    <div>
                        <label for="bowling_profile_id" class="block text-sm font-medium mb-2">Bowling Style</label>
                        <select name="bowling_profile_id" id="bowling_profile_id"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                            <option value="">Select bowling style</option>
                            @foreach($bowlingProfiles as $profile)
                                <option value="{{ $profile->id }}" {{ old('bowling_profile_id') == $profile->id ? 'selected' : '' }}>
                                    {{ $profile->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('bowling_profile_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- Wicket Keeper --}}
                <div class="flex items-center">
                    <input type="checkbox" name="is_wicket_keeper" id="is_wicket_keeper" value="1"
                           class="w-5 h-5 bg-gray-700 border-gray-600 rounded text-yellow-500 focus:ring-yellow-500"
                           {{ old('is_wicket_keeper') ? 'checked' : '' }}>
                    <label for="is_wicket_keeper" class="ml-3 text-sm">I am a wicket keeper</label>
                </div>

                {{-- Submit Button --}}
                <div class="pt-4">
                    <button type="submit"
                            class="w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-3 px-6 rounded-lg transition transform hover:scale-[1.02]">
                        Submit Registration
                    </button>
                </div>
            </form>
        </div>

        {{-- Back Link --}}
        <div class="text-center mt-6">
            <a href="{{ route('public.tournament.show', $tournament->slug) }}" class="text-gray-400 hover:text-white">
                <i class="fas fa-arrow-left mr-2"></i> Back to Tournament
            </a>
        </div>
    </div>
@endsection
