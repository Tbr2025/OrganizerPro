@extends('backend.layouts.app')

@section('title', 'Create Template')

@section('admin-content')
<div class="max-w-3xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Template</h1>
        <p class="text-gray-500 dark:text-gray-400 mt-1">Select a tournament and template type to open the editor</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-6">
        {{-- Tournament Selection --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Tournament</label>
            <select id="tournament-select" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                <option value="">-- Select a tournament --</option>
                @foreach($tournaments as $tournament)
                    <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
                @endforeach
            </select>
            @if($tournaments->isEmpty())
                <p class="text-sm text-red-500 mt-2">No active tournaments found. <a href="{{ route('admin.tournaments.create') }}" class="underline">Create one first</a>.</p>
            @endif
        </div>

        {{-- Template Type Selection --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Template Type</label>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @php
                    $typeIcons = [
                        'welcome_card' => ['icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'color' => 'bg-green-500'],
                        'match_poster' => ['icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'color' => 'bg-cyan-500'],
                        'match_summary' => ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'color' => 'bg-yellow-500'],
                        'award_poster' => ['icon' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z', 'color' => 'bg-red-500'],
                        'flyer' => ['icon' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z', 'color' => 'bg-purple-500'],
                        'champions_poster' => ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'color' => 'bg-amber-500'],
                        'point_table' => ['icon' => 'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z', 'color' => 'bg-indigo-500'],
                    ];
                @endphp

                @foreach($templateTypes as $type)
                    @php $meta = $typeIcons[$type] ?? ['icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16', 'color' => 'bg-gray-500']; @endphp
                    <label class="cursor-pointer">
                        <input type="radio" name="template_type" value="{{ $type }}" class="hidden peer" {{ $loop->first ? 'checked' : '' }}>
                        <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center transition peer-checked:border-purple-500 peer-checked:bg-purple-50 dark:peer-checked:bg-purple-900/30 hover:border-purple-300">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-lg {{ $meta['color'] }} flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $meta['icon'] }}"/>
                                </svg>
                            </div>
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ \App\Models\TournamentTemplate::getTypeDisplay($type) }}</span>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Action Button --}}
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="button" id="open-editor-btn" class="w-full px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-xl transition flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Open Template Editor
            </button>
        </div>
    </div>

    {{-- Existing Templates Quick Access --}}
    <div class="mt-8 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Existing Tournament Templates</h3>
        <div id="existing-templates" class="text-sm text-gray-500 dark:text-gray-400">
            Select a tournament above to view its existing templates.
        </div>
    </div>
</div>

@push('scripts')
<script>
    const tournamentSelect = document.getElementById('tournament-select');

    document.getElementById('open-editor-btn').addEventListener('click', function() {
        const tournamentId = tournamentSelect.value;
        const type = document.querySelector('input[name="template_type"]:checked')?.value;

        if (!tournamentId) {
            alert('Please select a tournament');
            return;
        }
        if (!type) {
            alert('Please select a template type');
            return;
        }

        window.location.href = `/admin/tournaments/${tournamentId}/templates/create?type=${type}`;
    });

    // Load existing templates when tournament is selected
    tournamentSelect.addEventListener('change', function() {
        const tournamentId = this.value;
        const container = document.getElementById('existing-templates');

        if (!tournamentId) {
            container.innerHTML = '<p class="text-gray-500">Select a tournament above to view its existing templates.</p>';
            return;
        }

        container.innerHTML = '<p class="text-gray-400">Loading...</p>';

        fetch(`/admin/tournaments/${tournamentId}/templates?ajax=1`)
            .then(res => res.json())
            .then(data => {
                if (data.templates && data.templates.length > 0) {
                    let html = '<div class="grid grid-cols-2 md:grid-cols-3 gap-3">';
                    data.templates.forEach(t => {
                        html += `
                            <a href="/admin/tournaments/${tournamentId}/templates/${t.id}/edit" class="block border border-gray-200 dark:border-gray-700 rounded-lg p-3 hover:border-purple-400 transition">
                                ${t.background_image_url
                                    ? `<img src="${t.background_image_url}" class="w-full h-20 object-cover rounded mb-2">`
                                    : `<div class="w-full h-20 bg-gray-100 dark:bg-gray-700 rounded mb-2 flex items-center justify-center"><span class="text-gray-400 text-xs">No preview</span></div>`
                                }
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">${t.name}</p>
                                <span class="text-xs text-gray-400">${t.type.replace('_', ' ')}</span>
                                ${t.is_default ? ' <span class="text-xs text-purple-600">Default</span>' : ''}
                            </a>`;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p class="text-gray-500">No templates yet for this tournament. Create one above!</p>';
                }
            })
            .catch(() => {
                container.innerHTML = '<p class="text-red-500">Failed to load templates.</p>';
            });
    });
</script>
@endpush
@endsection
