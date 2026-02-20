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
                        <a href="{{ route('admin.tournaments.templates.download', [$tournament, $template]) }}?{{ http_build_query(request()->only(['player_name', 'jersey_name', 'jersey_number', 'team_name', 'team_a_name', 'team_b_name', 'team_a_score', 'team_b_score', 'match_date', 'match_time', 'venue'])) }}"
                           class="btn-primary">
                            Download HD
                        </a>
                    @endif
                </div>
            </div>

            <!-- Error Message -->
            @if(isset($previewError) && $previewError)
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        {{ $previewError }}
                    </div>
                </div>
            @endif

            <!-- Layout Info -->
            @if(empty($template->layout_json))
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded mb-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        No layout elements configured. <a href="{{ route('admin.tournaments.templates.edit', [$tournament, $template]) }}" class="underline font-medium">Add elements to the template</a> to see a rendered preview.
                    </div>
                </div>
            @endif

            <!-- Preview Image -->
            <div class="bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden flex items-center justify-center p-4">
                @if($previewUrl)
                    <img src="{{ $previewUrl }}"
                         alt="Template Preview"
                         class="max-w-full h-auto shadow-lg rounded"
                         id="previewImage">
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

            <!-- Layout Elements Debug -->
            @if(!empty($template->layout_json))
                <div class="mt-4 text-xs text-gray-500">
                    <span class="font-medium">{{ count($template->layout_json) }} elements</span> in layout
                </div>
            @endif
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
                            &#123;&#123;{{ $key }}&#125;&#125;
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
