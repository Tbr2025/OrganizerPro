@extends('backend.layouts.app')

@section('title', 'Match Summary | ' . $match->match_title)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Matches', 'route' => route('admin.matches.index')],
    ['name' => $match->match_title, 'route' => route('admin.matches.show', $match)],
    ['name' => 'Summary Editor']
]" />

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Match Info Card -->
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold">{{ $match->match_title }}</h2>
                    <p class="text-gray-500">{{ $tournament->name }} - {{ $match->stage_display }}</p>
                    @if($match->match_date)
                        <p class="text-sm text-gray-400">{{ $match->match_date->format('D, M d, Y') }}</p>
                    @endif
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                        {{ $match->status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                        {{ ucfirst($match->status) }}
                    </span>
                </div>
            </div>

            @if($match->result)
                <div class="mt-4 grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold {{ $match->winner_team_id === $match->team_a_id ? 'text-green-600' : '' }}">
                            {{ $match->result->team_a_score ?? '-' }}/{{ $match->result->team_a_wickets ?? '-' }}
                        </div>
                        <div class="text-sm text-gray-500">{{ $match->teamA?->name }}</div>
                    </div>
                    <div class="self-center">
                        <span class="text-gray-400">vs</span>
                    </div>
                    <div>
                        <div class="text-2xl font-bold {{ $match->winner_team_id === $match->team_b_id ? 'text-green-600' : '' }}">
                            {{ $match->result->team_b_score ?? '-' }}/{{ $match->result->team_b_wickets ?? '-' }}
                        </div>
                        <div class="text-sm text-gray-500">{{ $match->teamB?->name }}</div>
                    </div>
                </div>

                @if($match->result->result_summary)
                    <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                        <span class="font-medium">{{ $match->result->result_summary }}</span>
                    </div>
                @endif
            @else
                <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900 rounded-lg text-center">
                    <p class="text-yellow-600 dark:text-yellow-300">Match result not yet recorded.</p>
                    <a href="{{ route('admin.matches.result.edit', $match) }}" class="text-primary-600 underline">
                        Record Result
                    </a>
                </div>
            @endif
        </div>

        <!-- Highlights Section -->
        <div class="card p-4">
            <h3 class="font-bold mb-4">Match Highlights</h3>

            @if($summary->hasHighlights())
                <ul class="space-y-2 mb-4">
                    @foreach($summary->highlights as $index => $highlight)
                        <li class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-800 rounded">
                            <span>{{ $highlight }}</span>
                            <form action="{{ route('admin.matches.summary.remove-highlight', $match) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="index" value="{{ $index }}">
                                <button type="submit" class="text-red-500 hover:text-red-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form action="{{ route('admin.matches.summary.add-highlight', $match) }}" method="POST" class="flex gap-2">
                @csrf
                <input type="text"
                       name="highlight"
                       placeholder="Add a highlight (e.g., Player X scored 50 runs)"
                       class="flex-1 rounded-lg border-gray-300"
                       required>
                <button type="submit" class="btn-primary">Add</button>
            </form>
        </div>

        <!-- Commentary Section -->
        <div class="card p-4">
            <h3 class="font-bold mb-4">Commentary / Notes</h3>

            <form action="{{ route('admin.matches.summary.update', $match) }}" method="POST">
                @csrf
                @method('PUT')

                <textarea name="commentary"
                          rows="5"
                          class="w-full rounded-lg border-gray-300"
                          placeholder="Add match commentary or notes...">{{ $summary->commentary }}</textarea>

                <div class="mt-3 flex justify-end">
                    <button type="submit" class="btn-primary">Save Commentary</button>
                </div>
            </form>
        </div>

        <!-- Awards Section -->
        <div class="card p-4">
            <h3 class="font-bold mb-4">Match Awards</h3>

            @if($awards->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    @foreach($awards as $award)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex items-center">
                                @if($award->tournamentAward && $award->tournamentAward->icon)
                                    <span class="text-2xl mr-3">{{ $award->tournamentAward->icon }}</span>
                                @endif
                                <div>
                                    <div class="font-medium">{{ $award->tournamentAward?->name ?? 'Award' }}</div>
                                    <div class="text-sm text-gray-500">{{ $award->player?->name ?? 'Unknown' }}</div>
                                </div>
                            </div>
                            <form action="{{ route('admin.matches.summary.remove-award', [$match, $award]) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('admin.matches.summary.assign-award', $match) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf

                <select name="tournament_award_id" required class="rounded-lg border-gray-300">
                    <option value="">Select Award</option>
                    @foreach($tournamentAwards as $award)
                        <option value="{{ $award->id }}">{{ $award->name }}</option>
                    @endforeach
                </select>

                <select name="player_id" required class="rounded-lg border-gray-300">
                    <option value="">Select Player</option>
                    @foreach($players as $player)
                        @if($player)
                            <option value="{{ $player->id }}">{{ $player->name }}</option>
                        @endif
                    @endforeach
                </select>

                <button type="submit" class="btn-primary">Assign Award</button>
            </form>
        </div>
    </div>

    <!-- Sidebar: Poster Preview & Actions -->
    <div class="lg:col-span-1">
        <div class="card p-4 sticky top-4">
            <h3 class="font-bold mb-4">Summary Poster</h3>

            <!-- Poster Preview -->
            <div class="aspect-[3/4] bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden mb-4">
                @if($summary->summary_poster)
                    <img src="{{ $summary->poster_url }}"
                         alt="Summary Poster"
                         class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                        <div class="text-center">
                            <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p>No poster generated</p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Actions -->
            <div class="space-y-2">
                <form action="{{ route('admin.matches.summary.generate-poster', $match) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full btn-primary">
                        {{ $summary->summary_poster ? 'Regenerate Poster' : 'Generate Poster' }}
                    </button>
                </form>

                @if($summary->summary_poster)
                    <a href="{{ route('admin.matches.summary.download-poster', $match) }}"
                       class="block w-full text-center px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Download Poster
                    </a>

                    @if(!$summary->poster_sent)
                        <form action="{{ route('admin.matches.summary.send', $match) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                Send to Teams
                            </button>
                        </form>
                    @else
                        <div class="text-center text-sm text-green-600">
                            Sent on {{ $summary->poster_sent_at->format('M d, Y H:i') }}
                        </div>
                    @endif
                @endif
            </div>

            <!-- Share Links -->
            <div class="mt-6 pt-4 border-t">
                <h4 class="font-medium text-sm mb-2">Share Links</h4>
                <div class="space-y-2">
                    <a href="{{ route('public.match.summary', $match->slug) }}"
                       target="_blank"
                       class="flex items-center text-sm text-primary-600 hover:underline">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                        View Public Summary Page
                    </a>

                    <button type="button"
                            onclick="copyToClipboard('{{ route('public.match.summary', $match->slug) }}')"
                            class="flex items-center text-sm text-gray-600 hover:text-gray-800">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        Copy Link
                    </button>

                    @php
                        $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                        $whatsappLink = $whatsappService->getResultShareLink($match);
                    @endphp
                    <a href="{{ $whatsappLink }}"
                       target="_blank"
                       class="flex items-center text-sm text-green-600 hover:text-green-800">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Share on WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Link copied to clipboard!');
    });
}
</script>
@endpush
@endsection
