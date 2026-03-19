@extends('backend.layouts.app')

@section('title', 'Generate Poster | ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.dashboard', $tournament)],
    ['name' => 'Templates', 'route' => route('admin.tournaments.templates.index', $tournament)],
    ['name' => 'Generate Poster']
]" />

<div class="max-w-6xl mx-auto">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl p-6 mb-6">
        <h1 class="text-2xl font-bold text-white mb-2">Generate Poster</h1>
        <p class="text-purple-100">Select data and template to create a poster with real tournament information</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Data Selection --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Poster Type Selection --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                    <span class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 flex items-center justify-center mr-3 text-sm font-bold">1</span>
                    Select Poster Type
                </h3>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3" x-data="{ type: '{{ request('type', 'match_poster') }}' }">
                    <button type="button" @click="type = 'match_poster'; updateType('match_poster')"
                            :class="type === 'match_poster' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/30' : 'border-gray-200 dark:border-gray-700'"
                            class="p-4 rounded-xl border-2 text-center transition hover:border-purple-300">
                        <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-cyan-500 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Match Poster</span>
                    </button>

                    <button type="button" @click="type = 'match_summary'; updateType('match_summary')"
                            :class="type === 'match_summary' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/30' : 'border-gray-200 dark:border-gray-700'"
                            class="p-4 rounded-xl border-2 text-center transition hover:border-purple-300">
                        <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-yellow-500 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Match Summary</span>
                    </button>

                    <button type="button" @click="type = 'award_poster'; updateType('award_poster')"
                            :class="type === 'award_poster' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/30' : 'border-gray-200 dark:border-gray-700'"
                            class="p-4 rounded-xl border-2 text-center transition hover:border-purple-300">
                        <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-red-500 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Award Poster</span>
                    </button>

                    <button type="button" @click="type = 'welcome_card'; updateType('welcome_card')"
                            :class="type === 'welcome_card' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/30' : 'border-gray-200 dark:border-gray-700'"
                            class="p-4 rounded-xl border-2 text-center transition hover:border-purple-300">
                        <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-green-500 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Welcome Card</span>
                    </button>
                </div>
            </div>

            {{-- Data Selection Based on Type --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                    <span class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 flex items-center justify-center mr-3 text-sm font-bold">2</span>
                    Select Data
                </h3>

                {{-- Match Selection (for match_poster and match_summary) --}}
                <div id="matchSelection" class="{{ in_array(request('type', 'match_poster'), ['match_poster', 'match_summary']) ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Match</label>
                    <select id="matchSelect" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <option value="">-- Select a match --</option>
                        @foreach($matches as $match)
                            <option value="{{ $match->id }}"
                                    data-team-a="{{ $match->teamA?->name }}"
                                    data-team-b="{{ $match->teamB?->name }}"
                                    data-team-a-short="{{ $match->teamA?->short_name ?? $match->teamA?->name }}"
                                    data-team-b-short="{{ $match->teamB?->short_name ?? $match->teamB?->name }}"
                                    data-team-a-logo="{{ $match->teamA?->team_logo_url ?? '' }}"
                                    data-team-b-logo="{{ $match->teamB?->team_logo_url ?? '' }}"
                                    data-team-a-captain-image="{{ $match->teamA?->captain_image_url ?? '' }}"
                                    data-team-b-captain-image="{{ $match->teamB?->captain_image_url ?? '' }}"
                                    data-team-a-captain-name="{{ $match->teamA?->captain?->name ?? '' }}"
                                    data-team-b-captain-name="{{ $match->teamB?->captain?->name ?? '' }}"
                                    data-date="{{ $match->match_date?->format('M d, Y') }}"
                                    data-time="{{ $match->start_time ?? '' }}"
                                    data-venue="{{ $match->ground?->name ?? $match->venue }}"
                                    data-stage="{{ $match->stage }}"
                                    data-stage-display="{{ $match->stage_display }}"
                                    data-status="{{ $match->status }}"
                                    data-team-a-score="{{ $match->result?->team_a_score ?? '' }}"
                                    data-team-b-score="{{ $match->result?->team_b_score ?? '' }}"
                                    data-winner="{{ $match->winner?->name }}"
                                    data-match-number="{{ $match->match_number ?? $match->id }}">
                                Match #{{ $match->match_number ?? $match->id }}: {{ $match->teamA?->name ?? 'TBD' }} vs {{ $match->teamB?->name ?? 'TBD' }}
                                @if($match->match_date) - {{ $match->match_date->format('M d') }} @endif
                                @if($match->status === 'completed') (Completed) @endif
                            </option>
                        @endforeach
                    </select>

                    @if($matches->isEmpty())
                        <p class="text-sm text-gray-500 mt-2">No matches found. <a href="{{ route('admin.tournaments.fixtures.index', $tournament) }}" class="text-purple-600 hover:underline">Create fixtures first</a>.</p>
                    @endif
                </div>

                {{-- Player Selection (for welcome_card) --}}
                <div id="playerSelection" class="{{ request('type') === 'welcome_card' ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Player</label>
                    <select id="playerSelect" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <option value="">-- Select a player --</option>
                        @foreach($players as $player)
                            <option value="{{ $player->id }}"
                                    data-name="{{ $player->name }}"
                                    data-jersey="{{ $player->jersey_number }}"
                                    data-team="{{ $player->actualTeam?->name }}"
                                    data-team-logo="{{ $player->actualTeam?->team_logo_url ?? '' }}"
                                    data-photo="{{ $player->image_path ? asset('storage/' . $player->image_path) : '' }}"
                                    data-type="{{ $player->playerType?->type ?? '' }}"
                                    data-batting="{{ $player->battingProfile?->style ?? '' }}"
                                    data-bowling="{{ $player->bowlingProfile?->style ?? '' }}">
                                {{ $player->name }} @if($player->actualTeam) ({{ $player->actualTeam->name }}) @endif
                            </option>
                        @endforeach
                    </select>

                    @if($players->isEmpty())
                        <p class="text-sm text-gray-500 mt-2">No registered players found.</p>
                    @endif
                </div>

                {{-- Award Selection (for award_poster) --}}
                <div id="awardSelection" class="{{ request('type') === 'award_poster' ? '' : 'hidden' }}">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Match</label>
                            <select id="awardMatchSelect" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" onchange="loadMatchAwards(this.value)">
                                <option value="">-- Select a completed match --</option>
                                @foreach($completedMatches as $match)
                                    <option value="{{ $match->id }}">
                                        Match #{{ $match->match_number ?? $match->id }}: {{ $match->teamA?->name ?? 'TBD' }} vs {{ $match->teamB?->name ?? 'TBD' }}
                                        @if($match->match_date) - {{ $match->match_date->format('M d') }} @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="awardPlayerSection" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Award Winner</label>
                            <select id="awardPlayerSelect" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                <option value="">-- Select award --</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Template Selection --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                    <span class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 flex items-center justify-center mr-3 text-sm font-bold">3</span>
                    Select Template
                </h3>

                <div id="templatesList" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    @forelse($templates as $template)
                        <label class="cursor-pointer">
                            <input type="radio" name="template_id" value="{{ $template->id }}" class="hidden peer" {{ $loop->first ? 'checked' : '' }}>
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-3 transition peer-checked:border-purple-500 peer-checked:bg-purple-50 dark:peer-checked:bg-purple-900/30 hover:border-purple-300">
                                @if($template->background_image)
                                    <img src="{{ $template->background_image_url }}" alt="{{ $template->name }}" class="w-full h-24 object-cover rounded-lg mb-2">
                                @else
                                    <div class="w-full h-24 bg-gray-100 dark:bg-gray-700 rounded-lg mb-2 flex items-center justify-center">
                                        <span class="text-gray-400 text-xs">No preview</span>
                                    </div>
                                @endif
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">{{ $template->name }}</p>
                                @if($template->is_default)
                                    <span class="text-xs text-purple-600">Default</span>
                                @endif
                            </div>
                        </label>
                    @empty
                        <div class="col-span-full text-center py-8 text-gray-500">
                            <p>No templates found for this type.</p>
                            <a href="{{ route('admin.tournaments.templates.create', ['tournament' => $tournament, 'type' => request('type', 'match_poster')]) }}"
                               class="text-purple-600 hover:underline mt-2 inline-block">Create a template</a>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right: Preview & Actions --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 sticky top-4">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Preview & Generate</h3>

                {{-- Preview Area --}}
                <div id="previewArea" class="bg-gray-100 dark:bg-gray-900 rounded-xl p-4 mb-4 min-h-[300px] flex items-center justify-center">
                    <div id="previewPlaceholder" class="text-center text-gray-400">
                        <svg class="w-16 h-16 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-sm">Select data to preview</p>
                    </div>
                    <img id="previewImage" src="" alt="Preview" class="hidden max-w-full rounded-lg shadow-lg">
                    <div id="previewLoading" class="hidden text-center">
                        <svg class="w-10 h-10 mx-auto animate-spin text-purple-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-sm text-gray-500 mt-2">Generating preview...</p>
                    </div>
                </div>

                {{-- Data Summary --}}
                <div id="dataSummary" class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 mb-4 hidden">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Selected Data</h4>
                    <div id="summaryContent" class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                        <!-- Populated by JS -->
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="space-y-3">
                    <button type="button" onclick="generatePreview()" id="previewBtn"
                            class="w-full px-4 py-3 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-xl transition flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Generate Preview
                    </button>

                    <button type="button" onclick="downloadPoster()" id="downloadBtn" disabled
                            class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium rounded-xl transition flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download Poster
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let currentType = '{{ request('type', 'match_poster') }}';
let generatedImageUrl = null;

function updateType(type) {
    currentType = type;

    // Show/hide data selection sections
    document.getElementById('matchSelection').classList.toggle('hidden', !['match_poster', 'match_summary'].includes(type));
    document.getElementById('playerSelection').classList.toggle('hidden', type !== 'welcome_card');
    document.getElementById('awardSelection').classList.toggle('hidden', type !== 'award_poster');

    // Reset preview
    resetPreview();

    // Load templates for this type
    loadTemplates(type);
}

function loadTemplates(type) {
    fetch(`{{ url('admin/tournaments/' . $tournament->id . '/templates') }}?type=${type}&ajax=1`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('templatesList');
            if (data.templates && data.templates.length > 0) {
                container.innerHTML = data.templates.map((t, i) => `
                    <label class="cursor-pointer">
                        <input type="radio" name="template_id" value="${t.id}" class="hidden peer" ${i === 0 ? 'checked' : ''}>
                        <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-3 transition peer-checked:border-purple-500 peer-checked:bg-purple-50 dark:peer-checked:bg-purple-900/30 hover:border-purple-300">
                            ${t.background_image_url ?
                                `<img src="${t.background_image_url}" alt="${t.name}" class="w-full h-24 object-cover rounded-lg mb-2">` :
                                `<div class="w-full h-24 bg-gray-100 dark:bg-gray-700 rounded-lg mb-2 flex items-center justify-center"><span class="text-gray-400 text-xs">No preview</span></div>`
                            }
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">${t.name}</p>
                            ${t.is_default ? '<span class="text-xs text-purple-600">Default</span>' : ''}
                        </div>
                    </label>
                `).join('');
            } else {
                container.innerHTML = `
                    <div class="col-span-full text-center py-8 text-gray-500">
                        <p>No templates found for this type.</p>
                        <a href="{{ route('admin.tournaments.templates.create', $tournament) }}?type=${type}"
                           class="text-purple-600 hover:underline mt-2 inline-block">Create a template</a>
                    </div>
                `;
            }
        })
        .catch(err => console.error('Error loading templates:', err));
}

function resetPreview() {
    document.getElementById('previewPlaceholder').classList.remove('hidden');
    document.getElementById('previewImage').classList.add('hidden');
    document.getElementById('previewLoading').classList.add('hidden');
    document.getElementById('dataSummary').classList.add('hidden');
    document.getElementById('downloadBtn').disabled = true;
    generatedImageUrl = null;
}

function getSelectedData() {
    const data = { type: currentType };

    if (['match_poster', 'match_summary'].includes(currentType)) {
        const matchSelect = document.getElementById('matchSelect');
        const selected = matchSelect.options[matchSelect.selectedIndex];
        if (selected && selected.value) {
            data.match_id = selected.value;
            data.team_a_name = selected.dataset.teamA;
            data.team_b_name = selected.dataset.teamB;
            data.team_a_short_name = selected.dataset.teamAShort;
            data.team_b_short_name = selected.dataset.teamBShort;
            data.team_a_logo = selected.dataset.teamALogo;
            data.team_b_logo = selected.dataset.teamBLogo;
            data.team_a_captain_image = selected.dataset.teamACaptainImage;
            data.team_b_captain_image = selected.dataset.teamBCaptainImage;
            data.team_a_captain_name = selected.dataset.teamACaptainName;
            data.team_b_captain_name = selected.dataset.teamBCaptainName;
            data.match_date = selected.dataset.date;
            data.match_time = selected.dataset.time;
            data.venue = selected.dataset.venue;
            data.ground_name = selected.dataset.venue;
            data.match_stage = selected.dataset.stageDisplay || selected.dataset.stage;
            data.match_number = selected.dataset.matchNumber;
            if (currentType === 'match_summary') {
                data.team_a_score = selected.dataset.teamAScore;
                data.team_b_score = selected.dataset.teamBScore;
                data.winner_name = selected.dataset.winner;
            }
        }
    } else if (currentType === 'welcome_card') {
        const playerSelect = document.getElementById('playerSelect');
        const selected = playerSelect.options[playerSelect.selectedIndex];
        if (selected && selected.value) {
            data.player_id = selected.value;
            data.player_name = selected.dataset.name;
            data.jersey_number = selected.dataset.jersey;
            data.team_name = selected.dataset.team;
            data.team_logo = selected.dataset.teamLogo;
            data.player_image = selected.dataset.photo;
            data.player_type = selected.dataset.type;
            data.batting_style = selected.dataset.batting;
            data.bowling_style = selected.dataset.bowling;
        }
    } else if (currentType === 'award_poster') {
        const awardSelect = document.getElementById('awardPlayerSelect');
        const selected = awardSelect.options[awardSelect.selectedIndex];
        if (selected && selected.value) {
            // Parse award data from option
            const awardData = JSON.parse(selected.dataset.award || '{}');
            Object.assign(data, awardData);
        }
    }

    // Get selected template
    const templateInput = document.querySelector('input[name="template_id"]:checked');
    if (templateInput) {
        data.template_id = templateInput.value;
    }

    return data;
}

function showDataSummary(data) {
    const summary = document.getElementById('dataSummary');
    const content = document.getElementById('summaryContent');

    let html = '';
    if (data.team_a_name && data.team_b_name) {
        html += `<p><strong>${data.team_a_name}</strong> vs <strong>${data.team_b_name}</strong></p>`;
    }
    if (data.match_date) html += `<p>Date: ${data.match_date}</p>`;
    if (data.venue) html += `<p>Venue: ${data.venue}</p>`;
    if (data.team_a_captain_name || data.team_b_captain_name) {
        html += `<p class="text-xs text-gray-500 mt-1">Captains: ${data.team_a_captain_name || 'TBD'} vs ${data.team_b_captain_name || 'TBD'}</p>`;
    }
    if (data.team_a_captain_image || data.team_b_captain_image) {
        html += `<div class="flex items-center gap-2 mt-2">`;
        if (data.team_a_captain_image) html += `<img src="${data.team_a_captain_image}" class="w-8 h-8 rounded-full object-cover border-2 border-white shadow-sm">`;
        if (data.team_a_logo) html += `<img src="${data.team_a_logo}" class="w-6 h-6 object-contain">`;
        html += `<span class="text-xs text-gray-400">vs</span>`;
        if (data.team_b_logo) html += `<img src="${data.team_b_logo}" class="w-6 h-6 object-contain">`;
        if (data.team_b_captain_image) html += `<img src="${data.team_b_captain_image}" class="w-8 h-8 rounded-full object-cover border-2 border-white shadow-sm">`;
        html += `</div>`;
    }
    if (data.player_name) html += `<p>Player: <strong>${data.player_name}</strong></p>`;
    if (data.team_name) html += `<p>Team: ${data.team_name}</p>`;
    if (data.award_name) html += `<p>Award: ${data.award_name}</p>`;

    if (html) {
        content.innerHTML = html;
        summary.classList.remove('hidden');
    }
}

function generatePreview() {
    const data = getSelectedData();

    if (!data.template_id) {
        alert('Please select a template');
        return;
    }

    if (['match_poster', 'match_summary'].includes(currentType) && !data.match_id) {
        alert('Please select a match');
        return;
    }

    if (currentType === 'welcome_card' && !data.player_id) {
        alert('Please select a player');
        return;
    }

    // Show loading
    document.getElementById('previewPlaceholder').classList.add('hidden');
    document.getElementById('previewImage').classList.add('hidden');
    document.getElementById('previewLoading').classList.remove('hidden');

    showDataSummary(data);

    // Call API to generate preview
    fetch(`{{ route('admin.tournaments.templates.generate-preview', $tournament) }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        document.getElementById('previewLoading').classList.add('hidden');

        if (result.success && result.image) {
            document.getElementById('previewImage').src = result.image;
            document.getElementById('previewImage').classList.remove('hidden');
            document.getElementById('downloadBtn').disabled = false;
            generatedImageUrl = result.image;
        } else {
            document.getElementById('previewPlaceholder').classList.remove('hidden');
            alert(result.error || 'Failed to generate preview');
        }
    })
    .catch(err => {
        document.getElementById('previewLoading').classList.add('hidden');
        document.getElementById('previewPlaceholder').classList.remove('hidden');
        console.error('Error:', err);
        alert('Failed to generate preview');
    });
}

function downloadPoster() {
    if (!generatedImageUrl) return;

    const link = document.createElement('a');
    link.href = generatedImageUrl;
    link.download = `poster-${currentType}-${Date.now()}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function loadMatchAwards(matchId) {
    if (!matchId) {
        document.getElementById('awardPlayerSection').classList.add('hidden');
        return;
    }

    fetch(`{{ url('admin/tournaments/' . $tournament->id . '/matches') }}/${matchId}/awards`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('awardPlayerSelect');
            select.innerHTML = '<option value="">-- Select award --</option>';

            if (data.awards && data.awards.length > 0) {
                data.awards.forEach(award => {
                    const opt = document.createElement('option');
                    opt.value = award.id;
                    opt.textContent = `${award.award_name}: ${award.player_name}`;
                    opt.dataset.award = JSON.stringify({
                        award_name: award.award_name,
                        player_name: award.player_name,
                        player_image: award.player_image,
                        team_name: award.team_name,
                        team_logo: award.team_logo,
                        match_id: matchId
                    });
                    select.appendChild(opt);
                });
            }

            document.getElementById('awardPlayerSection').classList.remove('hidden');
        })
        .catch(err => console.error('Error loading awards:', err));
}

// Event listeners
document.getElementById('matchSelect')?.addEventListener('change', function() {
    if (this.value) showDataSummary(getSelectedData());
});
document.getElementById('playerSelect')?.addEventListener('change', function() {
    if (this.value) showDataSummary(getSelectedData());
});
</script>
@endpush
@endsection
