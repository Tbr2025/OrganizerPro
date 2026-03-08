@php
    $settings = $tournament->settings;
    $logo = $settings?->logo ? Storage::url($settings->logo) : null;
@endphp

<a href="{{ route('public.tournament.show', $tournament->slug) }}"
   class="tournament-card rounded-2xl overflow-hidden border border-white/10 block group">
    {{-- Tournament Banner/Logo --}}
    <div class="h-40 bg-gradient-to-br from-gray-700 to-gray-800 relative overflow-hidden">
        @if($logo)
            <img src="{{ $logo }}" alt="{{ $tournament->name }}"
                 class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition-opacity">
        @else
            <div class="absolute inset-0 flex items-center justify-center">
                <i class="fas fa-trophy text-6xl text-gray-600"></i>
            </div>
        @endif

        {{-- Status Badge --}}
        <div class="absolute top-3 right-3">
            @if($tournament->status === 'registration')
                <span class="bg-green-500 text-white px-3 py-1 rounded-full text-xs font-semibold flex items-center gap-1">
                    <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                    Registration Open
                </span>
            @elseif($tournament->status === 'ongoing')
                <span class="bg-yellow-500 text-gray-900 px-3 py-1 rounded-full text-xs font-semibold">
                    Live
                </span>
            @else
                <span class="bg-gray-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                    Completed
                </span>
            @endif
        </div>
    </div>

    {{-- Tournament Info --}}
    <div class="p-5">
        <h4 class="text-xl font-bold mb-2 group-hover:text-yellow-400 transition-colors">{{ $tournament->name }}</h4>

        <div class="space-y-2 text-sm text-gray-400">
            @if($tournament->start_date)
                <div class="flex items-center gap-2">
                    <i class="fas fa-calendar w-4"></i>
                    <span>
                        {{ \Carbon\Carbon::parse($tournament->start_date)->format('M d, Y') }}
                        @if($tournament->end_date)
                            - {{ \Carbon\Carbon::parse($tournament->end_date)->format('M d, Y') }}
                        @endif
                    </span>
                </div>
            @endif

            @if($tournament->location)
                <div class="flex items-center gap-2">
                    <i class="fas fa-map-marker-alt w-4"></i>
                    <span>{{ $tournament->location }}</span>
                </div>
            @endif

            @if($tournament->format)
                <div class="flex items-center gap-2">
                    <i class="fas fa-cricket w-4"></i>
                    <span>{{ ucfirst($tournament->format) }}</span>
                </div>
            @endif
        </div>

        {{-- Registration Buttons --}}
        @if($showRegister && $tournament->status === 'registration')
            <div class="mt-4 pt-4 border-t border-white/10 flex gap-2">
                @if($settings?->player_registration_enabled)
                    <span class="flex-1 bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 font-semibold py-2 px-4 rounded-lg text-center text-sm group-hover:shadow-lg transition-shadow">
                        <i class="fas fa-user-plus mr-1"></i> Player
                    </span>
                @endif
                @if($settings?->team_registration_enabled)
                    <span class="flex-1 bg-white/10 text-white font-semibold py-2 px-4 rounded-lg text-center text-sm border border-white/20">
                        <i class="fas fa-users mr-1"></i> Team
                    </span>
                @endif
            </div>
        @else
            <div class="mt-4 pt-4 border-t border-white/10">
                <span class="text-yellow-400 font-semibold text-sm group-hover:underline">
                    View Details <i class="fas fa-arrow-right ml-1"></i>
                </span>
            </div>
        @endif
    </div>
</a>
