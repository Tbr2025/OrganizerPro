@extends('public.tournament.layouts.app')

@section('title', 'Team Registration - ' . $tournament->name)

@section('content')
    <div class="max-w-2xl mx-auto px-4 py-8">
        <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700">
            {{-- Header --}}
            <div class="p-6 bg-gray-700 text-center">
                <h1 class="text-2xl font-bold">Team Registration</h1>
                <p class="text-gray-400 mt-2">Register your team for {{ $tournament->name }}</p>
            </div>

            {{-- Form --}}
            <form method="POST" action="{{ route('public.tournament.register.team.store', $tournament->slug) }}"
                  enctype="multipart/form-data" class="p-6 space-y-6">
                @csrf

                {{-- Team Info Section --}}
                <div class="border-b border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold mb-4 text-yellow-400">Team Information</h2>

                    {{-- Team Name --}}
                    <div class="mb-4">
                        <label for="team_name" class="block text-sm font-medium mb-2">Team Name <span class="text-red-500">*</span></label>
                        <input type="text" name="team_name" id="team_name" value="{{ old('team_name') }}" required
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="Enter team name">
                        @error('team_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Team Short Name --}}
                    <div class="mb-4">
                        <label for="team_short_name" class="block text-sm font-medium mb-2">Short Name (Abbreviation)</label>
                        <input type="text" name="team_short_name" id="team_short_name" value="{{ old('team_short_name') }}" maxlength="10"
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="e.g., SRH, MI, CSK">
                        @error('team_short_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Team Logo --}}
                    <div class="mb-4">
                        <label for="team_logo" class="block text-sm font-medium mb-2">Team Logo</label>
                        <input type="file" name="team_logo" id="team_logo" accept="image/png,image/jpeg,image/jpg"
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-yellow-500 file:text-gray-900 file:font-semibold hover:file:bg-yellow-600">
                        <p class="text-gray-500 text-xs mt-1">PNG or JPG, max 2MB</p>
                        @error('team_logo')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Team Description --}}
                    <div>
                        <label for="team_description" class="block text-sm font-medium mb-2">Team Description</label>
                        <textarea name="team_description" id="team_description" rows="3"
                                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                  placeholder="Brief description about your team (optional)">{{ old('team_description') }}</textarea>
                        @error('team_description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Captain Info Section --}}
                <div class="border-b border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold mb-4 text-yellow-400">Captain Details</h2>

                    {{-- Captain Name --}}
                    <div class="mb-4">
                        <label for="captain_name" class="block text-sm font-medium mb-2">Captain Name <span class="text-red-500">*</span></label>
                        <input type="text" name="captain_name" id="captain_name" value="{{ old('captain_name') }}" required
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="Full name of team captain">
                        @error('captain_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Captain Email --}}
                    <div class="mb-4">
                        <label for="captain_email" class="block text-sm font-medium mb-2">Captain Email <span class="text-red-500">*</span></label>
                        <input type="email" name="captain_email" id="captain_email" value="{{ old('captain_email') }}" required
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="captain@email.com">
                        @error('captain_email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Captain Phone --}}
                    <div>
                        <label for="captain_phone" class="block text-sm font-medium mb-2">Captain Phone <span class="text-red-500">*</span></label>
                        <input type="tel" name="captain_phone" id="captain_phone" value="{{ old('captain_phone') }}" required
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="+971 50 123 4567">
                        @error('captain_phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Vice Captain Info Section --}}
                <div>
                    <h2 class="text-lg font-semibold mb-4 text-yellow-400">Vice Captain Details (Optional)</h2>

                    {{-- Vice Captain Name --}}
                    <div class="mb-4">
                        <label for="vice_captain_name" class="block text-sm font-medium mb-2">Vice Captain Name</label>
                        <input type="text" name="vice_captain_name" id="vice_captain_name" value="{{ old('vice_captain_name') }}"
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="Full name of vice captain">
                        @error('vice_captain_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Vice Captain Phone --}}
                    <div>
                        <label for="vice_captain_phone" class="block text-sm font-medium mb-2">Vice Captain Phone</label>
                        <input type="tel" name="vice_captain_phone" id="vice_captain_phone" value="{{ old('vice_captain_phone') }}"
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="+971 50 123 4567">
                        @error('vice_captain_phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="pt-4">
                    <button type="submit"
                            class="w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-3 px-6 rounded-lg transition transform hover:scale-[1.02]">
                        Submit Team Registration
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
