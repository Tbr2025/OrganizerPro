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
            <form method="POST" action="{{ route('public.tournament.registration.team.store', $tournament->slug) }}"
                  enctype="multipart/form-data" class="p-6 space-y-6">
                @csrf

                {{-- Team Info Section --}}
                <div class="border-b border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold mb-4 text-yellow-400">Team Information</h2>

                    {{-- Team Name (always visible) --}}
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
                    @if($teamFieldConfig['team_short_name']['visible'] ?? true)
                    <div class="mb-4">
                        <label for="team_short_name" class="block text-sm font-medium mb-2">Short Name (Abbreviation) @if($teamFieldConfig['team_short_name']['required'] ?? false)<span class="text-red-500">*</span>@endif</label>
                        <input type="text" name="team_short_name" id="team_short_name" value="{{ old('team_short_name') }}" maxlength="10"
                               {{ ($teamFieldConfig['team_short_name']['required'] ?? false) ? 'required' : '' }}
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="e.g., SRH, MI, CSK">
                        @error('team_short_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif

                    {{-- Team Logo --}}
                    @if($teamFieldConfig['team_logo']['visible'] ?? true)
                    <div class="mb-4">
                        <label for="team_logo" class="block text-sm font-medium mb-2">Team Logo @if($teamFieldConfig['team_logo']['required'] ?? false)<span class="text-red-500">*</span>@endif</label>
                        <input type="file" name="team_logo" id="team_logo" accept="image/png,image/jpeg,image/jpg"
                               {{ ($teamFieldConfig['team_logo']['required'] ?? false) ? 'required' : '' }}
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-yellow-500 file:text-gray-900 file:font-semibold hover:file:bg-yellow-600">
                        <p class="text-gray-500 text-xs mt-1">PNG or JPG, max 2MB</p>
                        @error('team_logo')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif

                    {{-- Team Description --}}
                    @if($teamFieldConfig['team_description']['visible'] ?? true)
                    <div>
                        <label for="team_description" class="block text-sm font-medium mb-2">Team Description @if($teamFieldConfig['team_description']['required'] ?? false)<span class="text-red-500">*</span>@endif</label>
                        <textarea name="team_description" id="team_description" rows="3"
                                  {{ ($teamFieldConfig['team_description']['required'] ?? false) ? 'required' : '' }}
                                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                  placeholder="Brief description about your team (optional)">{{ old('team_description') }}</textarea>
                        @error('team_description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif
                </div>

                {{-- Team Manager Info Section --}}
                <div class="border-b border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold mb-4 text-yellow-400">Team Manager Details</h2>

                    {{-- Manager Name (always visible) --}}
                    <div class="mb-4">
                        <label for="captain_name" class="block text-sm font-medium mb-2">Manager Name <span class="text-red-500">*</span></label>
                        <input type="text" name="captain_name" id="captain_name" value="{{ old('captain_name') }}" required
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="Full name of team manager">
                        @error('captain_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Manager Email (always visible) --}}
                    <div class="mb-4">
                        <label for="captain_email" class="block text-sm font-medium mb-2">Manager Email <span class="text-red-500">*</span></label>
                        <input type="email" name="captain_email" id="captain_email" value="{{ old('captain_email') }}" required
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="manager@email.com">
                        @error('captain_email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Manager Phone --}}
                    @if($teamFieldConfig['captain_phone']['visible'] ?? true)
                    <div>
                        <label for="captain_phone" class="block text-sm font-medium mb-2">Manager Phone @if($teamFieldConfig['captain_phone']['required'] ?? true)<span class="text-red-500">*</span>@endif</label>
                        <input type="tel" name="captain_phone" id="captain_phone" value="{{ old('captain_phone') }}"
                               {{ ($teamFieldConfig['captain_phone']['required'] ?? true) ? 'required' : '' }}
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="+971 50 123 4567">
                        @error('captain_phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif
                </div>

                {{-- Team Owner Info Section --}}
                @if(($teamFieldConfig['vice_captain_name']['visible'] ?? true) || ($teamFieldConfig['vice_captain_email']['visible'] ?? true) || ($teamFieldConfig['vice_captain_phone']['visible'] ?? true))
                <div class="border-b border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold mb-4 text-yellow-400">Team Owner Details @if(!($teamFieldConfig['vice_captain_name']['required'] ?? false))(Optional)@endif</h2>

                    {{-- Owner Name --}}
                    @if($teamFieldConfig['vice_captain_name']['visible'] ?? true)
                    <div class="mb-4">
                        <label for="vice_captain_name" class="block text-sm font-medium mb-2">Owner Name @if($teamFieldConfig['vice_captain_name']['required'] ?? false)<span class="text-red-500">*</span>@endif</label>
                        <input type="text" name="vice_captain_name" id="vice_captain_name" value="{{ old('vice_captain_name') }}"
                               {{ ($teamFieldConfig['vice_captain_name']['required'] ?? false) ? 'required' : '' }}
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="Full name of team owner">
                        @error('vice_captain_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif

                    {{-- Owner Email --}}
                    @if($teamFieldConfig['vice_captain_email']['visible'] ?? true)
                    <div class="mb-4">
                        <label for="vice_captain_email" class="block text-sm font-medium mb-2">Owner Email @if($teamFieldConfig['vice_captain_email']['required'] ?? false)<span class="text-red-500">*</span>@endif</label>
                        <input type="email" name="vice_captain_email" id="vice_captain_email" value="{{ old('vice_captain_email') }}"
                               {{ ($teamFieldConfig['vice_captain_email']['required'] ?? false) ? 'required' : '' }}
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="owner@email.com">
                        @error('vice_captain_email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif

                    {{-- Owner Phone --}}
                    @if($teamFieldConfig['vice_captain_phone']['visible'] ?? true)
                    <div>
                        <label for="vice_captain_phone" class="block text-sm font-medium mb-2">Owner Phone @if($teamFieldConfig['vice_captain_phone']['required'] ?? false)<span class="text-red-500">*</span>@endif</label>
                        <input type="tel" name="vice_captain_phone" id="vice_captain_phone" value="{{ old('vice_captain_phone') }}"
                               {{ ($teamFieldConfig['vice_captain_phone']['required'] ?? false) ? 'required' : '' }}
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                               placeholder="+971 50 123 4567">
                        @error('vice_captain_phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif
                </div>
                @endif

                {{-- Terms & Conditions --}}
                @if($teamFieldConfig['terms_and_conditions']['visible'] ?? false)
                <div class="border-b border-gray-700 pb-4 mb-4">
                    @if(!empty($settings->terms_and_conditions_content ?? ''))
                    <div x-data="{ showTC: false }">
                        <button type="button" @click="showTC = !showTC" class="text-yellow-500 hover:text-yellow-400 text-sm underline mb-3">
                            View Terms & Conditions
                        </button>
                        <div x-show="showTC" x-cloak class="mb-4 p-4 bg-gray-700 rounded-lg text-sm text-gray-300 max-h-48 overflow-y-auto whitespace-pre-wrap">{{ $settings->terms_and_conditions_content }}</div>
                    </div>
                    @endif

                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="terms_and_conditions" id="terms_and_conditions" value="1"
                               class="w-5 h-5 bg-gray-700 border-gray-600 rounded text-yellow-500 focus:ring-yellow-500"
                               {{ old('terms_and_conditions') ? 'checked' : '' }}
                               {{ ($teamFieldConfig['terms_and_conditions']['required'] ?? false) ? 'required' : '' }}>
                        <span class="ml-3 text-sm">
                            I agree to the Terms & Conditions @if($teamFieldConfig['terms_and_conditions']['required'] ?? false)<span class="text-red-500">*</span>@endif
                        </span>
                    </label>
                    @error('terms_and_conditions')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @endif

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
