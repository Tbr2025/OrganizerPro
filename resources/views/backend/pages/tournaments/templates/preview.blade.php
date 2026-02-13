@extends('backend.layouts.app')

@section('title', 'Preview Template | ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.show', $tournament)],
    ['name' => 'Templates', 'route' => route('admin.tournaments.templates.index', $tournament)],
    ['name' => 'Preview: ' . $template->name]
]" />

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Preview Panel -->
    <div class="lg:col-span-2">
        <div class="card p-4">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-xl font-bold">{{ $template->name }}</h2>
                    <p class="text-gray-500 text-sm">{{ $template->type_display }} Template Preview</p>
                </div>
                <div class="flex space-x-2">
                    <a href="{{ route('admin.tournaments.templates.edit', [$tournament, $template]) }}"
                       class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Edit Template
                    </a>
                    @if($previewUrl)
                        <a href="{{ $previewUrl }}"
                           download="preview-{{ $template->type }}.png"
                           class="btn-primary">
                            Download Preview
                        </a>
                    @endif
                </div>
            </div>

            <!-- Preview Image -->
            <div class="bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden flex items-center justify-center p-4">
                @if($previewUrl)
                    <img src="{{ $previewUrl }}"
                         alt="Template Preview"
                         class="max-w-full h-auto shadow-lg rounded">
                @else
                    <div class="text-center text-gray-400 py-16">
                        <svg class="w-24 h-24 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-lg">Preview not available</p>
                        <p class="text-sm mt-2">Upload a background image to generate previews</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Sample Data Panel -->
    <div class="lg:col-span-1">
        <div class="card p-4 sticky top-4">
            <h3 class="font-bold mb-4">Sample Data Used</h3>

            <div class="space-y-4 text-sm">
                @foreach($sampleData as $key => $value)
                    <div class="bg-gray-50 dark:bg-gray-800 rounded p-3">
                        <div class="font-medium text-gray-600 dark:text-gray-400 text-xs uppercase mb-1">
                            {{ '{{' . $key . '}}' }}
                        </div>
                        <div class="text-gray-900 dark:text-white">
                            @if(is_array($value))
                                <pre class="text-xs overflow-x-auto">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                            @elseif(str_contains($value, '/') && (str_contains($value, '.png') || str_contains($value, '.jpg')))
                                <span class="text-blue-600">[Image]</span>
                            @else
                                {{ $value }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Regenerate with Custom Data -->
            <div class="mt-6 pt-4 border-t">
                <h4 class="font-medium text-sm mb-3">Test with Custom Data</h4>
                <form action="{{ route('admin.tournaments.templates.preview', [$tournament, $template]) }}" method="GET">
                    @if($template->type === 'welcome_card')
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Player Name</label>
                                <input type="text"
                                       name="player_name"
                                       value="{{ request('player_name', 'John Doe') }}"
                                       class="w-full text-sm rounded border-gray-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Jersey Number</label>
                                <input type="text"
                                       name="jersey_number"
                                       value="{{ request('jersey_number', '10') }}"
                                       class="w-full text-sm rounded border-gray-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Team Name</label>
                                <input type="text"
                                       name="team_name"
                                       value="{{ request('team_name', 'Sample Team') }}"
                                       class="w-full text-sm rounded border-gray-300">
                            </div>
                        </div>
                    @elseif($template->type === 'match_poster' || $template->type === 'match_summary')
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Team A Name</label>
                                <input type="text"
                                       name="team_a_name"
                                       value="{{ request('team_a_name', 'Team Alpha') }}"
                                       class="w-full text-sm rounded border-gray-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Team B Name</label>
                                <input type="text"
                                       name="team_b_name"
                                       value="{{ request('team_b_name', 'Team Beta') }}"
                                       class="w-full text-sm rounded border-gray-300">
                            </div>
                            @if($template->type === 'match_summary')
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Score A</label>
                                        <input type="text"
                                               name="team_a_score"
                                               value="{{ request('team_a_score', '150/6') }}"
                                               class="w-full text-sm rounded border-gray-300">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Score B</label>
                                        <input type="text"
                                               name="team_b_score"
                                               value="{{ request('team_b_score', '145/8') }}"
                                               class="w-full text-sm rounded border-gray-300">
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <button type="submit" class="mt-4 w-full btn-primary text-sm">
                        Regenerate Preview
                    </button>
                </form>
            </div>

            <!-- Back Link -->
            <div class="mt-4">
                <a href="{{ route('admin.tournaments.templates.index', $tournament) }}"
                   class="block text-center text-sm text-gray-600 hover:text-gray-800">
                    &larr; Back to Templates
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
