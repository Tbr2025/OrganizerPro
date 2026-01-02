@extends('public.tournament.layouts.app')

@section('title', 'Point Table - ' . $tournament->name)

@section('content')
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8 text-center">Point Table</h1>

        @forelse($pointTableByGroups as $groupName => $entries)
            <div class="mb-8">
                @if($groupName !== 'default')
                    <h2 class="text-xl font-semibold mb-4 text-yellow-400">{{ $groupName }}</h2>
                @endif

                <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">#</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Team</th>
                                    <th class="px-4 py-3 text-center text-sm font-semibold">P</th>
                                    <th class="px-4 py-3 text-center text-sm font-semibold">W</th>
                                    <th class="px-4 py-3 text-center text-sm font-semibold">L</th>
                                    <th class="px-4 py-3 text-center text-sm font-semibold">T</th>
                                    <th class="px-4 py-3 text-center text-sm font-semibold">NR</th>
                                    <th class="px-4 py-3 text-center text-sm font-semibold">NRR</th>
                                    <th class="px-4 py-3 text-center text-sm font-semibold">Pts</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                @foreach($entries as $index => $entry)
                                    <tr class="hover:bg-gray-700/50 {{ $index < 2 ? 'border-l-4 border-green-500' : '' }}">
                                        <td class="px-4 py-3 text-sm">{{ $index + 1 }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                @if($entry->team?->logo)
                                                    <img src="{{ Storage::url($entry->team->logo) }}" alt="{{ $entry->team->name }}" class="h-8 w-8 object-contain">
                                                @else
                                                    <div class="h-8 w-8 bg-gray-600 rounded-full flex items-center justify-center">
                                                        <span class="text-xs font-bold">{{ substr($entry->team?->short_name ?? 'TBA', 0, 2) }}</span>
                                                    </div>
                                                @endif
                                                <span class="font-medium">{{ $entry->team?->name ?? 'Unknown' }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">{{ $entry->played }}</td>
                                        <td class="px-4 py-3 text-center text-green-400">{{ $entry->won }}</td>
                                        <td class="px-4 py-3 text-center text-red-400">{{ $entry->lost }}</td>
                                        <td class="px-4 py-3 text-center">{{ $entry->tied }}</td>
                                        <td class="px-4 py-3 text-center text-gray-400">{{ $entry->no_result }}</td>
                                        <td class="px-4 py-3 text-center {{ $entry->nrr >= 0 ? 'text-green-400' : 'text-red-400' }}">
                                            {{ $entry->nrr >= 0 ? '+' : '' }}{{ number_format($entry->nrr, 3) }}
                                        </td>
                                        <td class="px-4 py-3 text-center font-bold text-yellow-400">{{ $entry->points }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Legend --}}
                <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-400">
                    <span class="flex items-center gap-2">
                        <span class="w-3 h-3 bg-green-500 rounded"></span>
                        Qualified
                    </span>
                    <span>P = Played</span>
                    <span>W = Won</span>
                    <span>L = Lost</span>
                    <span>T = Tied</span>
                    <span>NR = No Result</span>
                    <span>NRR = Net Run Rate</span>
                    <span>Pts = Points</span>
                </div>
            </div>
        @empty
            <div class="text-center py-12">
                <i class="fas fa-table text-4xl text-gray-600 mb-4"></i>
                <p class="text-gray-400">Point table will be available once matches begin.</p>
            </div>
        @endforelse
    </div>
@endsection
